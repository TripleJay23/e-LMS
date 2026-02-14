# e-LMS - e-Learning Management System

## Overview

This is a Moodle-based e-Learning Management System customized for the Faculty of Informatics, supporting BIT (Bachelor in Information Technology) and BCS (Bachelor in Computer Science) programs.

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

## Programs Supported

The system currently supports 5 programs from the Faculty of Informatics:

| Acronym   | Program Name                                     | Level       | Duration    |
| --------- | ------------------------------------------------ | ----------- | ----------- |
| **BIT**   | Bachelor Degree in Information Technology        | Bachelor    | 6 semesters |
| **BCS**   | Bachelor Degree in Computer Science              | Bachelor    | 6 semesters |
| **DIT**   | Ordinary Diploma in Information Technology       | Diploma     | 4 semesters |
| **DCS**   | Ordinary Diploma in Computer Science             | Diploma     | 4 semesters |
| **BTCIT** | Basic Technician Certificate in Computing and IT | Certificate | 2 semesters |

## Technology Stack

- **Platform**: Moodle 4.x
- **Database**: PostgreSQL
- **Web Server**: Nginx
- **PHP**: 8.x
- **Operating System**: Windows

## Installation and Setup

### Prerequisites

- PostgreSQL running on port 5432
- Nginx web server
- PHP 8.x with required extensions
- Moodledata directory configured

### Implementation Status

✅ **Completed:**

1. Database schema for institutional structure (faculties, departments, programs)
2. Seed data for Faculty of Informatics with 5 programs
3. Moodle configuration:
   - Student self-registration enabled
   - File upload limit: 100MB
   - Media player support enabled
4. Course categories created for all 5 programs

### Running the Setup

The system has been pre-configured. To verify the setup:

```bash
cd "c:\Users\jtrip\Desktop\Group 07\e-LMS"

# Verify database setup
php scripts/run_setup.php

# Verify Moodle configuration
php scripts/setup_moodle.php

# Verify course categories
php scripts/create_categories.php
```

## Directory Structure

```
e-LMS/
├── moodle/                 # Moodle installation
│   ├── config.php          # Moodle configuration
│   └── ...
├── scripts/                # Setup and utility scripts
│   ├── database_setup.sql  # Database schema creation
│   ├── seed_data.sql       # Initial data population
│   ├── run_setup.php       # Database setup script
│   ├── setup_moodle.php    # Moodle configuration script
│   ├── create_categories.php # Course category creation
│   └── enrol_student.php   # Student enrollment utility
├── .env                    # Environment variables
└── README.md              # This file
```

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

## Usage

### For Administrators

1. **Access Moodle Admin**: http://localhost:8080/admin
2. **Create Courses**: Site administration → Courses → Add a new course
3. **Assign Facilitators**: Enroll users with `editingteacher` role
4. **Enroll Students in Programs**:
   ```bash
   php scripts/enrol_student.php <username> <program_acronym>
   # Example: php scripts/enrol_student.php john.doe BIT
   ```

### For Facilitators

See `Facilitator Guide` artifact for detailed instructions on:

- Uploading lecture materials
- Creating quizzes
- Grading assignments

### For Students

See `Student Guide` artifact for detailed instructions on:

- Creating an account
- Accessing courses
- Viewing materials and taking quizzes

## User Roles

| Role        | Moodle Role      | Capabilities                                      |
| ----------- | ---------------- | ------------------------------------------------- |
| Super Admin | `manager`        | Full platform access                              |
| Facilitator | `editingteacher` | Upload content, create quizzes, grade assignments |
| Student     | `student`        | View content, take quizzes, submit assignments    |

## Quick Start

### 1. Access the System

Visit: http://localhost:8080

### 2. Create a Test Student Account

1. Click "Log in" → "Create new account"
2. Fill in the registration form
3. Confirm your email
4. Log in

### 3. Create a Test Course (Admin)

1. Login as admin
2. Site administration → Courses → Add a new course
3. Select category (e.g., BIT)
4. Fill in course details
5. Enroll yourself as `editingteacher`

### 4. Add Content (Facilitator)

1. Enter the course
2. Turn editing on
3. Add File resource → Upload PDF
4. Add URL resource → Add video link
5. Add Quiz → Create questions

### 5. Test as Student

1. Enroll test student in program
2. Student logs in
3. Student accesses course
4. Student views materials and takes quiz

## Verification Checklist

- [x] Database schema created
- [x] Seed data populated (5 programs)
- [x] Moodle configured for self-registration
- [x] File upload limit set to 100MB
- [x] Course categories created for all programs
- [ ] Test courses created
- [ ] Test facilitator can upload content
- [ ] Test student can register and access content
- [ ] Test quiz functionality

## Troubleshooting

### Database Connection Issues

- Check `.env` file for correct database credentials
- Verify PostgreSQL is running: `psql -U moodleuser -d moodle`

### File Upload Issues

- Verify `maxbytes` setting in Moodle
- Check directory permissions for moodledata

### Video Playback Issues

- Use external hosting (YouTube, Vimeo) for large videos
- Ensure browser supports HTML5 video

## Next Steps

1. Create sample courses for each program
2. Add facilitators and assign them to courses
3. Create sample content (PDFs, videos, quizzes)
4. Enroll test students and verify functionality
5. Deploy to production server (if applicable)

## Documentation

- Implementation Plan - See artifacts
- Facilitator Guide - See artifacts
- Student Guide - See artifacts
- [Official Moodle Documentation](https://docs.moodle.org)

## Support

For technical support or questions:

- IT Support Team
- Moodle Community Forums: https://moodle.org/forums

## License

This system is based on Moodle, which is licensed under GPL v3.

---

**Last Updated**: February 12, 2026
**Version**: 1.0
