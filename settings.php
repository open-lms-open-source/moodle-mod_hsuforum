<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   mod_hsuforum
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/hsuforum/lib.php');

    $config = get_config('hsuforum');

    $settings->add(new admin_setting_configcheckbox('hsuforum/replytouser', get_string('replytouser', 'hsuforum'),
                       get_string('configreplytouser', 'hsuforum'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('hsuforum/shortpost', get_string('shortpost', 'hsuforum'),
                       get_string('configshortpost', 'hsuforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('hsuforum/longpost', get_string('longpost', 'hsuforum'),
                       get_string('configlongpost', 'hsuforum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('hsuforum/manydiscussions', get_string('manydiscussions', 'hsuforum'),
                       get_string('configmanydiscussions', 'hsuforum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($config->maxbytes)) {
            $maxbytes = $config->maxbytes;
        }
        $settings->add(new admin_setting_configselect('hsuforum/maxbytes', get_string('maxattachmentsize', 'hsuforum'),
                           get_string('configmaxbytes', 'hsuforum'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all forums
    $settings->add(new admin_setting_configtext('hsuforum/maxattachments', get_string('maxattachments', 'hsuforum'),
                       get_string('configmaxattachments', 'hsuforum'), 9, PARAM_INT));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('hsuforum/oldpostdays', get_string('oldpostdays', 'hsuforum'),
                       get_string('configoldpostdays', 'hsuforum'), 14, PARAM_INT));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('hsuforum/cleanreadtime', get_string('cleanreadtime', 'hsuforum'),
                       get_string('configcleanreadtime', 'hsuforum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('hsuforum/digestmailtime', get_string('digestmailtime', 'hsuforum'),
                       get_string('configdigestmailtime', 'hsuforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'hsuforum').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'hsuforum');
    }
    $settings->add(new admin_setting_configselect('hsuforum/enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    $settings->add(new admin_setting_configcheckbox('hsuforum/enabletimedposts', get_string('timedposts', 'hsuforum'),
                       get_string('configenabletimedposts', 'hsuforum'), 0));
}

