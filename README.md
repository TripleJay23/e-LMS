# e-LMS - e-Learning Management System

## Overview

A Moodle-based e-Learning Management System customized for the Faculty of Informatics, supporting BIT (Bachelor in Information Technology) and BCS (Bachelor in Computer Science) programs.

**System Status:** ✅ Fully Operational  
**Last Updated:** March 10, 2026  
**Version:** 3.0

---

## Current System State

### Users

- **Students:** 6
- **Facilitators (Teachers):** 8
- **HOD (Manager):** 1 - `hod_informatics` (department-level access)
- **Admin:** 1

### Courses

- **Total Active Courses:** 48 (25 shared, 12 BIT-only, 11 BCS-only)
- **HOD Access:** 100% (all courses visible)

### Security

- ✅ Google reCAPTCHA v2 enabled on login, signup, and forgot-password pages

---

## Features

### For Facilitators

- Upload lecture materials (PDFs, videos)
- Create quizzes and assessments with automatic grading
- Grade student submissions
- Track student progress

### For Students

- Self-registration via email with CAPTCHA protection
- Access course materials, PDFs, and video lectures
- Take quizzes and view results
- Track progress and grades

### For HOD (Head of Department)

- View all courses across programs (BIT & BCS)
- Manage course content and structure
- Oversee teacher assignments
- Monitor student enrollment

---

## Programs Supported

| Acronym   | Program Name                                     | Level       | Duration    | Status    |
| --------- | ------------------------------------------------ | ----------- | ----------- | --------- |
| **BIT**   | Bachelor Degree in Information Technology        | Bachelor    | 6 semesters | ✅ Active |
| **BCS**   | Bachelor Degree in Computer Science              | Bachelor    | 6 semesters | ✅ Active |
| **DIT**   | Ordinary Diploma in Information Technology       | Diploma     | 4 semesters | Planned   |
| **DCS**   | Ordinary Diploma in Computer Science             | Diploma     | 4 semesters | Planned   |
| **BTCIT** | Basic Technician Certificate in Computing and IT | Certificate | 2 semesters | Planned   |

---

## Technology Stack

- **Platform**: Moodle 5.1.3 (Build: 20260216)
- **Database**: PostgreSQL
- **Web Server**: Nginx
- **PHP**: 8.x
- **Operating System**: Windows

---

## Course Organization Structure

```
Faculty of Informatics (FACULTY_INFORMATICS)
`-- Department of Informatics (DEPT_INFORMATICS)
    |-- BCS
    |   |-- Year 1 -> Semester 1/2
    |   |-- Year 2 -> Semester 1/2
    |   `-- Year 3 -> Semester 1/2
    |-- BIT
    |   |-- Year 1 -> Semester 1/2
    |   |-- Year 2 -> Semester 1/2
    |   `-- Year 3 -> Semester 1/2
    `-- Shared Modules (COMMON)
        |-- Year 1 -> Semester 1/2
        |-- Year 2 -> Semester 1/2
        `-- Year 3 -> Semester 1/2
```

---

## Directory Structure

```
e-LMS/
├── moodle/                         # Moodle core (not tracked; install separately)
│   ├── config.php                  # Moodle configuration
│   ├── public/                     # Web root (Nginx document root)
│   └── ...
├── scripts/                        # Management and utility scripts
│   ├── Setup & Configuration
│   │   ├── run_setup.php                   # Master setup runner
│   │   ├── setup_moodle.php                # Core Moodle configuration
│   │   ├── setup_custom_profile_fields.php # Profile fields setup
│   │   ├── setup_department_hierarchy.php   # Department structure
│   │   ├── setup_reg_system.php            # Registration system
│   │   ├── set_recaptcha.php               # reCAPTCHA configuration
│   │   ├── database_setup.sql              # DB schema
│   │   └── seed_data.sql                   # Initial data
│   ├── User Management
│   │   ├── create_students.php             # Create student accounts
│   │   ├── create_lecturers.php            # Create facilitator accounts
│   │   └── create_hod.php                  # Create HOD account
│   ├── Course Management
│   │   ├── create_all_courses.php          # Create full course structure
│   │   ├── create_categories.php           # Create category hierarchy
│   │   ├── apply_full_course_template.php  # Apply images & styling
│   │   ├── cleanup_stale_courses.php       # Remove stale shared duplicates
│   │   └── normalize_shared_course_links.php # Canonicalize shared courses
│   ├── Enrollment
│   │   ├── enrol_student.php               # Enroll students
│   │   ├── enrol_hods_in_courses.php       # Enroll HODs
│   │   ├── cross_enroll_shared.php         # Legacy: cross-enroll -BIT/-BCS
│   │   └── assign_hod_role.php             # Assign HOD role
│   ├── Utilities
│   │   ├── generate_reg_tokens.php         # Generate registration tokens
│   │   └── run_cron.php                    # Trigger Moodle cron
│   └── Reference Data
│       ├── modules_corrected.json          # Course metadata
│       └── course_management_procedures.md # Procedures guide
├── images/                         # Course banner images (31 images)
├── logs/                           # Server logs
├── .env                            # Environment variables
├── .env.example                    # Environment template
├── nginx.conf                      # Nginx configuration
├── start_server.bat                # Start Nginx + PHP-FPM
└── README.md                       # This file
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

