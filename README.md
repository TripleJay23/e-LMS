# e-LMS - e-Learning Management System

## Overview

This is a Moodle-based e-Learning Management System customized for the Faculty of Informatics, supporting BIT (Bachelor in Information Technology) and BCS (Bachelor in Computer Science) programs.

**System Status:** ✅ Fully Operational and Optimized

---

## Current System State

### Users

- **Total Users:** 39
  - Students: 28
  - Teachers: 7 (editingteacher)
  - Managers: 1 (hod_informatics - system-wide access)
  - Unassigned: 3

### Courses

- **Total Active Courses:** 48
  - BIT Program courses
  - BCS Program courses
  - Shared modules (organized by Year/Semester)
- **HOD Access:** 100% (all courses visible)

### System Health

- ✅ Caches purged and optimized
- ✅ Logs cleared
- ✅ No deprecated courses
- ✅ All category structures organized
- ✅ Database fully synchronized

---

## Features

### For Facilitators

- ✅ Upload lecture materials (PDFs, videos)
- ✅ Create quizzes and assessments with automatic grading
- ✅ Grade student submissions
- ✅ Track student progress

### For Students

- ✅ Self-registration via email
- ✅ Access course materials
- ✅ View and download PDFs
- ✅ Watch video lectures
- ✅ Take quizzes and view results
- ✅ Track progress and grades

### For HOD (Head of Department)

- ✅ View all courses across programs (BIT & BCS)
- ✅ Manage course content and structure
- ✅ Oversee teacher assignments
- ✅ Monitor student enrollment

---

## Programs Supported

The system currently supports 5 programs from the Faculty of Informatics:

| Acronym   | Program Name                                     | Level       | Duration    | Status    |
| --------- | ------------------------------------------------ | ----------- | ----------- | --------- |
| **BIT**   | Bachelor Degree in Information Technology        | Bachelor    | 6 semesters | ✅ Active |
| **BCS**   | Bachelor Degree in Computer Science              | Bachelor    | 6 semesters | ✅ Active |
| **DIT**   | Ordinary Diploma in Information Technology       | Diploma     | 4 semesters | Setup     |
| **DCS**   | Ordinary Diploma in Computer Science             | Diploma     | 4 semesters | Setup     |
| **BTCIT** | Basic Technician Certificate in Computing and IT | Certificate | 2 semesters | Setup     |

---

## Technology Stack

- **Platform**: Moodle 4.x
- **Database**: PostgreSQL
- **Web Server**: Nginx
- **PHP**: 8.x
- **Operating System**: Windows

---

## Course Organization Structure

### Category Hierarchy

```
BIT (Bachelor in Information Technology)
├── Year 1
│   ├── Semester 1
│   └── Semester 2
├── Year 2
│   ├── Semester 1
│   └── Semester 2
└── Year 3
    ├── Semester 1
    └── Semester 2

BCS (Bachelor in Computer Science)
└── [Same structure as BIT]

Shared Modules
├── Year 1
│   ├── Semester 1
│   └── Semester 2
├── Year 2
│   ├── Semester 1
│   └── Semester 2
└── Year 3
    ├── Semester 1
    └── Semester 2
```

---

## Directory Structure

```
e-LMS/
├── moodle/                     # Moodle installation
│   ├── config.php              # Moodle configuration
│   └── ...
├── scripts/                    # Management and utility scripts
│   ├── System Maintenance
│   │   ├── system_cleanup.php              # Cache purge, cron, log cleanup
│   │   └── unenrol_deprecated_courses.php  # Remove deprecated enrollments
│   ├── User Management
│   │   └── export_users.php                # Export users to CSV
│   ├── HOD Management
│   │   ├── analyze_hod_structure.php       # Analyze HOD roles
│   │   ├── verify_hod_access.php           # Test HOD course access
│   │   ├── fix_hod_permissions.php         # Grant system-wide access
│   │   └── remove_redundant_hods.php       # Remove duplicate HODs
│   ├── Course Organization
│   │   ├── analyze_shared_categories.php   # Analyze category structure
│   │   ├── reorganize_shared_courses.php   # Organize courses
│   │   └── verify_shared_structure.php     # Verify course distribution
│   ├── Course-Program Links
│   │   ├── audit_course_program_links.php      # Detailed system audit
│   │   ├── verify_program_links.php            # Quick health check
│   │   ├── rebuild_program_course_links.php    # Sync categories ↔ database
│   │   └── cleanup_program_courses_table.php   # Remove invalid records
│   ├── Course Creation
│   │   ├── create_all_courses.php          # Create full course structure
│   │   └── create_categories.php           # Create category hierarchy
│   └── Documentation
│       └── course_management_procedures.md # Complete procedures guide
├── logs/                       # Server logs
│   └── archive/                # Archived logs
├── .env                        # Environment variables
├── e-LMS_Users_Export.csv      # Latest user export
├── e-LMS_Users_Reorganized_1.xlsx # User database
└── README.md                   # This file
```

