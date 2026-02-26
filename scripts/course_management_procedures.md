# Course Management Procedures

## Overview

The e-LMS uses two synchronized structures:

1. Moodle course categories (visible hierarchy).
2. Custom DB tables (`mdl_custom_*`) for program relationships.

## Target Hierarchy

```
Faculty of Informatics (idnumber: FACULTY_INFORMATICS)
`-- Department of Informatics (idnumber: DEPT_INFORMATICS)
    |-- BCS (idnumber: BCS)
    |   |-- Year 1 (BCS_Y1)
    |   |   |-- Semester 1 (BCS_Y1_S1)
    |   |   `-- Semester 2 (BCS_Y1_S2)
    |   |-- Year 2 (BCS_Y2)
    |   `-- Year 3 (BCS_Y3)
    |-- BIT (idnumber: BIT)
    |   |-- Year 1 (BIT_Y1)
    |   |-- Year 2 (BIT_Y2)
    |   `-- Year 3 (BIT_Y3)
    `-- Shared Modules (idnumber: COMMON)
        |-- Year 1 (common_y1)
        |   |-- Semester 1 (common_y1_s1)
        |   `-- Semester 2 (common_y1_s2)
        |-- Year 2 (common_y2)
        `-- Year 3 (common_y3)
```

## Required Setup Order

1. `php scripts/create_categories.php`
2. `php scripts/setup_department_hierarchy.php`
3. `php scripts/create_all_courses.php`
4. `php scripts/create_lecturers.php`

## Shared Courses

Shared courses are centralized as one course instance per code:

- Example: `ITU 07101-SHARED`
- Category location: `COMMON -> common_yX_sY`
- Program links: both BIT and BCS in `mdl_custom_program_courses`

## Teacher Assignment Rule

`scripts/create_lecturers.php` is idempotent and enforces exactly one `editingteacher` per course to keep single-teacher course cards on the home page.

## Best Practices

- Use scripts, not manual bulk changes in UI.
- Keep `idnumber` values unchanged.
- Purge caches after hierarchy/theme/server changes:

```bash
php moodle/admin/cli/purge_caches.php
```

- Keep backups before destructive changes:

```bash
pg_dump -h localhost -U moodleuser -d moodle > backup.sql
```

## Script Reference

| Script                           | Purpose                                                |
| -------------------------------- | ------------------------------------------------------ |
| `create_categories.php`          | Ensures full Faculty -> Department -> Program hierarchy |
| `setup_department_hierarchy.php` | Aligns categories, custom tables, and HOD scope        |
| `create_all_courses.php`         | Creates centralized shared + program-specific courses   |
| `create_lecturers.php`           | Creates lecturers and enforces one teacher per course   |
| `apply_full_course_template.php` | Applies course images and summary styling              |
| `run_cron.php`                   | Runs Moodle cron                                       |

_Last Updated: 2026-02-26_

