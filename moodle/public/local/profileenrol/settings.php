<?php

/**
 * local_profileenrol settings.php
 * Adds the "Generate Registration Tokens" link to Site Administration.
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
   $ADMIN->add('localplugins', new admin_externalpage(
      'local_profileenrol_tokens',
      get_string('generate_tokens', 'local_profileenrol'),
      new moodle_url('/local/profileenrol/admin_tokens.php'),
      'moodle/site:config'
   ));
}