---

## Database Schema

### Custom Tables

| Table                         | Purpose                                         |
| ----------------------------- | ----------------------------------------------- |
| `mdl_custom_faculties`        | Stores faculties (e.g., Faculty of Informatics) |
| `mdl_custom_departments`      | Stores departments within faculties             |
| `mdl_custom_programs`         | Stores academic programs (BIT, BCS, etc.)       |
| `mdl_custom_program_courses`  | Links courses to programs                       |
| `mdl_custom_student_programs` | Tracks student program enrollment               |

All custom tables use the `mdl_custom_` prefix to avoid conflicts with Moodle core.

**CRITICAL:** Categories and database tables must stay synchronized! See `scripts/course_management_procedures.md` for details.

---

## Starting the Server

```bash
# Navigate to project directory
cd "c:\Users\jtrip\Desktop\Group 07\e-LMS"

# Start Nginx and PHP-FPM
./start_server.bat

# Access system
# URL: http://localhost:8080
```

---

## Routine Maintenance

### Weekly Tasks

```bash
# Verify system health
php scripts/verify_program_links.php
```

### Monthly Tasks

```bash
# Full system audit
php scripts/audit_course_program_links.php

# System cleanup
php moodle/admin/cli/purge_caches.php
php moodle/admin/cli/cron.php

# Export updated user list
php scripts/export_users.php
```

### After Major Changes

```bash
# Rebuild program-course links
php scripts/rebuild_program_course_links.php

# Verify the rebuild
php scripts/verify_program_links.php
```

---

## Course Management

### Adding New Courses

**Recommended:** Use the automated script

```bash
php scripts/create_all_courses.php
```

**Manual Creation:**

1. Create course via Moodle UI in appropriate category
2. **IMMEDIATELY** run: `php scripts/rebuild_program_course_links.php`

### Moving Courses

1. Move via Moodle UI
2. **IMMEDIATELY** run: `php scripts/rebuild_program_course_links.php`
3. Verify: `php scripts/verify_program_links.php`

**See `scripts/course_management_procedures.md` for complete guidelines.**

---

## User Roles

| Role        | Username        | Moodle Role      | Access Level                                      |
| ----------- | --------------- | ---------------- | ------------------------------------------------- |
| Admin       | admin           | `administrator`  | Full system access (technical/infrastructure)     |
| HOD         | hod_informatics | `manager`        | All courses, both BIT & BCS (academic/content)    |
| Facilitator | [various]       | `editingteacher` | Upload content, create quizzes, grade assignments |
| Student     | [various]       | `student`        | View content, take quizzes, submit assignments    |

**Note:** There is **one unified HOD** (`hod_informatics`) with system-wide access to both programs.

---

## Quick Start

### 1. Access the System

Visit: http://localhost:8080

### 2. Login Credentials

- **Admin:** From `.env` file
- **HOD:** `hod_informatics` / [password]
- **Students:** Self-registration enabled

### 3. Create a Test Student Account

1. Click "Log in" → "Create new account"
2. Fill in the registration form
3. Confirm your email
4. Log in

### 4. Add Content (Facilitator)

1. Enter the course
2. Turn editing on
3. Add File resource → Upload PDF
4. Add URL resource → Add video link
5. Add Quiz → Create questions

---

## Troubleshooting

### Database Connection Issues

- Check `.env` file for correct database credentials
- Verify PostgreSQL is running: `psql -U moodleuser -d moodle`

### HOD Can't See All Courses

```bash
# Diagnose the issue
php scripts/verify_hod_access.php

# Fix permissions
php scripts/fix_hod_permissions.php

# Purge caches
php moodle/admin/cli/purge_caches.php
```

### Course Shows Wrong Program

```bash
# Rebuild program links
php scripts/rebuild_program_course_links.php
```

### System Performance Issues

```bash
# Run system cleanup
php scripts/system_cleanup.php
```

---

## System Optimization History

### February 14, 2026 - Major Optimization

- ✅ Purged all caches and optimized performance
- ✅ Exported 45 users to CSV for tracking
- ✅ Consolidated 3 HODs → 1 unified HOD
- ✅ Fixed HOD permissions (48% → 100% course access)
- ✅ Reorganized 25 shared courses into Year/Semester structure
- ✅ Deleted unnecessary JSON metadata files
- ✅ Cleaned up deprecated course enrollments

**Result:** System running at peak performance with clean organization.

---

## Documentation

- **Implementation Plan** - See artifacts directory
- **Course Management Procedures** - `scripts/course_management_procedures.md`
- **System Walkthrough** - See artifacts directory
- [Official Moodle Documentation](https://docs.moodle.org)

---

## Support

For technical support or questions:

- IT Support Team
- Moodle Community Forums: https://moodle.org/forums

---

## License

This system is based on Moodle, which is licensed under GPL v3.

---

**Last Updated**: February 14, 2026  
**Version**: 2.0 (Optimized)  
**System Status**: ✅ Fully Operational
