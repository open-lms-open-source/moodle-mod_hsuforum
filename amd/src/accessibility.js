/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   mod_hsuforum
 * @author    Rafael Becerra rafael.becerrarodriguez@blackboard.com
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * JS code to assign attributes and expected behavior for elements in the Dom regarding accessibility.
 */
define(['jquery'],
    function($) {
        return {
            init: function() {
                // Change pin button class on click to aria-pressed = "true".
                $('.pinbutton.btn.btn-default').click(function() {
                    $(this).attr('aria-pressed', 'true');
                });

                // Add event handler to include space key as user's input to bookmark and substantive.
                var hsuforumThreadFlags = $('a.hsuforum-toggle-bookmark, a.hsuforum-toggle-substantive');

                hsuforumThreadFlags.each(function(){
                    hsuforumThreadFlags.off('keypress').on('keypress', function(e) {
                        e.preventDefault();
                        if (e.keyCode === 32) {
                            e.target.click();
                        }
                    });
                });
            }
        }
    }
);
