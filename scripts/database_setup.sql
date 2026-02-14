-- Database Setup Script for e-LMS
-- Creates institutional structure tables for faculties, departments, and programs
-- Compatible with Moodle's PostgreSQL database

-- Faculty table
CREATE TABLE IF NOT EXISTS mdl_custom_faculties (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL
);

-- Department table
CREATE TABLE IF NOT EXISTS mdl_custom_departments (
    id BIGSERIAL PRIMARY KEY,
    facultyid BIGINT NOT NULL REFERENCES mdl_custom_faculties(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL
);

-- Program table
CREATE TABLE IF NOT EXISTS mdl_custom_programs (
    id BIGSERIAL PRIMARY KEY,
    departmentid BIGINT NOT NULL REFERENCES mdl_custom_departments(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    acronym VARCHAR(50),
    level VARCHAR(50), -- 'certificate', 'diploma', 'bachelor'
    duration INT, -- in semesters
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL
);

-- Link courses to programs
CREATE TABLE IF NOT EXISTS mdl_custom_program_courses (
    id BIGSERIAL PRIMARY KEY,
    programid BIGINT NOT NULL REFERENCES mdl_custom_programs(id) ON DELETE CASCADE,
    courseid BIGINT NOT NULL REFERENCES mdl_course(id) ON DELETE CASCADE,
    year INT, -- which year of study
    semester INT, -- which semester
    timecreated BIGINT NOT NULL,
    UNIQUE(programid, courseid)
);

-- Track student program enrollment
CREATE TABLE IF NOT EXISTS mdl_custom_student_programs (
    id BIGSERIAL PRIMARY KEY,
    userid BIGINT NOT NULL REFERENCES mdl_user(id) ON DELETE CASCADE,
    programid BIGINT NOT NULL REFERENCES mdl_custom_programs(id) ON DELETE CASCADE,
    yearofstudy INT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'active',
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL,
    UNIQUE(userid, programid)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_departments_faculty ON mdl_custom_departments(facultyid);
CREATE INDEX IF NOT EXISTS idx_programs_department ON mdl_custom_programs(departmentid);
CREATE INDEX IF NOT EXISTS idx_program_courses_program ON mdl_custom_program_courses(programid);
CREATE INDEX IF NOT EXISTS idx_program_courses_course ON mdl_custom_program_courses(courseid);
CREATE INDEX IF NOT EXISTS idx_student_programs_user ON mdl_custom_student_programs(userid);
CREATE INDEX IF NOT EXISTS idx_student_programs_program ON mdl_custom_student_programs(programid);

-- Success message
DO $$
BEGIN
    RAISE NOTICE 'Database schema created successfully!';
    RAISE NOTICE 'Tables created: mdl_custom_faculties, mdl_custom_departments, mdl_custom_programs, mdl_custom_program_courses, mdl_custom_student_programs';
END $$;
