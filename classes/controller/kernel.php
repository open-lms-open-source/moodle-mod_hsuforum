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
 * Controller Kernel
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\controller;

use mod_hsuforum\response\response_interface;
use mod_hsuforum_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles typical request lifecycle.
 *
 * Given an action, route it to a controller method,
 * execute controller method and handle any return
 * values.
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kernel {
    /**
     * @var router
     */
    protected $router;

    /**
     * @var mod_hsuforum_renderer
     */
    protected $renderer;

    /**
     * @param router $router
     * @param mod_hsuforum_renderer $renderer
     */
    public function __construct(router $router, mod_hsuforum_renderer $renderer = null) {
        global $PAGE;

        if (is_null($renderer)) {
            $renderer = $PAGE->get_renderer('mod_hsuforum');
        }
        $this->router   = $router;
        $this->renderer = $renderer;
    }

    /**
     * @return \mod_hsuforum_renderer
     */
    public function get_renderer() {
        return $this->renderer;
    }

    /**
     * @return router
     */
    public function get_router() {
        return $this->router;
    }

    /**
     * Entry method for handling a action based request
     *
     * @param string $action The action to handle
     */
    public function handle($action) {
        $callback = $this->resolve_controller_callback($action);
        $response = $this->generate_response($callback);
        $this->send_response($response);
    }

    /**
     * Given an action, find the controller and method responsible for
     * handling the action.
     *
     * In addition, send some extra variables to the controller
     * and initialize it.
     *
     * @param string $action
     * @return array
     */
    public function resolve_controller_callback($action) {
        /** @var $controller \mod_hsuforum\controller\controller_abstract */
        list($controller, $method) = $this->router->route_action($action);

        $controller->set_renderer($this->renderer);
        $controller->init($action);

        return array($controller, $method);
    }

    /**
     * Given a controller callback, execute the callback
     * and handle the return value or resulting output
     * buffer.
     *
     * @param callable $callback
     * @return string
     * @throws \coding_exception
     */
    public function generate_response($callback) {
        ob_start();
        $response = call_user_func($callback);
        $buffer   = trim(ob_get_contents());
        ob_end_clean();

        if (!empty($response) and !empty($buffer)) {
            throw new \coding_exception('Mixed return output and buffer output', "Buffer: $buffer");
        } else if (!empty($buffer)) {
            $response = $buffer;
        }
        return $response;
    }

    /**
     * Automatically wraps non-empty responses with
     * header/footer, etc.
     *
     * @param string|response_interface $response
     */
    public function send_response($response) {
        global $OUTPUT;

        if ($response instanceof response_interface) {
            $response->send();
        } else if (!empty($response)) {
            echo $OUTPUT->header();
            echo $response;
            echo $OUTPUT->footer();
        }
    }
}
