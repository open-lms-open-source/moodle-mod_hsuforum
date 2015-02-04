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
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

    require_once('../../config.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', false, PARAM_INT);       // Forum instance id (id in course modules table)
    $f           = optional_param('f', false, PARAM_INT);        // Forum ID
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();

    if (!$f && !$id) {
        print_error('missingparameter');
    } else if ($f) {
        $forum = $DB->get_record('hsuforum', array('id' => $f));
        $params['f'] = $forum->id;
    } else {
        if (!$cm = get_coursemodule_from_id('hsuforum', $id)){
            print_error('missingparameter');
        }
        $forum = $DB->get_record('hsuforum', array('id' => $cm->instance));
        $params['id'] = $cm->id;
    }

    if ($forum->type == 'single') {
        $discussions = $DB->get_records('hsuforum_discussions', array('forum' => $forum->id), 'timemodified ASC');
        $discussion  = array_pop($discussions);

        if (empty($discussion)) {
            print_error('cannotfindfirstpost', 'hsuforum');
        }
        redirect(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id)));
    }

    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/hsuforum/view.php', $params);

    $course = $DB->get_record('course', array('id' => $forum->course));

    if (!$cm && !$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
        print_error('missingparameter');
    }

// move require_course_login here to use forced language for course
// fix for MDL-6926
    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);
    require_course_login($course, true, $cm);

/// Print header.
    $PAGE->set_title($forum->name);
    $PAGE->add_body_class('forumtype-'.$forum->type);
    $PAGE->set_heading($course->fullname);

    $renderer = $PAGE->get_renderer('mod_hsuforum');
/// This has to be called before we start setting up page as it triggers view events.
    $discussionview = $renderer->render_discussionsview($forum);

    echo $OUTPUT->header();
    echo ('<div id="discussionsview">');

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'hsuforum'));
    }

    echo $discussionview;

    echo '</div>';
    echo $renderer->advanced_editor();
    echo $OUTPUT->footer($course);
