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
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/hsuforum/lib.php');

    $config = get_config('hsuforum');

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
    $options = array(
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
        6 => 6,
        7 => 7,
        8 => 8,
        9 => 9,
        10 => 10,
        20 => 20,
        50 => 50,
        100 => 100
    );
    $settings->add(new admin_setting_configselect('hsuforum/maxattachments', get_string('maxattachments', 'hsuforum'),
                       get_string('configmaxattachments', 'hsuforum'), 9, $options));

    // Default Subscription mode setting.
    $options = hsuforum_get_subscriptionmode_options();
    $settings->add(new admin_setting_configselect('hsuforum_subscription', get_string('subscriptionmode', 'hsuforum'),
        get_string('configsubscriptiontype', 'hsuforum'), HSUFORUM_CHOOSESUBSCRIBE, $options));

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

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'hsuforum'),
            2 => get_string('posts', 'hsuforum')
        );
        $settings->add(new admin_setting_configselect('hsuforum_rsstype', get_string('rsstypedefault', 'hsuforum'),
                get_string('configrsstypedefault', 'hsuforum'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('hsuforum_rssarticles', get_string('rssarticles', 'hsuforum'),
                get_string('configrssarticlesdefault', 'hsuforum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('hsuforum/enabletimedposts', get_string('timedposts', 'hsuforum'),
                       get_string('configenabletimedposts', 'hsuforum'), 1));

    $settings->add(new admin_setting_configcheckbox('hsuforum/hiderecentposts', get_string('hiderecentposts', 'hsuforum'),
                       get_string('confighiderecentposts', 'hsuforum'), 0));
}

