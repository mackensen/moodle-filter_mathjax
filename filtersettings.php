<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('filter_mathjax_distroothttp', 'filter_mathjax_distroothttp',
                       get_string('distroothttp', 'filter_mathjax'), ''));
    $settings->add(new admin_setting_configtext('filter_mathjax_distroothttps', 'filter_mathjax_distroothttps',
                       get_string('distroothttps', 'filter_mathjax'), ''));
}
