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
 * Post attachments handler
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attachments {
    /**
     * @var object
     */
    protected $forum;

    /**
     * @var \context_module
     */
    protected $context;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $component = 'mod_hsuforum';

    /**
     * @var string
     */
    protected $filearea = 'attachment';

    /**
     * @var string
     */
    protected $filepath = '/';

    /**
     * File names to be deleted
     *
     * @var array
     */
    protected $deletefiles;

    /**
     * @param object $forum
     * @param \context_module $context
     * @param array $deletefiles File names to be deleted
     */
    public function __construct($forum, \context_module $context, array $deletefiles = array()) {
        $this->forum       = $forum;
        $this->context     = $context;
        $this->deletefiles = $deletefiles;
    }

    /**
     * Are attachments allowed in this forum and for this current user?
     *
     * @return bool
     */
    public function attachments_allowed() {
        return (!empty($this->forum->maxattachments) && $this->forum->maxbytes != 1 && has_capability('mod/hsuforum:createattachment', $this->context)); // 1 = No attachments at all.
    }

    /**
     * @param $postid
     * @param string $sort
     * @return \stored_file[]
     */
    protected function get_all_attachments($postid, $sort = 'timemodified') {
        return get_file_storage()->get_area_files($this->context->id, $this->component, $this->filearea, $postid, $sort, false);
    }

    /**
     * Returns post attachments
     *
     * Filters out any attachments that are set to be deleted.
     *
     * @param $postid
     * @param string $sort
     * @return \stored_file[]
     */
    public function get_attachments($postid, $sort = 'timemodified') {
        $files = array();
        foreach ($this->get_all_attachments($postid, $sort) as $key => $file) {
            if (!in_array($file->get_filename(), $this->deletefiles)) {
                $files[$key] = $file;
            }
        }
        return $files;
    }

    /**
     *
     * @param string $name The file name
     * @param string $path The absolute path to the file
     * @param int $postid
     * @param null|int $license
     * @return \stored_file
     */
    public function add_attachment($name, $path, $postid, $license = null) {
        global $CFG, $USER;

        if ($license == null) {
            $license = $CFG->sitedefaultlicense;
        }
        $record            = new \stdClass();
        $record->filearea  = $this->filearea;
        $record->component = $this->component;
        $record->filepath  = $this->filepath;
        $record->itemid    = $postid;
        $record->license   = $license;
        $record->author    = fullname($USER, true);
        $record->contextid = $this->context->id;
        $record->userid    = $USER->id;
        $record->source    = clean_param($name, PARAM_FILE);
        $record->filename  = $this->get_unused_filename($record, $record->source);

        return get_file_storage()->create_file_from_pathname($record, $path);
    }

    /**
     * Delete attachments that are scheduled to be deleted.
     *
     * Yeah, this is a weird method.
     *
     * @param $postid
     */
    public function delete_attachments($postid) {
        if (empty($this->deletefiles)) {
            return;
        }
        foreach ($this->get_all_attachments($postid) as $file) {
            if (in_array($file->get_filename(), $this->deletefiles)) {
                $file->delete();
            }
        }
    }

    /**
     * Get a name for the file that does not conflict with any other files
     *
     * @param object $record File record
     * @param string $filename The file name to use
     * @return string
     */
    protected function get_unused_filename($record, $filename) {
        $fs    = get_file_storage();
        $count = 2;
        while ($fs->file_exists($record->contextid, $record->component, $record->filearea, $record->itemid, $record->filepath, $filename)) {
            $filename = $this->append_suffix($filename, '_'.$count);
        }
        return $filename;
    }

    /**
     * Append suffix to file name
     *
     * @param string $filename
     * @param string $suffix
     * @return string
     */
    protected function append_suffix($filename, $suffix) {
        $pathinfo = pathinfo($filename);
        if (empty($pathinfo['extension'])) {
            return $filename.$suffix;
        } else {
            return $pathinfo['filename'].$suffix.'.'.$pathinfo['extension'];
        }
    }
}
