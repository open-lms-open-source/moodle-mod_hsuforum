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

        $contextkey = $userid.'_'.\get_class($context).'_'.$context->id.'_'.$capability;
        if (!isset($caps[$contextkey])) {
            $caps[$contextkey] = \has_capability($capability, $context, $user);
        }

        return $caps[$contextkey];
    }

    /**
     * Check if the user has all the capabilities in a list.
     *
     * This is just a utility method that calls has_capability in a loop. Try to put
     * the capabilities that fewest users are likely to have first in the list for best
     * performance.
     *
     * @category access
     * @see has_capability()
     *
     * @param array $capabilities an array of capability names.
     * @param context $context the context to check the capability in. You normally get this with instance method of a context class.
     * @param integer|stdClass $user A user id or object. By default (null) checks the permissions of the current user.
     * @return boolean true if the user has all of these capabilities. Otherwise false.
     */
    public static function cached_has_all_capabilities(array $capabilities, context $context, $user = null) {
        foreach ($capabilities as $capability) {
            if (!self::cached_has_capability($capability, $context, $user)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Get string from ram cache.
     *
     * @param $identifier
     * @param string $component
     * @param null $a
     * @return mixed
     */
    public static function cached_get_string($identifier, $component = '', $a = null){
        static $cache = [];

        $hashkey = $identifier.$component;
        if ($a !== null) {
            if (is_string($a) || is_int($a)) {
                $hashkey .= $a;
            } else {
                $hashkey .= serialize($a);
            }
        }

        if (strlen($hashkey)>32){
            // Use md5 to reduce length.
            // Collision chances are super low -
            // http://stackoverflow.com/questions/201705/how-many-random-elements-before-md5-produces-collisions
            $hashkey = md5($hashkey);
        }

        if (!isset($cache[$hashkey])) {
            $cache[$hashkey] = get_string($identifier, $component, $a);
        }

        return $cache[$hashkey];
    }
}