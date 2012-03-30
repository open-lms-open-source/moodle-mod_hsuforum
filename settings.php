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
 * @package mod-forum
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/forum/lib.php');

    $settings->add(new admin_setting_configselect('hsuforum_displaymode', get_string('displaymode', 'hsuforum'),
                       get_string('configdisplaymode', 'hsuforum'), FORUM_MODE_NESTED, hsuforum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('hsuforum_replytouser', get_string('replytouser', 'hsuforum'),
                       get_string('configreplytouser', 'hsuforum'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('hsuforum_shortpost', get_string('shortpost', 'hsuforum'),
                       get_string('configshortpost', 'hsuforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('hsuforum_longpost', get_string('longpost', 'hsuforum'),
                       get_string('configlongpost', 'hsuforum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('hsuforum_manydiscussions', get_string('manydiscussions', 'hsuforum'),
                       get_string('configmanydiscussions', 'hsuforum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $settings->add(new admin_setting_configselect('hsuforum_maxbytes', get_string('maxattachmentsize', 'hsuforum'),
                           get_string('configmaxbytes', 'hsuforum'), 512000, get_max_upload_sizes($CFG->maxbytes)));
    }

    // Default number of attachments allowed per post in all forums
    $settings->add(new admin_setting_configtext('hsuforum_maxattachments', get_string('maxattachments', 'hsuforum'),
                       get_string('configmaxattachments', 'hsuforum'), 9, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('hsuforum_trackreadposts', get_string('trackforum', 'hsuforum'),
                       get_string('configtrackreadposts', 'hsuforum'), 1));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('hsuforum_oldpostdays', get_string('oldpostdays', 'hsuforum'),
                       get_string('configoldpostdays', 'hsuforum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('hsuforum_usermarksread', get_string('usermarksread', 'hsuforum'),
                       get_string('configusermarksread', 'hsuforum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('hsuforum_cleanreadtime', get_string('cleanreadtime', 'hsuforum'),
                       get_string('configcleanreadtime', 'hsuforum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'hsuforum'),
                       get_string('configdigestmailtime', 'hsuforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'hsuforum').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'hsuforum');
    }
    $settings->add(new admin_setting_configselect('hsuforum_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    $settings->add(new admin_setting_configcheckbox('hsuforum_enabletimedposts', get_string('timedposts', 'hsuforum'),
                       get_string('configenabletimedposts', 'hsuforum'), 0));
}

