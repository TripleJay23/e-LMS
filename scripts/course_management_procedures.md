# Course Management Procedures

## Overview

The e-LMS uses two parallel systems for organizing courses:

1. **Moodle Course Categories** - Visual hierarchy visible to users
2. **Custom Database Tables** - Links courses to programs for enrollment and reporting

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

- `mdl_custom_programs` - Program definitions (BIT, BCS, etc.)
- `mdl_custom_program_courses` - Links courses to programs
- `mdl_custom_student_programs` - Student enrollment in programs

---

## Adding New Courses

### ✅ CORRECT Way (Automated)

**Always use the course creation script:**

```bash
php scripts/create_all_courses.php
```

This script:

- Creates courses in correct categories
- **Automatically** adds records to `mdl_custom_program_courses`
- Ensures synchronization from the start

### ⚠️ Manual Creation (Discouraged)

If you MUST create a course manually via Moodle UI:

1. Create the course in the appropriate category (`BIT_Y1_S1`, etc.)
2. **IMMEDIATELY** run the rebuild script:
   ```bash
   php scripts/rebuild_program_course_links.php
   ```

---

## Moving Courses Between Programs

### Example: Moving "Database Systems" from BCS to BIT

**NEVER** just drag-and-drop in Moodle UI!

**Instead:**

1. Move the course via Moodle UI to the new category
2. **IMMEDIATELY** run rebuild script:
   ```bash
   php scripts/rebuild_program_course_links.php
   ```
3. Verify the change:
   ```bash
   php scripts/verify_program_links.php
   ```

The rebuild script will:

- Detect the new category
- Delete old program link
- Create new program link

---

## Shared Courses

Shared courses (e.g., Mathematics, Programming Fundamentals) are created as **separate instances**:

- `CS101-BIT` - BIT version of the course
- `CS101-BCS` - BCS version of the same course

### Why Separate Instances?

- Each program can have different schedules
- Different lecturers for each program
- Different student cohorts
- Independent grading and assessments

### Creating Shared Courses

The `create_all_courses.php` script handles this automatically by:

- Reading from `modules_categorized.json`
- Creating both BIT and BCS versions
- Linking each to its respective program

---

## Deprecating/Archiving Courses

### When to Archive

- Course is no longer offered
- Course has been replaced by a new version
- Old semester courses that are complete

### How to Archive

1. Run the archive script:
   ```bash
   php scripts/archive_deprecated_courses.php
   ```
2. This will:
   - Move courses to hidden "Archive" category
   - Hide courses from all users
   - Preserve data for historical records

3. Clean up database:
   ```bash
   php scripts/cleanup_program_courses_table.php
   ```

### Naming Convention for Deprecated Courses

Before archiving, rename courses to indicate they're deprecated:

- Add `-OLD` suffix: `CS101-OLD`
- Add `(DEPRECATED)` to fullname: `Programming I (DEPRECATED)`

Then the archive script will automatically detect and process them.

---

## Deleting Courses (Permanent)

**⚠️ WARNING**: Deletion is permanent and cannot be undone!

### Before Deleting

1. Ensure all student data is exported
2. Archive the course first (see above)
3. Keep archived for at least one academic year

### To Delete

1. Move to Archive category (if not already)
2. Use Moodle's built-in deletion:
   - Site administration → Courses → Manage courses and categories
   - Select course → Delete

3. Clean up database:
   ```bash
   php scripts/cleanup_program_courses_table.php
   ```

---

## Routine Maintenance

### Weekly Verification (Recommended)

Run the verification script to catch issues early:

```bash
php scripts/verify_program_links.php
```

If issues are found, it will tell you which scripts to run.

### Monthly Full Audit

Run complete audit for detailed health check:

```bash
php scripts/audit_course_program_links.php
```

Review the report and follow recommended actions.

### After Major Changes

After batch operations (imports, migrations, reorganizing), always:

1. Run rebuild:

   ```bash
   php scripts/rebuild_program_course_links.php
   ```

2. Verify:
   ```bash
   php scripts/verify_program_links.php
   ```

---

## Troubleshooting

### Issue: Course shows wrong program in notifications

**Cause**: Category and database are out of sync

**Fix**:

```bash
php scripts/rebuild_program_course_links.php
```

### Issue: HOD can't see all courses

**Cause**: Courses not linked in `mdl_custom_program_courses`

**Fix**:

```bash
php scripts/audit_course_program_links.php  # Identify orphaned courses
php scripts/rebuild_program_course_links.php  # Fix links
```

### Issue: Ghost records / Database errors

**Cause**: Links to deleted/archived courses

**Fix**:

```bash
php scripts/cleanup_program_courses_table.php
```

### Issue: Duplicate courses in lists

**Cause**: Duplicate records in database

**Fix**:

```bash
php scripts/cleanup_program_courses_table.php  # Removes duplicates
```

---

## Script Reference

| Script                              | Purpose                     | Safe to Run?       |
| ----------------------------------- | --------------------------- | ------------------ |
| `audit_course_program_links.php`    | Check system health         | ✅ Yes (Read-only) |
| `verify_program_links.php`          | Quick health check          | ✅ Yes (Read-only) |
| `archive_deprecated_courses.php`    | Move old courses to Archive | ⚠️ Test first      |
| `cleanup_program_courses_table.php` | Remove invalid DB records   | ⚠️ Backup first    |
| `rebuild_program_course_links.php`  | Sync categories ↔ database  | ⚠️ Backup first    |
| `create_all_courses.php`            | Create course structure     | ⚠️ Test first      |

### Before Running Destructive Scripts

Always backup the database:

```bash
# PostgreSQL backup
pg_dump -h localhost -U moodleuser -d moodle > backup_$(date +%Y%m%d).sql

# Or use Moodle's backup
php admin/cli/backup.php
```

---

## Best Practices

### ✅ DO

- Use automated scripts for course creation
- Run verification regularly
- Keep categories organized with proper idnumbers
- Archive old courses instead of deleting
- Test changes on staging first
- Keep database backups

### ❌ DON'T

- Create courses manually without rebuilding links
- Move courses without running rebuild script
- Delete courses without archiving first
- Ignore warnings from verification script
- Make bulk changes without backups
- Modify `mdl_custom_program_courses` directly in SQL

---

## Emergency Recovery

If the system becomes completely out of sync:

1. **Backup database immediately**

   ```bash
   pg_dump [connection details] > emergency_backup.sql
   ```

2. **Run full audit**

   ```bash
   php scripts/audit_course_program_links.php > audit_report.txt
   ```

3. **Clean up**

   ```bash
   php scripts/archive_deprecated_courses.php
   php scripts/cleanup_program_courses_table.php
   ```

4. **Rebuild everything**

   ```bash
   php scripts/rebuild_program_course_links.php
   ```

5. **Verify recovery**

   ```bash
   php scripts/verify_program_links.php
   ```

6. **Test critical functions**
   - Check HOD views
   - Check student enrollments
   - Check notifications
   - Check course listings

---

## Support

For issues not covered here:

1. Run audit script for detailed diagnostics
2. Check logs in `moodle/admin/cli/logs/`
3. Review error messages carefully
4. Test fixes on a copy first

---

_Last Updated: 2026-02-14_  
_Moodle Version: 4.x_  
_Custom Tables Version: 1.0_