---

## Getting Started

Moodle core is required in `moodle/` (not tracked in git).

### 1. Start the Server

```bash
cd "c:\Users\jtrip\Desktop\Group 07\e-LMS"
./start_server.bat
```

Access the system at: **http://localhost:8081**

### 2. Login Credentials

- **Admin:** See `.env` file
- **HOD:** `hod_informatics` / [password]
- **Students:** Self-registration enabled (with reCAPTCHA)

### 3. Create a Student Account

1. Go to http://localhost:8081/login/signup.php
2. Fill in the registration form
3. Complete the reCAPTCHA challenge
4. Confirm your email
5. Log in

### 4. Add Content (Facilitator)

1. Enter the course
2. Turn editing on
3. Add File resource → Upload PDF
4. Add URL resource → Add video link
5. Add Quiz → Create questions

---

## User Roles

| Role        | Username        | Moodle Role      | Access Level                                      |
| ----------- | --------------- | ---------------- | ------------------------------------------------- |
| Admin       | admin           | `administrator`  | Full system access (technical/infrastructure)     |
| HOD         | hod_informatics | `manager`        | All courses, both BIT & BCS (academic/content)    |
| Facilitator | [various]       | `editingteacher` | Upload content, create quizzes, grade assignments |
| Student     | [various]       | `student`        | View content, take quizzes, submit assignments    |

**Note:** There is **one unified HOD** (`hod_informatics`) with department-scoped manager access over BIT, BCS, and shared courses.

---

## Routine Maintenance

### Cache Management

```bash
php moodle/admin/cli/purge_caches.php
```

### Run Cron

```bash
php scripts/run_cron.php
```

### Update Course Images

After updating images in the `images/` directory:

```bash
php scripts/apply_full_course_template.php
```

### Reconfigure reCAPTCHA

To update reCAPTCHA keys:

```bash
php scripts/set_recaptcha.php <site_key> <secret_key>
```

---

## Course Management

### Adding New Courses

**Recommended:** Use the automated script:

```bash
php scripts/create_all_courses.php
```

**Manual Creation:**

1. Create course via Moodle UI in appropriate category
2. Run cron to sync: `php scripts/run_cron.php`

See `scripts/course_management_procedures.md` for complete guidelines.

---

## Troubleshooting

### Database Connection Issues

- Check `.env` file for correct database credentials
- Verify PostgreSQL is running: `psql -U moodleuser -d moodle`

### Server Won't Start

- Ensure no other processes are using ports 8081 (Nginx) or 9000 (PHP-FPM)
- Check `logs/` directory for error details

### Students Can't Register

- Verify reCAPTCHA keys are valid: `php scripts/set_recaptcha.php`
- Check SMTP settings in `.env` for email confirmation

### System Performance Issues

```bash
php moodle/admin/cli/purge_caches.php
php scripts/run_cron.php
```

---

## Scripts Reference

| Script                           | Purpose                     | Safe to Run?       |
| -------------------------------- | --------------------------- | ------------------ |
| `run_setup.php`                  | Master setup runner         | ⚠️ First-time only |
| `setup_moodle.php`               | Core config                 | ⚠️ First-time only |
| `create_all_courses.php`         | Create courses              | ⚠️ Test first      |
| `create_categories.php`          | Create categories           | ⚠️ Test first      |
| `cleanup_stale_courses.php`      | Remove stale shared copies  | ⚠️ Destructive     |
| `normalize_shared_course_links.php` | Canonicalize shared links | ⚠️ Test first      |
| `create_students.php`            | Create student accounts     | ⚠️ Test first      |
| `create_lecturers.php`           | Create facilitator accounts | ⚠️ Test first      |
| `enrol_student.php`              | Enroll students             | ⚠️ Test first      |
| `cross_enroll_shared.php`        | Legacy cross-enrollment     | ⚠️ Legacy          |
| `audit_teachers.php`             | Audit teacher assignments   | ✅ Safe            |
| `apply_full_course_template.php` | Apply images + styling      | ✅ Re-runnable     |
| `set_recaptcha.php`              | Configure reCAPTCHA         | ✅ Re-runnable     |
| `generate_reg_tokens.php`        | Generate tokens             | ✅ Re-runnable     |
| `run_cron.php`                   | Trigger cron                | ✅ Safe            |

---

## Documentation

- **Course Management Procedures** — `scripts/course_management_procedures.md`
- [Official Moodle Documentation](https://docs.moodle.org)

---

## Support

For technical support or questions:

- IT Support Team
- Moodle Community Forums: https://moodle.org/forums

---

## License

This system is based on Moodle, which is licensed under GPL v3.


