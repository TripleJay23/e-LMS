#!/usr/bin/env php
<?php
/**
 * Database Setup Script for e-LMS
 * Runs the database migrations to create institutional structure tables
 */

// Load .env file
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, '"\'');
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

$dbhost = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'moodle';
$dbuser = getenv('DB_USER') ?: 'moodleuser';
$dbpass = getenv('DB_PASSWORD') ?: 'password';
$dbport = getenv('DB_PORT') ?: '5432';

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║          e-LMS Database Setup Script                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Connecting to database...\n";
echo "  Host: $dbhost:$dbport\n";
echo "  Database: $dbname\n";
echo "  User: $dbuser\n\n";

try {
    $dsn = "pgsql:host=$dbhost;port=$dbport;dbname=$dbname";
    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✓ Connected successfully!\n\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Run database_setup.sql
echo "Step 1: Creating database schema...\n";
$setupSql = file_get_contents(__DIR__ . '/database_setup.sql');
try {
    $pdo->exec($setupSql);
    echo "✓ Schema created successfully!\n\n";
} catch (PDOException $e) {
    echo "✗ Schema creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Run seed_data.sql
echo "Step 2: Seeding initial data...\n";
$seedSql = file_get_contents(__DIR__ . '/seed_data.sql');
try {
    $pdo->exec($seedSql);
    echo "✓ Data seeded successfully!\n\n";
} catch (PDOException $e) {
    echo "✗ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify data
echo "Step 3: Verifying data...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM mdl_custom_faculties");
    $facultyCount = $stmt->fetchColumn();
    echo "  Faculties: $facultyCount\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM mdl_custom_departments");
    $deptCount = $stmt->fetchColumn();
    echo "  Departments: $deptCount\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM mdl_custom_programs");
    $programCount = $stmt->fetchColumn();
    echo "  Programs: $programCount\n\n";
    
    if ($facultyCount > 0 && $deptCount > 0 && $programCount > 0) {
        echo "✓ Verification passed!\n\n";
    } else {
        echo "✗ Verification failed: No data found\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Display created programs
echo "Created Programs:\n";
echo "════════════════════════════════════════════════════════\n";
try {
    $stmt = $pdo->query("
        SELECT p.name, p.acronym, p.level, d.name as department
        FROM mdl_custom_programs p
        JOIN mdl_custom_departments d ON p.departmentid = d.id
        ORDER BY p.level, p.name
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("  • %s (%s) - %s\n", 
            $row['name'], 
            $row['acronym'] ?: 'N/A', 
            $row['level']
        );
        printf("    Department: %s\n", $row['department']);
    }
} catch (PDOException $e) {
    echo "Error displaying programs: " . $e->getMessage() . "\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║              Setup Complete! ✓                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\nNext steps:\n";
echo "  1. Configure Moodle settings (run setup_moodle.php)\n";
echo "  2. Create course categories for programs\n";
echo "  3. Start creating courses\n\n";
