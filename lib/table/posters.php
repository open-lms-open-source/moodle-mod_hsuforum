<?php
/**
 * View Posters Table
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/tablelib.php');

class hsuforum_lib_table_posters extends table_sql {
    function __construct($uniqueid) {
        global $PAGE;

        parent::__construct($uniqueid);

        $this->define_columns(array('userpic', 'fullname', 'posts', 'replies', 'substantive'));
        $this->define_headers(array('', get_string('fullnameuser'), get_string('posts', 'hsuforum'), get_string('replies', 'hsuforum'), get_string('substantive', 'hsuforum')));

        $fields = user_picture::fields('u', null, 'id', 'picture');
        $params = array('forumid' => $PAGE->activityrecord->id);

        $this->set_sql(
            "$fields, u.firstname, u.lastname, COUNT(*) AS total, SUM(CASE WHEN p.parent = 0 THEN 1 ELSE 0 END) AS posts, SUM(CASE WHEN p.parent != 0 THEN 1 ELSE 0 END) AS replies, 0 AS substantive",
            '{hsuforum_posts} p, {hsuforum_discussions} d, {hsuforum} f, {user} u',
            'u.id = p.userid AND p.discussion = d.id AND d.forum = f.id AND f.id = :forumid GROUP BY p.userid',
            $params
        );
        $this->set_count_sql("
            SELECT COUNT(DISTINCT p.userid)
              FROM {hsuforum_posts} p
              JOIN {user} u ON u.id = p.userid
              JOIN {hsuforum_discussions} d ON d.id = p.discussion
              JOIN {hsuforum} f ON f.id = d.forum
              WHERE f.id = :forumid
        ", $params);
    }

    public function col_userpic($row) {
        global $OUTPUT;
        return $OUTPUT->user_picture(user_picture::unalias($row, null, 'id', 'picture'));
    }
}