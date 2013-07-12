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
 * Routes the action parameter to controller methods
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class hsuforum_lib_controller_route {
    /**
     * @var hsuforum_controller_abstract[]
     */
    protected $controllers = array();

    /**
     * Add a controller to the router
     *
     * The router routes actions to the controllers
     * by first come, first serve.
     *
     * @param hsuforum_controller_abstract $controller
     */
    public function add_controller(hsuforum_controller_abstract $controller) {
        $this->controllers[] = $controller;
    }

    /**
     * @return array|hsuforum_controller_abstract[]
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
     * @return string|void|boolean|null
     * @throws coding_exception
     */
    public function action($action) {
        $method = "{$action}_action";
        foreach ($this->get_controllers() as $controller) {
            $reflection = new ReflectionClass($controller);
            if (!$reflection->hasMethod($method)) {
                continue;
            } else if ($reflection->getMethod($method)->isPublic() !== true) {
                throw new coding_exception("The controller callback is not public: $method");
            }
            $controller->init($action);
            return $controller->$method();
        }
        throw new coding_exception("Unable to handle request for $method");
    }
}