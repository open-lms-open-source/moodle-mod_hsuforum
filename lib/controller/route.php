<?php
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