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
 * Wrapper for the is_uploaded_function
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class checker {

    /**
     * Use the is_upload_function or an equivalent for testing
     *
     * @var boolean
     */
    private $stub;

    /**
     * checker constructor.
     * @param Boolean $stub indicate which function to use
     */
    public function __construct($stub) {
        $this->$stub = $stub;
    }

    /**
     * Tells whether the file was uploaded via HTTP POST or use
     * a different approach if it nos possible to do the POST validation
     * @param string $filename the filename being checked
     * @return bool true on success or false on failure.
     */
    public function is_uploaded_function($filename){
        if ($this->stub){
            return is_uploaded_file($filename);
        }
        return file_exists($filename);
    }

}
