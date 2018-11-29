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
 * Controller Router
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\controller;

use coding_exception;
use SplObjectStorage;

defined('MOODLE_INTERNAL') || die();

/**
 * Matches an action to a controller method.
 * Can work with multiple controllers, first controller
 * that matches the action wins.
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class router {
    /**
     * Holds all the controller objects
     *
     * @var SplObjectStorage
     */
    protected $controllers;

    public function __construct() {
        $this->controllers = new SplObjectStorage();
    }

    /**
     * Add a controller to the router
     *
     * The router routes actions to the controllers
     * by first come, first serve.
     *
     * @param object $controller
     * @return $this
     */
    public function add_controller($controller) {
        $this->controllers->attach($controller);
        return $this;
    }

    /**
     * @return SplObjectStorage
     */
    public function get_controllers() {
        return $this->controllers;
    }

    /**
     * Routes an action.
     *
     * The router routes actions to the controllers
     * by first come, first serve.
     *
     * @param $action
     * @return array The controller and method to execute
     * @throws coding_exception
     */
    public function route_action($action) {
        $method = "{$action}_action";
        foreach ($this->controllers as $controller) {
            $reflection = new \ReflectionClass($controller);
            if (!$reflection->hasMethod($method)) {
                continue;
            } else if ($reflection->getMethod($method)->isPublic() !== true) {
                throw new coding_exception("The controller callback is not public: $method");
            }
            return array($controller, $method);
        }
        throw new coding_exception("Unable to handle request for $method");
    }
}
