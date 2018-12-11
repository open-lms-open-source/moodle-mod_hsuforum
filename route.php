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
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_hsuforum\controller\edit_controller;
use mod_hsuforum\controller\export_controller;
use mod_hsuforum\controller\flag_controller;
use mod_hsuforum\controller\kernel;
use mod_hsuforum\controller\posters_controller;
use mod_hsuforum\controller\posts_controller;
use mod_hsuforum\controller\router;

if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
    || !empty($_POST['yuiformsubmit']) // Handle yui form submissions.
) {
    define('AJAX_SCRIPT', true);
    define('NO_DEBUG_DISPLAY', true);
}
require_once(dirname(dirname(__DIR__)).'/config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/classes/controller/kernel.php');
require_once(__DIR__.'/classes/controller/router.php');
require_once(__DIR__.'/classes/controller/export_controller.php');
require_once(__DIR__.'/classes/controller/posters_controller.php');
require_once(__DIR__.'/classes/controller/flag_controller.php');
require_once(__DIR__.'/classes/controller/posts_controller.php');
require_once(__DIR__.'/classes/controller/edit_controller.php');

global $PAGE, $DB;

$contextid = required_param('contextid', PARAM_INT);
$action    = optional_param('action', 'view', PARAM_ALPHAEXT);

list($context, $course, $cm) = get_context_info_array($contextid);

if (empty($cm)) {
    throw new coding_exception("Failed to find course module record with contextid of $contextid");
}
$instance = $DB->get_record('hsuforum', array('id' => $cm->instance), '*', MUST_EXIST);

$PAGE->set_url('/mod/hsuforum/route.php', array(
    'contextid' => $context->id,
    'action'    => $action,
));
require_login($course, true, $cm);

$PAGE->set_title("$course->shortname: $instance->name");
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($instance);
$PAGE->set_context($context);

$router = new router();
$router->add_controller(new posters_controller());
$router->add_controller(new flag_controller());
$router->add_controller(new posts_controller());
$router->add_controller(new export_controller());
$router->add_controller(new edit_controller());

$kernel = new kernel($router);
$kernel->handle($action);
