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
 * File Export Adapter
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\export;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/adapter_interface.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_adapter implements adapter_interface {
    /**
     * @var \stdClass
     */
    protected $cm;

    /**
     * @var format_abstract
     */
    protected $format;

    /**
     * Include attachments in the export or not
     *
     * @var bool
     */
    protected $attachments;

    /**
     * Files to include in the zip archive
     *
     * @var array
     */
    protected $archivefiles = array();

    /**
     * Absolute path to the main export file
     *
     * @var string
     */
    protected $exportfile;

    /**
     * Absolute path to the temporary directory for the export
     *
     * @var string
     */
    protected $tempdirectory;

    /**
     * @param \stdClass $cm
     * @param format_abstract $format
     * @param bool $attachments
     */
    public function __construct($cm, format_abstract $format, $attachments = false) {
        $this->cm = $cm;
        $this->format = $format;
        $this->attachments = $attachments;
    }

    /**
     * Initialization routine
     *
     * @param null|\stdClass $discussion Only passed if exporting a single discussion
     * @return void
     */
    public function initialization($discussion = null) {
        if (!empty($discussion)) {
            $filename = $this->create_file_name($discussion->name);
        } else {
            $filename = $this->create_file_name(hsuforum_get_cm_forum($this->cm)->name);
        }
        $this->tempdirectory = $this->create_temp_directory();
        $this->exportfile    = $this->tempdirectory.'/'.$filename;
        $this->archivefiles  = array($filename => $this->exportfile);

        $this->format->init($this->exportfile);
    }

    /**
     * Send a discussion and its posts to the export
     *
     * @param \stdClass $discussion
     * @param \stdClass[] $posts
     * @return void
     */
    public function send_discussion($discussion, $posts) {
        $discname = format_string($discussion->name);

        foreach ($posts as $post) {
            $postuser = hsuforum_extract_postuser($post, hsuforum_get_cm_forum($this->cm), \context_module::instance($this->cm->id));
            $author   = fullname($postuser);

            $attachments = $this->process_attachments($post);

            $options          = new \stdClass();
            $options->para    = false;
            $options->trusted = $post->messagetrust;
            $options->context = \context_module::instance($this->cm->id);

            $message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', \context_module::instance($this->cm->id)->id, 'mod_hsuforum', 'post', $post->id);
            $message = format_text($message, $post->messageformat, $options, $this->cm->course);
            $message = \core_text::specialtoascii(html_to_text($message));

            if ($post->id == $discussion->firstpost) {
                $this->format->export_discussion($post->id, $discname, $author, $post->created, $message, $attachments);
            } else {
                $private = '';
                if (!empty($post->privatereply)) {
                    $private = get_string('yes');
                }
                $this->format->export_post($post->id, $discname, format_string($post->subject), $author, $post->created, $message, $attachments, $private);
            }
        }
    }

    /**
     * Exporting is done, wrap things up.
     *
     * @throws \coding_exception
     * @return void
     */
    public function finish() {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');

        $this->format->close();

        if ($this->attachments) {
            $zipname = pathinfo($this->exportfile, PATHINFO_FILENAME).'.zip';
            $zippath = $this->tempdirectory.'/'.$zipname;

            $zip = new \zip_packer();
            if (!$zip->archive_to_pathname($this->archivefiles, $zippath)) {
                throw new \coding_exception('Failed to create zip archive');
            }
            send_file($zippath, $zipname, 0, 0, false, true, '', true);
        } else {
            send_file($this->exportfile, pathinfo($this->exportfile, PATHINFO_BASENAME), 0, 0, false, true, '', true);
        }
    }

    /**
     * Get an array of file attachment names.
     *
     * In addition, if exporting attachments, then add
     * the attachments to the file pool.
     *
     * @param \stdClass $post
     * @return array
     */
    protected function process_attachments($post) {
        $attachments = array();
        if (empty($post->attachment)) {
            return $attachments;
        }
        /** @var \stored_file[] $files */
        $files = get_file_storage()->get_area_files(\context_module::instance($this->cm->id)->id, 'mod_hsuforum', 'attachment', $post->id, 'timemodified', false);
        foreach ($files as $file) {
            if ($this->attachments) {
                $filename = $this->resolve_file_name($file);
                $attachments[] = $filename;
                $this->archivefiles['attachments/'.$filename] = $file;
            } else {
                $attachments[] = $file->get_filename();
            }
        }
        return $attachments;
    }

    /**
     * Given a stored file, get its file name, but make
     * sure that it does not conflict with any other
     * attachments in the file pool.
     *
     * @param \stored_file $file
     * @return string
     */
    protected function resolve_file_name(\stored_file $file) {
        $filename = $file->get_filename();
        $pathinfo = pathinfo($filename);
        $count    = 1;
        while (array_key_exists('attachments/'.$filename, $this->archivefiles)) {
            $filename = $pathinfo['filename'].'_'.$count.'.'.$pathinfo['extension'];
            $count++;
        }
        return $filename;
    }

    /**
     * Create the temporary directory
     *
     * @return string
     */
    protected function create_temp_directory() {
        return make_request_directory();
    }

    /**
     * Creates a file name
     *
     * @param string $name
     * @return string
     */
    protected function create_file_name($name) {
        global $COURSE;

        $filename  = trim(shorten_text(format_string($COURSE->shortname), 50, true, '')).'_';
        $filename .= trim(shorten_text(format_string($name), 100, false, ''));
        $filename .= '_'.userdate(time(), '%Y%m%d', false, false);
        $filename  = str_replace(' ', '_', $filename);
        $filename  = trim(clean_filename($filename), '_');

        return $filename.'.'.$this->format->get_extension();
    }
}
