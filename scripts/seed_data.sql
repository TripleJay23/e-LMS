-- Seed Data Script for e-LMS
-- Target hierarchy:
-- Faculty of Informatics -> Department of Informatics -> BIT + BCS
-- Run this after database_setup.sql

INSERT INTO mdl_custom_faculties (name, code, timecreated, timemodified)
VALUES ('Faculty of Informatics', 'FI', EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    timemodified = EXTRACT(EPOCH FROM NOW())::BIGINT;

DO $$
DECLARE
    faculty_id BIGINT;
    dept_inf_id BIGINT;
BEGIN
    SELECT id INTO faculty_id
    FROM mdl_custom_faculties
    WHERE code = 'FI';

    IF faculty_id IS NULL THEN
        RAISE EXCEPTION 'Faculty FI not found. Run database_setup.sql first.';
    END IF;

    INSERT INTO mdl_custom_departments (facultyid, name, code, timecreated, timemodified)
    VALUES (
        faculty_id,
        'Department of Informatics',
        'INF',
        EXTRACT(EPOCH FROM NOW())::BIGINT,
        EXTRACT(EPOCH FROM NOW())::BIGINT
    )
    ON CONFLICT (code) DO UPDATE
    SET
        facultyid = EXCLUDED.facultyid,
        name = EXCLUDED.name,
        timemodified = EXTRACT(EPOCH FROM NOW())::BIGINT
    RETURNING id INTO dept_inf_id;

    IF dept_inf_id IS NULL THEN
        SELECT id INTO dept_inf_id
        FROM mdl_custom_departments
        WHERE code = 'INF';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM mdl_custom_programs WHERE acronym = 'BCS') THEN
        INSERT INTO mdl_custom_programs (
            departmentid, name, acronym, level, duration, timecreated, timemodified
        ) VALUES (
            dept_inf_id,
            'Bachelor Degree in Computer Science',
            'BCS',
            'bachelor',
            6,
            EXTRACT(EPOCH FROM NOW())::BIGINT,
            EXTRACT(EPOCH FROM NOW())::BIGINT
        );
    ELSE
        UPDATE mdl_custom_programs
        SET
            departmentid = dept_inf_id,
            name = 'Bachelor Degree in Computer Science',
            level = 'bachelor',
            duration = 6,
            timemodified = EXTRACT(EPOCH FROM NOW())::BIGINT
        WHERE acronym = 'BCS';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM mdl_custom_programs WHERE acronym = 'BIT') THEN
        INSERT INTO mdl_custom_programs (
            departmentid, name, acronym, level, duration, timecreated, timemodified
        ) VALUES (
            dept_inf_id,
            'Bachelor Degree in Information Technology',
            'BIT',
            'bachelor',
            6,
            EXTRACT(EPOCH FROM NOW())::BIGINT,
            EXTRACT(EPOCH FROM NOW())::BIGINT
        );
    ELSE
        UPDATE mdl_custom_programs
        SET
            departmentid = dept_inf_id,
            name = 'Bachelor Degree in Information Technology',
            level = 'bachelor',
            duration = 6,
            timemodified = EXTRACT(EPOCH FROM NOW())::BIGINT
        WHERE acronym = 'BIT';
    END IF;

    RAISE NOTICE 'Seed data aligned successfully.';
    RAISE NOTICE 'Faculty: Faculty of Informatics';
    RAISE NOTICE 'Department: Department of Informatics';
    RAISE NOTICE 'Programs: BCS, BIT';
END $$;

