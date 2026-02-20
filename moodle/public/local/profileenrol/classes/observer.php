<?php

namespace local_profileenrol;

defined('MOODLE_INTERNAL') || die();

class observer
{

   /**
    * Handle user_created event.
    * At this point: user exists with confirmed=0, profile fields are saved.
    * We do NOT auto-confirm — Moodle sends the confirmation email instead.
    * We only validate the token here (no DB changes).
    */
   public static function user_created(\core\event\user_created $event)
   {
      // Intentionally empty — enrolment happens on first login after confirmation.
      // The confirmation email is sent by auth_email right after this event.
   }

   /**
    * Handle user_updated event (e.g. admin manually confirms a user).
    */
   public static function user_updated(\core\event\user_updated $event)
   {
      self::enrol_if_ready($event->objectid);
   }

   /**
    * Handle user_loggedin event.
    * This fires when the student clicks the confirmation link and is auto-logged in.
    * confirm.php: user_confirm() sets confirmed=1, then complete_user_login() fires this.
    */
   public static function user_loggedin(\core\event\user_loggedin $event)
   {
      self::enrol_if_ready($event->objectid);
   }

   /**
    * Core logic: if user is confirmed and has an unclaimed reg token → enrol + claim.
    * Runs at most once per user (token is claimed after first run).
    */
   private static function enrol_if_ready($userid)
   {
      global $DB, $CFG;

      $user = $DB->get_record('user', ['id' => $userid]);
      if (!$user || empty($user->confirmed)) return;

      // Load custom profile fields
      require_once($CFG->dirroot . '/user/profile/lib.php');
      profile_load_data($user);

      $program_acronym = $user->profile_field_program_study ?? null;
      $year            = (int)($user->profile_field_year_of_study ?? 0);
      $reg_number      = trim($user->profile_field_reg_number ?? '');

      if (!$program_acronym || !$year || !$reg_number) return;

      // ── Check for valid unclaimed registration token ────────────────────────
      $token = $DB->get_record('custom_reg_tokens', ['reg_number' => $reg_number]);
      if (!$token || $token->status === 'claimed') return; // already processed or invalid

      if (strtoupper($token->program) !== strtoupper($program_acronym)) return;
      if ((int)$token->year !== $year) return;

      // ── Claim the token ────────────────────────────────────────────────────
      $token->status      = 'claimed';
      $token->userid      = $user->id;
      $token->timeclaimed = time();
      $DB->update_record('custom_reg_tokens', $token);

      // ── Enrol in all courses (suppress per-course notification emails) ─────
      $program = $DB->get_record('custom_programs', ['acronym' => strtoupper($program_acronym)]);
      if (!$program) return;

      $links = $DB->get_records('custom_program_courses', [
         'programid' => $program->id,
         'year'      => $year,
      ]);
      if (!$links) return;

      require_once($CFG->dirroot . '/course/lib.php');
      require_once($CFG->libdir  . '/enrollib.php');

      $enrol  = enrol_get_plugin('manual');
      if (!$enrol) return;

      $student_role = $DB->get_record('role', ['shortname' => 'student']);
      $roleid       = $student_role ? $student_role->id : 5;

      // Suppress notification emails during bulk enrolment
      $prev_noemailever = $CFG->noemailever ?? false;
      $CFG->noemailever = true;

      foreach ($links as $link) {
         try {
            $course = $DB->get_record('course', ['id' => $link->courseid]);
            if (!$course) continue;

            $context = \context_course::instance($course->id);
            if (is_enrolled($context, $user->id)) continue;

            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if (!$instance) {
               $iid      = $enrol->add_instance($course);
               $instance = $DB->get_record('enrol', ['id' => $iid]);
            }

            $enrol->enrol_user($instance, $user->id, $roleid);
         } catch (\Throwable $e) {
            debugging('local_profileenrol: failed to enrol uid=' . $user->id .
               ' in course=' . $link->courseid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
         }
      }

      // Restore email setting
      $CFG->noemailever = $prev_noemailever;
   }
}
