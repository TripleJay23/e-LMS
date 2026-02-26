<?php

/**
 * local_profileenrol lib.php
 *
 * Hooks into Moodle signup form VALIDATION only.
 * All post-signup logic (auto-confirm, enrol, claim) is in observer.php.
 *
 * Hook: local_profileenrol_validate_extend_signup_form($data)
 *   — Called by core_login_validate_extend_signup_form() during signup form validation
 *   — Runs BEFORE user is created → perfect for validation, not for DB writes
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Validate signup form: reject if email is duplicate or token is invalid/claimed/mismatched.
 */
function local_profileenrol_validate_extend_signup_form($data)
{
   global $DB;

   $errors = [];

   // ── Email uniqueness check ─────────────────────────────────────────────────
   $email = trim($data['email'] ?? '');
   if ($email && $DB->record_exists_select(
      'user',
      'LOWER(email) = LOWER(:email) AND deleted = 0',
      ['email' => $email]
   )) {
      $errors['email'] = 'An account with that email address already exists. Please use a different email or contact your administrator.';
      return $errors;
   }

   // ── Registration token validation ──────────────────────────────────────────
   $reg_number = trim($data['profile_field_reg_number'] ?? '');
   $program    = strtoupper(trim($data['profile_field_program_study'] ?? ''));
   $year       = (int)($data['profile_field_year_of_study'] ?? 0);

   if (empty($reg_number)) {
      return $errors; // Moodle shows "required field" separately
   }

   $token = $DB->get_record('custom_reg_tokens', ['reg_number' => $reg_number]);

   if (!$token) {
      $errors['profile_field_reg_number'] = 'Invalid registration number. Please check with your institution.';
      return $errors;
   }

   if (strtolower((string)$token->status) !== 'unused') {
      $errors['profile_field_reg_number'] = 'This registration number has already been used. Contact your administrator if this is an error.';
      return $errors;
   }

   if (strtoupper($token->program) !== $program) {
      $errors['profile_field_reg_number'] = 'Registration number does not match the selected Program of Study.';
      return $errors;
   }

   if ((int)$token->year !== $year) {
      $errors['profile_field_reg_number'] = 'Registration number does not match the selected Year of Study.';
      return $errors;
   }

   return $errors;
}
