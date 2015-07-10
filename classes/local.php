<?php
// This file is part of the Moodle Rooms hsuforum
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
//

namespace mod_hsuforum;

defined('MOODLE_INTERNAL') || die;

class local {
    /**
     * Same as has_capability but cached to memory
     *
     * @param $capability
     * @param context $context
     * @param null $user
     */
    public static function cached_has_capability($capability, $context, $user = null) {
        global $USER;

        if (empty($capability)) {
            throw new \Exception('Error - capability is empty');
        }

        if (empty($user)) {
            $user = $USER;
        }

        static $caps = [];

        $userid = is_object($user) ? $user->id : $user;

        $contextkey = $userid.'_'.$context->id.'_'.$capability;
        if (!isset($caps[$contextkey])) {
            $caps[$contextkey] = has_capability($capability, $context, $user);
        }

        return $caps[$contextkey];
    }

    /**
     * Prevent name from exceeding 255 chars.
     */
    public static function shorten_post_name($name) {
        $strre = get_string('re', 'hsuforum');
        if (\core_text::strlen($name) > 255) {
            $shortened = shorten_text($name, 255);
            if (trim(str_ireplace($strre, '', $shortened)) === '...' || \core_text::strlen($shortened) > 255) {
                // Make a 2nd pass with the 'exact' param true, as shortening on word boundary failed or exceeded 255 chars.
                $shortened = shorten_text($name, 255, true);
            }
            $name = $shortened;
        }
        return $name;
    }

}