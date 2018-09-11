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
 * Discussion date form renderable.
 * @package    mod
 * @subpackage hsuforum
 * @author    gthomas2
 * @copyright Copyright (c) 2015 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\renderables;
use mod_hsuforum\form\date_form;

defined('MOODLE_INTERNAL') || die();

class discussion_dateform implements \renderable {
    /**
     * Output html for this renderable?
     * @var bool $output
     */
    public $output = false;

    /**
     * @var date_form
     */
    protected $dateform = null;

    /**
     * @param \context_module $context
     * @param null|stdClass $discussion
     * @throws \coding_exception
     */
    public function __construct(\context_module $context, $discussion = null) {
        $config = get_config('hsuforum');
        if (empty($config->enabletimedposts) || !has_capability('mod/hsuforum:viewhiddentimedposts', $context)) {
            return;
        }
        $df = new date_form();
        if (!empty($discussion)) {
            $df->set_data($discussion);
        }
        $this->dateform = $df;
        $this->output = true;
    }

    /**
     * Accessor
     *
     * @return date_form
     */
    public function get_dateform(){
        return $this->dateform;
    }
}
