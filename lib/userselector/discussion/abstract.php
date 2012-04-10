<?php
/**
 * Discussion potential user selector
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__DIR__))).'/repository/discussion.php');

abstract class hsuforum_userselector_discussion_abstract extends user_selector_base {
    /**
     * @var stdClass
     */
    protected $forum = null;

    /**
     * @var stdClass
     */
    protected $discussion = null;

    /**
     * @var context_module
     */
    protected $context = null;

    /**
     * @var int
     */
    protected $currentgroup = null;

    /**
     * @var hsuforum_repository_discussion
     */
    protected $repo;

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $options['accesscontext'] = $options['context'];
        parent::__construct($name, $options);
        if (isset($options['context'])) {
            $this->context = $options['context'];
        }
        if (isset($options['currentgroup'])) {
            $this->currentgroup = $options['currentgroup'];
        }
        if (isset($options['forum'])) {
            $this->forum = $options['forum'];
        }
        if (isset($options['discussion'])) {
            $this->discussion = $options['discussion'];
        }
    }

    /**
     * Get file path to this class
     *
     * @abstract
     * @return string
     */
    abstract public function get_filepath();

    /**
     * Returns an array of options to seralise and store for searches
     *
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        $options['file'] =  $this->get_filepath();
        $options['context'] = $this->context;
        $options['currentgroup'] = $this->currentgroup;
        $options['forum'] = $this->forum;
        $options['discussion'] = $this->discussion;
        return $options;
    }

    /**
     * @param \hsuforum_repository_discussion $repo
     * @return hsuforum_userselector_discussion_abstract
     */
    public function set_repo(hsuforum_repository_discussion $repo) {
        $this->repo = $repo;
        return $this;
    }

    /**
     * @return \hsuforum_repository_discussion
     */
    public function get_repo() {
        if (!$this->repo instanceof hsuforum_repository_discussion) {
            $this->set_repo(new hsuforum_repository_discussion());
        }
        return $this->repo;
    }
}