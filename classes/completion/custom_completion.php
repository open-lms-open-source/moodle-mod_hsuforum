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

declare(strict_types=1);

namespace mod_hsuforum\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the hsuforum activity.
 *
 * Class for defining mod_hsuforum's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given hsuforum instance and a user.
 *
 * @package mod_hsuforum
 * @copyright Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $hsuforumid = $this->cm->instance;

        if (!$hsuforum = $DB->get_record('hsuforum', ['id' => $hsuforumid])) {
            throw new \moodle_exception('Unable to find hsuforum with id ' . $hsuforumid);
        }

        $postcountparams = ['userid' => $userid, 'hsuforumid' => $hsuforumid];
        $postcountsql = "SELECT COUNT(*)
                           FROM {hsuforum_posts} fp
                           JOIN {hsuforum_discussions} fd ON fp.discussion = fd.id
                          WHERE fp.userid = :userid
                            AND fd.forum = :hsuforumid";

        if ($rule == 'completiondiscussions') {
            $status = $hsuforum->completiondiscussions <=
                $DB->count_records('hsuforum_discussions', ['forum' => $hsuforumid, 'userid' => $userid]);
        } else if ($rule == 'completionreplies') {
            $status = $hsuforum->completionreplies <=
                $DB->get_field_sql($postcountsql . ' AND fp.parent <> 0', $postcountparams);
        } else if ($rule == 'completionposts') {
            $status = $hsuforum->completionposts <= $DB->get_field_sql($postcountsql, $postcountparams);
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completiondiscussions',
            'completionreplies',
            'completionposts',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $completiondiscussions = $this->cm->customdata['customcompletionrules']['completiondiscussions'] ?? 0;
        $completionreplies = $this->cm->customdata['customcompletionrules']['completionreplies'] ?? 0;
        $completionposts = $this->cm->customdata['customcompletionrules']['completionposts'] ?? 0;

        return [
            'completiondiscussions' => get_string('completiondetail:discussions', 'hsuforum', $completiondiscussions),
            'completionreplies' => get_string('completiondetail:replies', 'hsuforum', $completionreplies),
            'completionposts' => get_string('completiondetail:posts', 'hsuforum', $completionposts),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completiondiscussions',
            'completionreplies',
            'completionposts',
            'completionusegrade',
        ];
    }
}
