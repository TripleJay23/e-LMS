-- Seed Data Script for e-LMS
-- Populates institutional structure with Faculty of Informatics and BIT/BCS programs
-- Run this after database_setup.sql

-- Insert Faculty of Informatics
INSERT INTO mdl_custom_faculties (name, code, timecreated, timemodified)
VALUES ('Faculty of Informatics', 'FI', EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
ON CONFLICT (code) DO NOTHING;

-- Insert departments and programs
DO $$
DECLARE
    faculty_id BIGINT;
    dept_csm_id BIGINT;
    dept_is_id BIGINT;
BEGIN
    -- Get faculty ID
    SELECT id INTO faculty_id FROM mdl_custom_faculties WHERE code = 'FI';
    
    IF faculty_id IS NULL THEN
        RAISE EXCEPTION 'Faculty not found. Please run database_setup.sql first.';
    END IF;
    
    -- Insert Department of Computer Science and Mathematics
    INSERT INTO mdl_custom_departments (facultyid, name, code, timecreated, timemodified)
    VALUES (faculty_id, 'Department of Computer Science and Mathematics', 'CSM', 
            EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
    ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name
    RETURNING id INTO dept_csm_id;
    
    -- If already exists, get the ID
    IF dept_csm_id IS NULL THEN
        SELECT id INTO dept_csm_id FROM mdl_custom_departments WHERE code = 'CSM';
    END IF;
    
    -- Insert Department of Information Systems
    INSERT INTO mdl_custom_departments (facultyid, name, code, timecreated, timemodified)
    VALUES (faculty_id, 'Department of Information Systems', 'IS', 
            EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
    ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name
    RETURNING id INTO dept_is_id;
    
    -- If already exists, get the ID
    IF dept_is_id IS NULL THEN
        SELECT id INTO dept_is_id FROM mdl_custom_departments WHERE code = 'IS';
    END IF;
    
    -- Insert Programs for Computer Science and Mathematics
    INSERT INTO mdl_custom_programs (departmentid, name, acronym, level, duration, timecreated, timemodified)
    VALUES 
        (dept_csm_id, 'Ordinary Diploma in Computer Science', 'DCS', 'diploma', 4, 
         EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT),
        (dept_csm_id, 'Bachelor Degree in Computer Science', 'BCS', 'bachelor', 6, 
         EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
    ON CONFLICT DO NOTHING;
    
    -- Insert Programs for Information Systems
    INSERT INTO mdl_custom_programs (departmentid, name, acronym, level, duration, timecreated, timemodified)
    VALUES 
        (dept_is_id, 'Basic Technician Certificate in Computing and Information Technology', 'BTCIT', 'certificate', 2, 
         EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT),
        (dept_is_id, 'Ordinary Diploma in Information Technology', 'DIT', 'diploma', 4, 
         EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT),
        (dept_is_id, 'Bachelor Degree in Information Technology', 'BIT', 'bachelor', 6, 
         EXTRACT(EPOCH FROM NOW())::BIGINT, EXTRACT(EPOCH FROM NOW())::BIGINT)
    ON CONFLICT DO NOTHING;
    
    RAISE NOTICE 'Seed data inserted successfully!';
    RAISE NOTICE 'Faculty: Faculty of Informatics';
    RAISE NOTICE 'Departments: Computer Science and Mathematics, Information Systems';
    RAISE NOTICE 'Programs: DCS, BCS, BTCIT, DIT, BIT (5 programs total)';
END $$;
