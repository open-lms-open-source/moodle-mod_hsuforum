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
 * Set tracking option for the forum.
 *
 * @package   mod_hsuforum
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

require_once("../../config.php");
require_once("lib.php");

$f          = required_param('f',PARAM_INT); // The forum to mark
$mark       = required_param('mark',PARAM_ALPHA); // Read or unread?
$d          = optional_param('d',0,PARAM_INT); // Discussion to mark.
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

$url = new moodle_url('/mod/hsuforum/markposts.php', array('f'=>$f, 'mark'=>$mark));
if ($d !== 0) {
    $url->param('d', $d);
}
if ($returnpage !== 'index.php') {
    $url->param('returnpage', $returnpage);
}
$PAGE->set_url($url);

if (! $forum = $DB->get_record("hsuforum", array("id" => $f))) {
    print_error('invalidforumid', 'hsuforum');
}

if (! $course = $DB->get_record("course", array("id" => $forum->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$user = $USER;

require_login($course, false, $cm);

if ($returnpage == 'index.php') {
    $returnto = hsuforum_go_back_to($returnpage.'?id='.$course->id);
} else {
    $returnto = hsuforum_go_back_to($returnpage.'?f='.$forum->id);
}

if (isguestuser()) {   // Guests can't change forum
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguesttracking', 'hsuforum').'<br /><br />'.get_string('liketologin'), get_login_url(), $returnto);
    echo $OUTPUT->footer();
    exit;
}

$info = new stdClass();
$info->name  = fullname($user);
$info->forum = format_string($forum->name);

if ($mark == 'read') {
    if (!empty($d)) {
        if (! $discussion = $DB->get_record('hsuforum_discussions', array('id'=> $d, 'forum'=> $forum->id))) {
            print_error('invaliddiscussionid', 'hsuforum');
        }

        hsuforum_tp_mark_discussion_read($user, $d);
    } else {
        // Mark all messages read in current group
        $currentgroup = groups_get_activity_group($cm);
        if(!$currentgroup) {
            // mark_hsuforum_read requires ===false, while get_activity_group
            // may return 0
            $currentgroup=false;
        }
        hsuforum_tp_mark_hsuforum_read($user, $forum->id, $currentgroup);
    }

/// FUTURE - Add ability to mark them as unread.
//    } else { // subscribe
//        if (hsuforum_tp_start_tracking($forum->id, $user->id)) {
//            add_to_log($course->id, "hsuforum", "mark unread", "view.php?f=$forum->id", $forum->id, $cm->id);
//            redirect($returnto, get_string("nowtracking", "hsuforum", $info), 1);
//        } else {
//            print_error("Could not start tracking that forum", $_SERVER["HTTP_REFERER"]);
//        }
}

redirect($returnto);

