<?php
/**
 * View Posters Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/abstract.php');
require_once(dirname(__DIR__).'/lib/table/posters.php');

class hsuforum_controller_posters extends hsuforum_controller_abstract {
    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        // Anyone can view
    }

    /**
     * View Posters
     */
    public function viewposters_action() {
        global $PAGE, $OUTPUT;

        $table = new hsuforum_lib_table_posters('mod_hsuforum_viewposters');
        $table->define_baseurl($PAGE->url->out());
        $table->set_attribute('class', 'generaltable generalbox hsuforum_viewposters');
        $table->column_class('userpic', 'col_userpic');

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('viewposters', 'hsuforum'));
        $table->out('25', false);
        echo $OUTPUT->footer();
    }
}