#!/usr/bin/env php
<?php
/**
 * Set reCAPTCHA keys and enable CAPTCHA on login and signup pages.
 *
 * Usage: php set_recaptcha.php <site_key> <secret_key>
 *
 * What this does:
 *   - Sets recaptchapublickey  → shown on login/signup forms (reCAPTCHA widget)
 *   - Sets recaptchaprivatekey → used server-side to verify CAPTCHA responses
 *   - Sets enableloginrecaptcha = 1 → enables CAPTCHA on the login page
 *   - Sets enableforgotpasswordrecaptcha = 1 → enables CAPTCHA on forgot-password page
 *
 * The signup form (login/signup_form.php) already calls signup_captcha_enabled()
 * which checks that both recaptchapublickey and recaptchaprivatekey are set.
 * So once keys are saved, the signup CAPTCHA is automatically active.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/clilib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║          e-LMS reCAPTCHA Configuration Script         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Read keys from command-line arguments or use hardcoded defaults.
$sitekey   = $argv[1] ?? '6Lf6gHMsAAAAAPG2JtVwoHqujvUC95TyTDkNPlOo';
$secretkey = $argv[2] ?? '6Lf6gHMsAAAAAJuvZaAQ9Q1NIWcW0FNd04h2281u';

if (empty($sitekey) || empty($secretkey)) {
    echo "ERROR: Site key and secret key are required.\n";
    echo "Usage: php set_recaptcha.php <site_key> <secret_key>\n";
    exit(1);
}

try {
    echo "Applying reCAPTCHA settings...\n\n";

    // 1. Set the public (site) key - used to render the widget in the browser.
    echo "1. Setting reCAPTCHA public (site) key...\n";
    set_config('recaptchapublickey', $sitekey);
    echo "   ✓ recaptchapublickey = " . substr($sitekey, 0, 10) . "...\n\n";

    // 2. Set the private (secret) key - used server-side for verification.
    echo "2. Setting reCAPTCHA private (secret) key...\n";
    set_config('recaptchaprivatekey', $secretkey);
    echo "   ✓ recaptchaprivatekey = " . substr($secretkey, 0, 10) . "...\n\n";

    // 3. Enable CAPTCHA on the login page.
    //    Value 1 = Yes (show reCAPTCHA after failed login attempts).
    echo "3. Enabling reCAPTCHA on login page...\n";
    set_config('enableloginrecaptcha', 1);
    echo "   ✓ enableloginrecaptcha = 1 (enabled)\n\n";

    // 4. Enable CAPTCHA on the forgot-password page.
    echo "4. Enabling reCAPTCHA on forgot-password page...\n";
    set_config('enableforgotpasswordrecaptcha', 1);
    echo "   ✓ enableforgotpasswordrecaptcha = 1 (enabled)\n\n";

    // 5. Purge Moodle config cache so changes take effect immediately.
    echo "5. Purging config cache...\n";
    purge_all_caches();
    echo "   ✓ Cache purged\n\n";

    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║         reCAPTCHA Configuration Complete! ✓           ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    echo "Active on these pages:\n";
    echo "  • http://localhost:8081/login/index.php    (login)\n";
    echo "  • http://localhost:8081/login/signup.php   (signup)\n";
    echo "  • http://localhost:8081/login/forgot_password.php\n\n";

    echo "Notes:\n";
    echo "  - Login CAPTCHA appears after failed login attempts.\n";
    echo "  - Signup CAPTCHA is always shown when keys are configured.\n";
    echo "  - Forgot-password CAPTCHA is shown on every submission.\n\n";

} catch (Exception $e) {
    echo "✗ Configuration failed: " . $e->getMessage() . "\n";
    exit(1);
}
