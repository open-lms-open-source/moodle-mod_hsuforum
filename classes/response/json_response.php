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
 * JSON Response
 *
 * Used by controllers and the kernel to handle
 * responses.
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\response;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/response_interface.php');

/**
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class json_response implements response_interface {
    /**
     * @var mixed
     */
    protected $data;

    /**
     * @param mixed $data
     */
    function __construct($data) {
        $this->data = $data;
    }

    /**
     * Convert data attribute to a string
     *
     * @param \core_renderer $output
     * @return string
     */
    protected function data_to_string(\core_renderer $output) {
        if ($this->data instanceof \Exception) {
            $info = get_exception_info($this->data);
            return $output->fatal_error($info->message, $info->moreinfourl, $info->link, $info->backtrace, $info->debuginfo);
        } else {
            return json_encode($this->data);
        }
    }

    /**
     * Send the response to the browser
     *
     * @return void
     */
    public function send() {
        global $PAGE;

        /** @var \core_renderer $output */
        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_AJAX);

        echo $output->header();
        echo $this->data_to_string($output);
        echo $output->footer();
    }
}
