# Course Management Procedures

## Overview

The e-LMS uses two parallel systems for organizing courses:

1. **Moodle Course Categories** — Visual hierarchy visible to users
2. **Custom Database Tables** — Links courses to programs for enrollment and reporting

**CRITICAL**: These systems must stay synchronized at all times!

---

## Course Organization Structure

### Category Hierarchy

```
BIT (idnumber: BIT)
├── Year 1 (idnumber: BIT_Y1)
│   ├── Semester 1 (idnumber: BIT_Y1_S1)
│   └── Semester 2 (idnumber: BIT_Y1_S2)
├── Year 2 (idnumber: BIT_Y2)
│   ├── Semester 1 (idnumber: BIT_Y2_S1)
│   └── Semester 2 (idnumber: BIT_Y2_S2)
└── Year 3 (idnumber: BIT_Y3)
    ├── Semester 1 (idnumber: BIT_Y3_S1)
    └── Semester 2 (idnumber: BIT_Y3_S2)

BCS (idnumber: BCS)
└── [Same structure as BIT]
```

### Database Tables

- `mdl_custom_programs` — Program definitions (BIT, BCS, etc.)
- `mdl_custom_program_courses` — Links courses to programs
- `mdl_custom_student_programs` — Student enrollment in programs

---

## Adding New Courses

### ✅ Recommended: Automated Script

```bash
php scripts/create_all_courses.php
```

This script:

- Creates courses in correct categories
- Automatically adds records to `mdl_custom_program_courses`
- Ensures synchronization from the start

### ⚠️ Manual Creation (Discouraged)

If you MUST create a course manually via Moodle UI:

1. Create the course in the appropriate category (`BIT_Y1_S1`, etc.)
2. Run cron to sync: `php scripts/run_cron.php`

---

## Shared Courses

Shared courses (e.g., Mathematics, Programming Fundamentals) are created as **separate instances**:

- `ITU 07101-BIT` — BIT version
- `ITU 07101-BCS` — BCS version

### Why Separate Instances?

- Each program can have different schedules
- Different lecturers for each program
- Different student cohorts
- Independent grading and assessments

### Creating Shared Courses

The `create_all_courses.php` script handles this automatically by:

- Reading from `modules_corrected.json`
- Creating both BIT and BCS versions
- Linking each to its respective program

---

## Course Images & Styling

### Updating Course Images

1. Place new images in the `images/` directory (JPG, PNG, GIF supported)
2. Run the template script:

```bash
php scripts/apply_full_course_template.php
```

This will:

- Upload images to each course (round-robin distribution)
- Generate styled summary HTML with academic metadata (year, semester, credits, type)
- Pull metadata from `modules_corrected.json`

---

## Deprecating/Archiving Courses

### Naming Convention

Before archiving, rename courses to indicate they're deprecated:

- Add `-OLD` suffix: `ITU 07101-OLD`
- Add `(DEPRECATED)` to fullname

### How to Archive

1. Hide the course via Moodle UI (Course settings → Visibility: Hide)
2. Move to an "Archive" category if desired

### Deleting Courses (Permanent)

**⚠️ WARNING**: Deletion is permanent and cannot be undone!

1. Ensure all student data is exported
2. Archive the course first
3. Use Moodle's built-in deletion:
   - Site administration → Courses → Manage courses and categories
   - Select course → Delete

---

## Best Practices

### ✅ DO

- Use automated scripts for course creation
- Keep categories organized with proper idnumbers
- Archive old courses instead of deleting
- Keep database backups
- Update course images regularly via `apply_full_course_template.php`

### ❌ DON'T

- Create courses manually without running cron after
- Delete courses without archiving first
- Modify `mdl_custom_program_courses` directly in SQL
- Make bulk changes without backups

---

## Emergency Recovery

If the system becomes out of sync:

1. **Backup database immediately**

   ```bash
   pg_dump -h localhost -U moodleuser -d moodle > emergency_backup.sql
   ```

2. **Purge caches**

   ```bash
   php moodle/admin/cli/purge_caches.php
   ```

3. **Run cron**

   ```bash
   php scripts/run_cron.php
   ```

4. **Test critical functions**
   - Check HOD views
   - Check student enrollments
   - Check course listings

---

## Script Reference

| Script                           | Purpose                      | Safe to Run?   |
| -------------------------------- | ---------------------------- | -------------- |
| `create_all_courses.php`         | Create full course structure | ⚠️ Test first  |
| `create_categories.php`          | Create category hierarchy    | ⚠️ Test first  |
| `apply_full_course_template.php` | Apply images & styling       | ✅ Re-runnable |
| `enrol_student.php`              | Enroll students              | ⚠️ Test first  |
| `enrol_hods_in_courses.php`      | Enroll HODs                  | ⚠️ Test first  |
| `cross_enroll_shared.php`        | Shared course enrollment     | ⚠️ Test first  |
| `run_cron.php`                   | Trigger Moodle cron          | ✅ Safe        |

### Before Running Destructive Scripts

Always backup the database:

```bash
pg_dump -h localhost -U moodleuser -d moodle > backup_$(date +%Y%m%d).sql
```

---

_Last Updated: 2026-02-21_  
_Moodle Version: 4.x_  
_Custom Tables Version: 1.0_
