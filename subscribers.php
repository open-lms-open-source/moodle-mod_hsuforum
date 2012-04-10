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
 * This file is used to display and organise forum subscribers
 *
 * @package mod-hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id    = required_param('id',PARAM_INT);           // forum
$group = optional_param('group',0,PARAM_INT);      // change of group
$edit  = optional_param('edit',-1,PARAM_BOOL);     // Turn editing on and off

$url = new moodle_url('/mod/hsuforum/subscribers.php', array('id'=>$id));
if ($group !== 0) {
    $url->param('group', $group);
}
if ($edit !== 0) {
    $url->param('edit', $edit);
}
$PAGE->set_url($url);

$forum = $DB->get_record('hsuforum', array('id'=>$id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$forum->course), '*', MUST_EXIST);
if (! $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id)) {
    $cm->id = 0;
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/hsuforum:viewsubscribers', $context)) {
    print_error('nopermissiontosubscribe', 'hsuforum');
}

unset($SESSION->fromdiscussion);

add_to_log($course->id, "hsuforum", "view subscribers", "subscribers.php?id=$forum->id", $forum->id, $cm->id);

$forumoutput = $PAGE->get_renderer('mod_hsuforum');
$currentgroup = groups_get_activity_group($cm);
$options = array('forumid'=>$forum->id, 'currentgroup'=>$currentgroup, 'context'=>$context);
$existingselector = new hsuforum_existing_subscriber_selector('existingsubscribers', $options);
$subscriberselector = new hsuforum_potential_subscriber_selector('potentialsubscribers', $options);
$subscriberselector->set_existing_subscribers($existingselector->find_users(''));

if (data_submitted()) {
    require_sesskey();
    $subscribe = (bool)optional_param('subscribe', false, PARAM_RAW);
    $unsubscribe = (bool)optional_param('unsubscribe', false, PARAM_RAW);
    /** It has to be one or the other, not both or neither */
    if (!($subscribe xor $unsubscribe)) {
        print_error('invalidaction');
    }
    if ($subscribe) {
        $users = $subscriberselector->get_selected_users();
        foreach ($users as $user) {
            if (!hsuforum_subscribe($user->id, $id)) {
                print_error('cannotaddsubscriber', 'hsuforum', '', $user->id);
            }
        }
    } else if ($unsubscribe) {
        $users = $existingselector->get_selected_users();
        foreach ($users as $user) {
            if (!hsuforum_unsubscribe($user->id, $id)) {
                print_error('cannotremovesubscriber', 'hsuforum', '', $user->id);
            }
        }
    }
    $subscriberselector->invalidate_selected_users();
    $existingselector->invalidate_selected_users();
    $subscriberselector->set_existing_subscribers($existingselector->find_users(''));
}

$strsubscribers = get_string("subscribers", "hsuforum");
$PAGE->navbar->add($strsubscribers);
$PAGE->set_title($strsubscribers);
$PAGE->set_heading($COURSE->fullname);
if (has_capability('mod/hsuforum:managesubscriptions', $context)) {
    if ($edit != -1) {
        $USER->subscriptionsediting = $edit;
    }
    $PAGE->set_button(hsuforum_update_subscriptions_button($course->id, $id));
} else {
    unset($USER->subscriptionsediting);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forum', 'hsuforum').' '.$strsubscribers);
if (empty($USER->subscriptionsediting)) {
    echo $forumoutput->subscriber_overview(hsuforum_subscribed_users($course, $forum, $currentgroup, $context), $forum->name, $course);
} else if (hsuforum_is_forcesubscribed($forum)) {
    $subscriberselector->set_force_subscribed(true);
    echo $forumoutput->subscribed_users($subscriberselector);
} else {
    echo $forumoutput->subscriber_selection_form($existingselector, $subscriberselector);
}
echo $OUTPUT->footer();