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
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

require_once(__DIR__.'/attachments.php');

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_file {
    /**
     * @var attachments
     */
    protected $attachments;

    /**
     * File upload options
     *
     * @var array
     */
    protected $options;

    /**
     * File upload element name
     *
     * @var string
     */
    protected $element;

    /**
     * Use is_uploaded_file or file_exist
     *
     * @var boolean
     */
    protected $usestub;

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
     * @param attachments $attachments
     * @param array $options File upload options
     * @param string $element File upload element name
     * @param boolean $stub Indicates if we need a stub or not
     */
    public function __construct(attachments $attachments, array $options, $stub = false, $element = 'attachment') {
        $this->options     = $options;
        $this->element     = $element;
        $this->attachments = $attachments;
        $this->usestub     = $stub;
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
     * @return \stored_file[]
     */
    public function process_file_upload($postid, $license = null) {
        if ($this->was_file_uploaded()) {
            $this->validate_files($postid);
            $this->save_files($postid, $license);
        }
        // This only deletes attachments that the user selected to delete.
        $this->attachments->delete_attachments($postid);

        return $this->attachments->get_attachments($postid);

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
        if (!$this->attachments->attachments_allowed()) {
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
    public function validate_files($postid = 0) {
        if (!isset($_FILES[$this->element])) {
            throw new moodle_exception('nofile');
        }
        $files    = $this->get_files();
        $maxfiles = $this->options['maxfiles'];
        if ($maxfiles != -1) {
            $total = count($files);
            if (!empty($postid)) {
                $total += count($this->attachments->get_attachments($postid));
            }
            if ($total > $maxfiles) {
               throw new moodle_exception('err_maxfiles', 'form', '', $maxfiles);
            }
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
        if (!$this->is_uploaded_function($file['tmp_name'])) {
            throw new moodle_exception('notuploadedfile', 'hsuforum');
        }
        if (!$this->validate_file_contents($file['tmp_name'])) {
            throw new moodle_exception('upload_error_invalid_file', 'repository_upload', '', clean_param($file['name'], PARAM_FILE));
        }
        $maxbytes = $this->options['maxbytes'];
        if (($maxbytes !== -1) && (filesize($file['tmp_name']) > $maxbytes)) {
            $message = new \stdClass();
            $message->file = '\'' . $file['name'] . '\'';
            $message->size = $this->format_bytes($maxbytes);
            throw new \file_exception('maxbytesfile', $message);
        }

        \core\antivirus\manager::scan_file($file['tmp_name'], $file['name'], true);
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
     */
    protected function save_files($postid, $license = null) {
        foreach ($this->get_files() as $file) {
            $this->attachments->add_attachment($file['name'], $file['tmp_name'], $postid, $license);
        }
    }

    /**
     * Function to convert an integer that represents the max size of a file in bytes
     * to a more human readable format.
     * @param int $maxsize max size in bytes that a file can take.
     * @return string size of the file on B, KB, MB, GB or TB.
     */
    protected function format_bytes($maxsize){
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $exp = floor(log($maxsize, 1024));
        return round($maxsize / pow(1024, $exp), 2) . $units[$exp];
    }

    /**
     * Tells whether the file was uploaded via HTTP POST or use
     * a different approach if it nos possible to do the POST validation
     * @param string $filename the filename being checked
     * @return bool true on success or false on failure.
     */
    protected function is_uploaded_function($filename){
        if (!$this->usestub){
            return is_uploaded_file($filename);
        }
        return file_exists($filename);
    }

}
