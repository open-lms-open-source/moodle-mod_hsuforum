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
 * Set the mail digest option in a specific forum for a user.
 *
 * @copyright 2013 Andrew Nicols
 * @package   mod_hsuforum
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/mod/hsuforum/lib.php');

$id = required_param('id', PARAM_INT);
$maildigest = required_param('maildigest', PARAM_INT);
$backtoindex = optional_param('backtoindex', 0, PARAM_INT);

// We must have a valid session key.
require_sesskey();

$forum = $DB->get_record('hsuforum', array('id' => $id));
$course  = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

$url = new moodle_url('/mod/hsuforum/maildigest.php', array(
    'id' => $id,
    'maildigest' => $maildigest,
));
$PAGE->set_url($url);
$PAGE->set_context($context);

$digestoptions = hsuforum_get_user_digest_options();

$info = new stdClass();
$info->name  = fullname($USER);
$info->forum = format_string($forum->name);
hsuforum_set_user_maildigest($forum, $maildigest);
$info->maildigest = $maildigest;

if ($maildigest === -1) {
    // Get the default maildigest options.
    $info->maildigest = $USER->maildigest;
    $info->maildigesttitle = $digestoptions[$info->maildigest];
    $info->maildigestdescription = get_string('emaildigest_' . $info->maildigest,
        'mod_hsuforum', $info);
    $updatemessage = get_string('emaildigestupdated_default', 'hsuforum', $info);
} else {
    $info->maildigesttitle = $digestoptions[$info->maildigest];
    $info->maildigestdescription = get_string('emaildigest_' . $info->maildigest,
        'mod_hsuforum', $info);
    $updatemessage = get_string('emaildigestupdated', 'hsuforum', $info);
}

if ($backtoindex) {
    $returnto = "index.php?id={$course->id}";
} else {
    $returnto = "view.php?f={$id}";
}

redirect($returnto, $updatemessage, null, \core\output\notification::NOTIFY_SUCCESS);
