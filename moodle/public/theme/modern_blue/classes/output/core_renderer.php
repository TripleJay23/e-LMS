<?php

namespace theme_modern_blue\output;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_completion\progress;
use html_writer;
use moodle_url;

class core_renderer extends \theme_boost\output\core_renderer
{
    public function body_attributes($additionalclasses = [])
    {
        if (isloggedin() && !isguestuser()) {
            $additionalclasses[] = 'modern-blue-authenticated-user';
        }

        return parent::body_attributes($additionalclasses);
    }

    public function full_header()
    {
        if ($this->is_dashboard_page()) {
            return $this->render_student_header(
                'Dashboard',
                '',
                'modern-blue-page-header-dashboard'
            );
        }

        if ($this->is_mycourses_page()) {
            return $this->render_student_header(
                get_string('mycourses'),
                'Welcome back! Continue your learning journey',
                'modern-blue-page-header-mycourses'
            );
        }

        if ($this->is_course_page()) {
            return $this->render_course_header();
        }

        if ($this->is_profile_page()) {
            return $this->render_profile_header();
        }

        return parent::full_header();
    }

    public function course_content_footer($onlyifnotcalledbefore = false)
    {
        $footer = parent::course_content_footer($onlyifnotcalledbefore);
        if (!$this->is_course_page()) {
            return $footer;
        }

        return $footer . $this->render_course_progress_footer();
    }

    protected function is_dashboard_page(): bool
    {
        return $this->current_path() === '/my/index.php';
    }

    protected function is_mycourses_page(): bool
    {
        return $this->current_path() === '/my/courses.php';
    }

    protected function is_course_page(): bool
    {
        return str_starts_with((string) $this->page->pagetype, 'course-view-');
    }

    protected function is_profile_page(): bool
    {
        return $this->current_path() === '/user/profile.php';
    }

    protected function current_path(): string
    {
        $url = $this->page->url->out(false);
        return (string) parse_url($url, PHP_URL_PATH);
    }

    protected function render_student_header(string $title, string $subtitle, string $modifier): string
    {
        $content = html_writer::start_div('modern-blue-page-header ' . $modifier);
        $content .= html_writer::start_div('modern-blue-page-header__copy');
        $content .= html_writer::tag('h1', $title, ['class' => 'modern-blue-page-header__title']);
        $content .= html_writer::tag('p', $subtitle, ['class' => 'modern-blue-page-header__subtitle']);
        $content .= html_writer::end_div();
        $content .= $this->render_header_actions();
        $content .= html_writer::end_div();

        return html_writer::tag('header', $content, [
            'id' => 'page-header',
            'class' => 'header-maxwidth d-print-none modern-blue-shell-header'
        ]);
    }

    protected function render_course_header(): string
    {
        $course = $this->page->course;
        $progressvalue = progress::get_course_progress_percentage($course);
        $status = ($progressvalue === null || $progressvalue < 100) ? 'In Progress' : 'Completed';

        $meta = html_writer::tag('span', s($course->shortname), ['class' => 'modern-blue-course-header__meta-item']);

        $content = html_writer::start_div('modern-blue-page-header modern-blue-page-header-course');
        $content .= html_writer::start_div('modern-blue-page-header__copy');
        $content .= html_writer::tag('h1', format_string($course->fullname, true, [
            'context' => context_course::instance($course->id),
            'escape' => false
        ]), ['class' => 'modern-blue-page-header__title']);
        $content .= html_writer::tag('div', $meta, ['class' => 'modern-blue-course-header__meta']);
        $content .= html_writer::end_div();
        $content .= $this->render_header_actions();
        $content .= html_writer::end_div();

        return html_writer::tag('header', $content, [
            'id' => 'page-header',
            'class' => 'header-maxwidth d-print-none modern-blue-shell-header modern-blue-shell-header-course'
        ]);
    }

