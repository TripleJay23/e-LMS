<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'modern_blue';
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->parents = ['boost'];
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;

$THEME->scss = function ($theme) {
   return theme_modern_blue_get_main_scss_content($theme);
};
