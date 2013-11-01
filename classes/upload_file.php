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
 * Upload File
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_file {
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
    protected $element;

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
     * PHP upload errors to Moodle strings
     *
     * @var array
     */
    protected $errors = array(
        UPLOAD_ERR_INI_SIZE   => array('upload_error_ini_size', 'repository_upload'),
        UPLOAD_ERR_FORM_SIZE  => array('upload_error_form_size', 'repository_upload'),
        UPLOAD_ERR_PARTIAL    => array('upload_error_partial', 'repository_upload'),
        UPLOAD_ERR_NO_FILE    => array('upload_error_no_file', 'repository_upload'),
        UPLOAD_ERR_NO_TMP_DIR => array('upload_error_no_tmp_dir', 'repository_upload'),
        UPLOAD_ERR_CANT_WRITE => array('upload_error_cant_write', 'repository_upload'),
        UPLOAD_ERR_EXTENSION  => array('upload_error_extension', 'repository_upload'),
    );

    /**
     * @param \context_module $context
     * @param array $options
     * @param string $element
     */
    public function __construct(\context_module $context, array $options, $element) {
        $this->options = $options;
        $this->element = $element;
        $this->context = $context;
    }

    /**
     * Returns an array of files
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_files() {
        if (empty($_FILES) || !isset($_FILES[$this->element])) {
            throw new \coding_exception('Cannot use this method when no files were uploaded');
        }
        $files = array();
        foreach ($_FILES[$this->element] as $name => $values) {
            foreach ($values as $key => $value) {
                $files[$key][$name] = $value;
            }
        }
        return $files;
    }

    /**
     * Upload any files for a given post
     *
     * @param $postid
     * @param null|int $license
     * @return null|\stored_file
     */
    public function process_file_upload($postid, $license = null) {
        if (!$this->was_file_uploaded()) {
            return null;
        }
        $this->validate_files();
        return $this->save_files($postid, $license);
    }

    /**
     * Determine if a file was uploaded or not
     *
     * @return bool
     */
    public function was_file_uploaded() {
        if (empty($_FILES) || !isset($_FILES[$this->element])) {
            return false;
        }
        foreach ($this->get_files() as $file) {
            if ($file['error'] != UPLOAD_ERR_NO_FILE && $file['size'] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate all of the uploaded files
     *
     * @throws \moodle_exception
     */
    public function validate_files() {
        if (!isset($_FILES[$this->element])) {
            throw new moodle_exception('nofile');
        }
        $files    = $this->get_files();
        $maxfiles = $this->options['maxfiles'];
        if ($maxfiles != -1 && count($files) > $maxfiles) {
            throw new moodle_exception('err_maxfiles', 'form', '', $maxfiles);
        }
        foreach ($files as $file) {
            $this->validate_file($file);
        }
    }

    /**
     * Validate the uploaded file
     *
     * @param array $file
     * @throws \moodle_exception
     * @throws \file_exception
     */
    protected function validate_file(array $file) {
        global $CFG;

        require_once($CFG->dirroot.'/repository/lib.php');

        if (!empty($file['error'])) {
            $errorcode = $file['error'];
            if (array_key_exists($errorcode, $this->errors)) {
                list($error, $component) = $this->errors[$errorcode];
                throw new moodle_exception($error, $component);
            }
            throw new moodle_exception('nofile');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new moodle_exception('notuploadedfile', 'hsuforum');
        }
        if (!$this->validate_file_contents($file['tmp_name'])) {
            throw new moodle_exception('upload_error_invalid_file', 'repository_upload', '', clean_param($file['name'], PARAM_FILE));
        }
        $maxbytes = $this->options['maxbytes'];
        if (($maxbytes !== -1) && (filesize($file['tmp_name']) > $maxbytes)) {
            throw new \file_exception('maxbytes');
        }

        \repository::antivir_scan_file($file['tmp_name'], $file['name'], true);
    }

    /**
     * Copied from repository_upload::check_valid_contents
     *
     * Checks the contents of the given file is not completely NULL - this can happen if a
     * user drags & drops a folder onto a filemanager / filepicker element
     *
     * @param string $filepath full path (including filename) to file to check
     * @return true if file has at least one non-null byte within it
     */
    protected function validate_file_contents($filepath) {
        $buffersize = 4096;

        $fp = fopen($filepath, 'r');
        if (!$fp) {
            return false; // Cannot read the file - something has gone wrong
        }
        while (!feof($fp)) {
            // Read the file 4k at a time
            $data = fread($fp, $buffersize);
            if (preg_match('/[^\0]+/', $data)) {
                fclose($fp);
                return true; // Return as soon as a non-null byte is found
            }
        }
        // Entire file is NULL
        fclose($fp);
        return false;
    }

    /**
     * @param int $postid
     * @param null|int $license
     * @return \stored_file[]
     */
    protected function save_files($postid, $license = null) {
        $stored = array();
        foreach ($this->get_files() as $file) {
            $stored[] = $this->save_file($file, $postid, $license);
        }
        return $stored;
    }

    /**
     * @param array $file
     * @param int $postid
     * @param null|int $license
     * @return \stored_file
     */
    protected function save_file(array $file, $postid, $license = null) {
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
        $record->source    = clean_param($file['name'], PARAM_FILE);
        $record->filename  = $this->get_unused_filename($record, $record->source);

        return get_file_storage()->create_file_from_pathname($record, $file['tmp_name']);
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
        while($fs->file_exists($record->contextid, $record->component, $record->filearea, $record->itemid, $record->filepath, $filename)) {
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