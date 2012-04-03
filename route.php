<?php
/**
 * Route entry
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__DIR__)).'/config.php');
require_once(__DIR__.'/lib/controller/route.php');
require_once(__DIR__.'/controller/posters.php');

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

$response = $route->action($action);

if ($response) {
    echo $OUTPUT->header();
    echo $response;
    echo $OUTPUT->footer();
}