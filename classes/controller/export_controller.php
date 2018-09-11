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
 * Export Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\controller;

use mod_hsuforum\export\csv_format;
use mod_hsuforum\export\export_manager;
use mod_hsuforum\export\file_adapter;
use mod_hsuforum\export\print_adapter;
use mod_hsuforum\export\text_format;
use mod_hsuforum\form\export_form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/controller_abstract.php');
require_once(dirname(__DIR__).'/form/export_form.php');
require_once(dirname(__DIR__).'/export/export_manager.php');
require_once(dirname(__DIR__).'/export/file_adapter.php');
require_once(dirname(__DIR__).'/export/print_adapter.php');
require_once(dirname(__DIR__).'/export/csv_format.php');
require_once(dirname(__DIR__).'/export/text_format.php');

class export_controller extends controller_abstract {
    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        global $PAGE;

        require_capability('mod/hsuforum:viewdiscussion', $PAGE->context);

        if (is_guest($PAGE->context)) {
            print_error('noguest');
        }
    }

    /**
     * Export UI
     */
    public function export_action() {
        global $PAGE;

        // Must fetch plain object
        $cm    = get_coursemodule_from_id('hsuforum', $PAGE->cm->id, $PAGE->course->id, false, MUST_EXIST);
        $mform = new export_form($this->new_url(), (object) array(
            'cm'    => $cm,
            'forum' => $PAGE->activityrecord,
        ));

        if ($mform->is_cancelled()) {
            redirect(new \moodle_url('/mod/hsuforum/view.php', array('id' => $cm->id)));
        } else if ($data = $mform->get_data()) {
            if ($data->format == 'print') {
                $adapter = new print_adapter($cm);
            } else {
                if ($data->format == 'csv') {
                    $format = new csv_format();
                } else if ($data->format == 'text') {
                    $format = new text_format();
                } else {
                    throw new \coding_exception('Unrecognized export format: '.$data->format);
                }
                $adapter = new file_adapter($cm, $format, (boolean) $data->attachments);
            }
            list($discussionid, $userid) = $data->discussionopts;

            $manager = new export_manager($cm, $adapter);
            if (empty($discussionid)) {
                $manager->export_discussions($userid);
            } else {
                $manager->export_discussion($discussionid, $userid);
            }
            die;
        }
        $mform->display();
    }
}
