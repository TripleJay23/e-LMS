#!/usr/bin/env php
<?php
/**
 * Run Moodle Cron to Process Ad-hoc Tasks
 * This will process pending question bank migration tasks
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/admin/cli/cron.php');
