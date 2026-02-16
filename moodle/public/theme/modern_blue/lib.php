<?php
defined('MOODLE_INTERNAL') || die();

function theme_modern_blue_get_main_scss_content($theme)
{
   global $CFG;

   $scss = '
        // Import Boost SCSS
        @import "moodle/public/theme/boost/scss/preset/default.scss";
        @import "moodle/public/theme/boost/scss/boost.scss";
        
        // Import Modern Blue Custom SCSS
        @import "moodle/public/theme/modern_blue/scss/post.scss";
    ';

   // Note: In a real Moodle environment, we usually use file_get_contents on the scss files 
   // or rely on Moodle's built-in SCSS compiler to handle imports relative to the theme directory.
   // For this implementation, we will inject the logic to read the specific SCSS files.

   $boost_scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
   $boost_main = file_get_contents($CFG->dirroot . '/theme/boost/scss/boost.scss');

   // We will append our custom SCSS at the end
   $custom_scss = file_get_contents($CFG->dirroot . '/theme/modern_blue/scss/post.scss');

   return $boost_scss . "\n" . $boost_main . "\n" . $custom_scss;
}
