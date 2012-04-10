<?php
/**
 * Discussion existing user selector
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/abstract.php');

class hsuforum_userselector_discussion_existing extends hsuforum_userselector_discussion_abstract {

    public function __construct($name, $options) {
        parent::__construct($name, $options);

        $this->extrafields = explode(',', user_picture::fields());
    }

    /**
     * Get file path to this class
     *
     * @return string
     */
    public function get_filepath() {
        return '/mod/hsuforum/lib/userselector/discussion/existing.php';
    }

    public function find_users($search) {
        return array(
            get_string("existingsubscribers", 'hsuforum') =>
            $this->get_repo()->get_subscribed_users($this->forum, $this->discussion, $this->context, $this->currentgroup, $this->required_fields_sql('u'), $this->search_sql($search, 'u'))
        );
    }
}