    protected function render_profile_header(): string
    {
        global $DB, $CFG;

        $userid = $this->page->context->instanceid ?? 0;
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $breadcrumbs = html_writer::div($this->navbar(), '', ['id' => 'page-navbar']);
        $profileactions = html_writer::start_div('modern-blue-profile-hero__actions');

        if (!empty($CFG->messaging)) {
            $profileactions .= html_writer::link(
                new moodle_url('/message/index.php', ['id' => $user->id]),
                'Message',
                ['class' => 'btn modern-blue-button modern-blue-button--ghost']
            );
        }

        $profileactions .= html_writer::link(
            new moodle_url('/user/edit.php', ['id' => $user->id, 'returnto' => 'profile']),
            'Edit profile',
            ['class' => 'btn modern-blue-button modern-blue-button--outline']
        );
        $profileactions .= html_writer::end_div();

        $rolebadge = $this->get_user_role_label($user->id);

        $locationparts = [];
        if (!empty($user->city)) {
            $locationparts[] = $user->city;
        }
        if (!empty($user->country)) {
            $locationparts[] = get_string($user->country, 'countries');
        }
        $location = !empty($locationparts) ? implode(', ', $locationparts) : '';

        $identity = html_writer::start_div('modern-blue-profile-hero__identity');
        $identity .= html_writer::tag('h1', fullname($user), ['class' => 'modern-blue-profile-hero__name']);
        $identity .= html_writer::start_div('modern-blue-profile-hero__meta');
        $identity .= html_writer::tag('span', $rolebadge, ['class' => 'modern-blue-profile-hero__badge']);
        if (!empty($location)) {
            $identity .= html_writer::tag('span', s($location), ['class' => 'modern-blue-profile-hero__location']);
        }
        $identity .= html_writer::end_div();
        $identity .= html_writer::end_div();

        $hero = html_writer::start_div('modern-blue-profile-hero');
        $hero .= html_writer::div('', 'modern-blue-profile-hero__cover');
        $hero .= html_writer::start_div('modern-blue-profile-hero__body');
        $hero .= html_writer::div(
            $this->user_picture($user, ['size' => 120, 'class' => 'modern-blue-profile-hero__avatar-image']),
            'modern-blue-profile-hero__avatar'
        );
        $hero .= $identity;
        $hero .= $profileactions;
        $hero .= html_writer::end_div();
        $hero .= html_writer::end_div();

        $toprow = html_writer::start_div('modern-blue-profile-header__top');
        $toprow .= $breadcrumbs;
        $toprow .= $this->render_header_actions();
        $toprow .= html_writer::end_div();

        $content = html_writer::div($toprow . $hero, 'modern-blue-page-header modern-blue-page-header-profile');

        return html_writer::tag('header', $content, [
            'id' => 'page-header',
            'class' => 'header-maxwidth d-print-none modern-blue-shell-header modern-blue-shell-header-profile'
        ]);
    }

    protected function render_header_actions(): string
    {
        $actions = $this->page->get_header_actions();
        if (empty($actions)) {
            return '';
        }

        $content = '';
        foreach ($actions as $action) {
            $content .= html_writer::div($action, 'header-action');
        }

        return html_writer::div(
            $content,
            'header-actions-container modern-blue-header-actions',
            ['data-region' => 'header-actions-container']
        );
    }

    protected function render_course_progress_footer(): string
    {
        $course = $this->page->course;
        $progressvalue = progress::get_course_progress_percentage($course);
        if ($progressvalue === null) {
            // Keep native behaviour when completion tracking is not configured.
            return '';
        }

        $progresswidth = $progressvalue === null ? 0 : max(0, min(100, (int) round($progressvalue)));
        $progresslabel = $progresswidth . '% complete';
        $supportingcopy = $progresswidth >= 100
            ? 'You have completed every tracked activity in this course.'
            : 'Keep progressing through activities to move this course toward completion.';

        $meter = html_writer::start_div('modern-blue-course-progress__bar', [
            'role' => 'progressbar',
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
            'aria-valuenow' => (string) $progresswidth,
            'aria-label' => 'Course completion progress'
        ]);
        $meter .= html_writer::div('', 'modern-blue-course-progress__bar-fill', [
            'style' => 'width: ' . $progresswidth . '%;'
        ]);
        $meter .= html_writer::end_div();

        $content = html_writer::start_div('modern-blue-course-progress');
        $content .= html_writer::start_div('modern-blue-course-progress__copy');
        $content .= html_writer::tag('p', 'Course progress', ['class' => 'modern-blue-course-progress__eyebrow']);
        $content .= html_writer::tag('h2', $progresslabel, ['class' => 'modern-blue-course-progress__title']);
        $content .= html_writer::tag('p', $supportingcopy, ['class' => 'modern-blue-course-progress__subtitle']);
        $content .= html_writer::end_div();
        $content .= html_writer::start_div('modern-blue-course-progress__meter');
        $content .= $meter;
        $content .= html_writer::tag('span', $progresslabel, ['class' => 'modern-blue-course-progress__value']);
        $content .= html_writer::end_div();
        $content .= html_writer::end_div();

        return html_writer::div($content, 'modern-blue-course-progress-wrap');
    }

    protected function get_preferred_name(): string
    {
        global $USER;

        $name = trim($USER->firstname ?? '');
        if ($name === '') {
            $name = trim(fullname($USER));
        }

        return strtoupper($name);
    }

    protected function get_user_role_label(int $userid): string
    {
        global $DB;

        // Check system-level roles first (admin, manager).
        $systemctx = \context_system::instance();
        $roles = get_user_roles($systemctx, $userid, false);
        foreach ($roles as $role) {
            switch ($role->shortname) {
                case 'manager':
                    return 'Manager';
            }
        }

        if (is_siteadmin($userid)) {
            return 'Administrator';
        }

        // Check for category-level manager (HOD).
        $catmanager = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra
             JOIN {role} r ON r.id = ra.roleid
             JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.userid = ? AND r.shortname = 'manager' AND ctx.contextlevel = 40",
            [$userid]
        );
        if ($catmanager) {
            return 'Manager';
        }

        // Check for editing teacher role in any course.
        $isteacher = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra
             JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = ? AND r.shortname = 'editingteacher'",
            [$userid]
        );
        if ($isteacher) {
            return 'Lecturer';
        }

        return 'Student';
    }
}
