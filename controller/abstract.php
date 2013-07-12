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
 * Abstract Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class hsuforum_controller_abstract {
    /**
     * @var mod_hsuforum_renderer
     */
    protected $renderer;

    public function __construct() {
        global $PAGE;

        $this->set_renderer(
            $PAGE->get_renderer('mod_hsuforum')
        );
    }

    /**
     * @param \mod_hsuforum_renderer $renderer
     * @return hsuforum_controller_abstract
     */
    public function set_renderer(mod_hsuforum_renderer $renderer) {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * @return \mod_hsuforum_renderer
     */
    public function get_renderer() {
        return $this->renderer;
    }

    /**
     * Generate a new URL to this page
     *
     * @param array $extraparams
     * @return moodle_url
     */
    public function new_url($extraparams = array()) {
        global $PAGE;

        $url = $PAGE->url;
        $url->params($extraparams);
        return $url;
    }

    /**
     * Initialize the controller before the given
     * action is invoked.
     *
     * @param string $action
     */
    public function init($action) {
        $this->require_capability($action);
    }

    /**
     * Do any security checks needed for the passed action
     *
     * @abstract
     * @param string $action
     */
    abstract public function require_capability($action);
}