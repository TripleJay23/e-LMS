<?php

namespace local_profileenrol;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Handle user_created event.
     * No action is needed here; enrolment runs after confirmation/login.
     */
    public static function user_created(\core\event\user_created $event) {
        // Intentionally empty.
    }

    /**
     * Handle user_updated event (e.g. admin confirmation).
     */
    public static function user_updated(\core\event\user_updated $event) {
        self::enrol_if_ready((int)$event->objectid);
    }

    /**
     * Handle user_loggedin event.
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        self::enrol_if_ready((int)$event->objectid);
    }

    /**
     * If user is confirmed and has a valid unused token, enrol and then claim token.
     * The process is transactional and idempotent.
     */
    private static function enrol_if_ready(int $userid): void {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user || empty($user->confirmed)) {
            return;
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        profile_load_data($user);

        $programacronym = strtoupper(trim((string)($user->profile_field_program_study ?? '')));
        $year = (int)($user->profile_field_year_of_study ?? 0);
        $regnumber = trim((string)($user->profile_field_reg_number ?? ''));

        if ($programacronym === '' || $year < 1 || $regnumber === '') {
            return;
        }

        $token = $DB->get_record('custom_reg_tokens', ['reg_number' => $regnumber], '*', IGNORE_MISSING);
        if (!$token || !self::is_token_available($token)) {
            return;
        }
        if (strcasecmp((string)$token->program, $programacronym) !== 0) {
            return;
        }
        if ((int)$token->year !== $year) {
            return;
        }

        $program = $DB->get_record('custom_programs', ['acronym' => $programacronym], '*', IGNORE_MISSING);
        if (!$program) {
            return;
        }

        $links = $DB->get_records('custom_program_courses', [
            'programid' => $program->id,
            'year' => $year,
        ]);
        if (empty($links)) {
            return;
        }

        $courseids = self::get_canonical_course_ids($links);
        if (empty($courseids)) {
            return;
        }

        require_once($CFG->libdir . '/enrollib.php');
        $manualenrol = enrol_get_plugin('manual');
        if (!$manualenrol) {
            return;
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id', IGNORE_MISSING);
        $roleid = $studentrole ? (int)$studentrole->id : 5;
        $now = time();

        $prevnoemailever = $CFG->noemailever ?? false;
        $CFG->noemailever = true;

        try {
            $transaction = $DB->start_delegated_transaction();

            // Re-load token inside transaction for safe idempotency.
            $token = $DB->get_record('custom_reg_tokens', ['id' => $token->id], '*', MUST_EXIST);
            if (!self::is_token_available($token)) {
                $transaction->allow_commit();
                return;
            }

            foreach ($courseids as $courseid) {
                $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
                if (!$course) {
                    continue;
                }

                $context = \context_course::instance($courseid);
                if (!is_enrolled($context, $user->id)) {
                    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MISSING);
                    if (!$instance) {
                        $instanceid = $manualenrol->add_instance($course);
                        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
                    }
                    $manualenrol->enrol_user($instance, $user->id, $roleid);
                }
            }

            $programenrol = $DB->get_record('custom_student_programs', [
                'userid' => $user->id,
                'programid' => $program->id,
            ], '*', IGNORE_MISSING);

            if (!$programenrol) {
                $DB->insert_record('custom_student_programs', (object)[
                    'userid' => $user->id,
                    'programid' => $program->id,
                    'yearofstudy' => $year,
                    'status' => 'active',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            } else if ((int)$programenrol->yearofstudy !== $year) {
                $programenrol->yearofstudy = $year;
                $programenrol->timemodified = $now;
                $DB->update_record('custom_student_programs', $programenrol);
            }

            $token->status = 'claimed';
            $token->userid = $user->id;
            $token->timeclaimed = $now;
            $DB->update_record('custom_reg_tokens', $token);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            debugging(
                'local_profileenrol: failed to complete enrolment for uid=' . $user->id . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        } finally {
            $CFG->noemailever = $prevnoemailever;
        }
    }

    /**
     * Token is considered available only while unused.
     */
    private static function is_token_available(\stdClass $token): bool {
        return strtolower((string)$token->status) === 'unused';
    }

    /**
     * Select one canonical course per normalized module code.
     * Prefers -SHARED when both legacy and shared copies are linked.
     *
     * @param \stdClass[] $links
     * @return int[]
     */
    private static function get_canonical_course_ids(array $links): array {
        global $DB;

        $candidateids = [];
        foreach ($links as $link) {
            $candidateids[] = (int)$link->courseid;
        }
        $candidateids = array_values(array_unique($candidateids));
        if (empty($candidateids)) {
            return [];
        }

        $courses = $DB->get_records_list('course', 'id', $candidateids, '', 'id,shortname');
        $selected = [];

        foreach ($courses as $course) {
            $basecode = self::normalise_course_code((string)$course->shortname);
            if (!isset($selected[$basecode])) {
                $selected[$basecode] = $course;
                continue;
            }

            $existing = $selected[$basecode];
            if (self::is_shared_course((string)$course->shortname) && !self::is_shared_course((string)$existing->shortname)) {
                $selected[$basecode] = $course;
            }
        }

        return array_values(array_map(static function($course): int {
            return (int)$course->id;
        }, $selected));
    }

    private static function normalise_course_code(string $shortname): string {
        return trim((string)preg_replace('/-(?:SHARED|BIT|BCS)$/i', '', $shortname));
    }

    private static function is_shared_course(string $shortname): bool {
        return (bool)preg_match('/-SHARED$/i', $shortname);
    }
}
