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
 * Route entry
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0) {
    define('AJAX_SCRIPT', true);
}
require_once(dirname(dirname(__DIR__)).'/config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/lib/controller/route.php');
require_once(__DIR__.'/controller/posters.php');
require_once(__DIR__.'/controller/flag.php');
require_once(__DIR__.'/controller/posts.php');

global $COURSE, $PAGE, $OUTPUT, $CFG, $DB;  // For IDE...

$contextid = required_param('contextid', PARAM_INT);
$action    = optional_param('action', 'view', PARAM_ACTION);

$context = context::instance_by_id($contextid);
/** @var $coursecontext context_course */
$coursecontext = $context->get_course_context();

$cm       = get_coursemodule_from_id('hsuforum', $context->instanceid, $coursecontext->instanceid, false, MUST_EXIST);
$instance = $DB->get_record('hsuforum', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($cm->course, true, $cm);

$PAGE->set_title("$COURSE->shortname: $instance->name");
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_activity_record($instance);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hsuforum/route.php', array(
    'contextid' => $context->id,
    'action' => $action,
));

$route = new hsuforum_lib_controller_route();
$route->add_controller(new hsuforum_controller_posters());
$route->add_controller(new hsuforum_controller_flag());
$route->add_controller(new hsuforum_controller_posts());

$response = $route->action($action);

if ($response) {
    echo $OUTPUT->header();
    echo $response;
    echo $OUTPUT->footer();
}