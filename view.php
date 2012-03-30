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
 * @package mod-hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/hsuforum/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('hsuforum', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $forum = $DB->get_record("hsuforum", array("id" => $cm->instance))) {
            print_error('invalidforumid', 'hsuforum');
        }
        if ($forum->type == 'single') {
            $PAGE->set_pagetype('mod-hsuforum-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforums = get_string("modulenameplural", "hsuforum");
        $strforum = get_string("modulename", "hsuforum");
    } else if ($f) {

        if (! $forum = $DB->get_record("hsuforum", array("id" => $f))) {
            print_error('invalidforumid', 'hsuforum');
        }
        if (! $course = $DB->get_record("course", array("id" => $forum->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforums = get_string("modulenameplural", "hsuforum");
        $strforum = get_string("modulename", "hsuforum");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(hsuforum_search_form($course, $search));
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->hsuforum_enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id))) . ': %fullname%';
        rss_add_http_header($context, 'mod_hsuforum', $forum, $rsstitle);
    }

    // Mark viewed if required
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

/// Print header.

    $PAGE->set_title(format_string($forum->name));
    $PAGE->add_body_class('forumtype-'.$forum->type);
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'hsuforum'));
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/hsuforum/view.php?id=' . $cm->id);
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

/// Okay, we can show the discussions. Log the forum view.
    if ($cm->id) {
        add_to_log($course->id, "hsuforum", "view forum", "view.php?id=$cm->id", "$forum->id", $cm->id);
    } else {
        add_to_log($course->id, "hsuforum", "view forum", "view.php?f=$forum->id", "$forum->id");
    }

    $SESSION->fromdiscussion = $FULLME;   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion forum, we need to print the display
    // mode control.
    if ($forum->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('hsuforum_discussions', array('forum'=>$forum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("hsuforum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("hsuforum_displaymode", $CFG->hsuforum_displaymode);
            hsuforum_print_mode_form($forum->id, $displaymode, $forum->type);
        }
    }

    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a->blockafter = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumisthrottled','hsuforum',$a));
    }

    if ($forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','hsuforum'));
    }

    switch ($forum->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'hsuforum'));
            }
            if (! $post = hsuforum_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'hsuforum');
            }
            if ($mode) {
                set_user_preference("hsuforum_displaymode", $mode);
            }

            $canreply    = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/hsuforum:rate', $context);
            $displaymode = get_user_preferences("hsuforum_displaymode", $CFG->hsuforum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            hsuforum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            if (!empty($forum->intro)) {
                echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
            }
            echo '<p class="mdl-align">';
            if (hsuforum_user_can_post_discussion($forum, null, -1, $cm)) {
                print_string("allowsdiscussions", "hsuforum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                hsuforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                hsuforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                hsuforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                hsuforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
            }
            break;

        case 'blog':
            if (!empty($forum->intro)) {
                echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
            }
            echo '<br />';
            if (!empty($showall)) {
                hsuforum_print_latest_discussions($course, $forum, 0, 'plain', '', -1, -1, -1, 0, $cm);
            } else {
                hsuforum_print_latest_discussions($course, $forum, -1, 'plain', '', -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
            }
            break;

        default:
            if (!empty($forum->intro)) {
                echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
            }
            echo '<br />';
            if (!empty($showall)) {
                hsuforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                hsuforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
            }


            break;
    }

    echo $OUTPUT->footer($course);


