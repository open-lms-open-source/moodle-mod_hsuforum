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
 * @author    Jonathan Garcia Gomez jonathan.garcia@blackboard.com
 * @copyright Blackboard 2017
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * JS code to retrive advanced editor for the hsuforum.
 */
define(['jquery', 'core/notification', 'core/ajax', 'core/templates', 'core/fragment', 'core/str'],
    function($, notification, ajax, templates, fragment) {
        return {

            /**
             * Througth the fragment API brings the html and JS content for a new instance of the
             * advanced editor.
             * @param {string} selector Target fot the advanced editor. The div target will be hidden when this loads up.
             * @param {int} cmid  Course module ID, necessary to create a new instance of the editor.
             */
            advancedEditor: function(selector, cmid) {

                /**
                 * Put the editor into the form.
                 * @param {string} action Action to be performed, load up the editor, show it or hide it.
                 * @param {jQuery} editor Could be Atto or TinyMCE
                 * @param {jQuery} parent  Parent node for the action trigger element.
                 * @param {int} cmid Course module ID
                 */
                var setEditor = function (action, editor, parent, cmid) {
                    var simple = parent.find('div[id^="editor-target-container-"].hsuforum-textarea');
                    var advanced = parent.find('div[id^="editor-target-container-"].editor_atto_content');
                    var itemid = parent.find('input[name="itemid"]');
                    var draftitemid = 0;
                    var posttype = parent.find('#hsuforum-post-type');
                    // If we are adding a discussion or a reply, we need a new fresh draft area, so send 0 to create a new one.
                    if (posttype.attr('value') === 'edit') {
                        draftitemid = itemid.val();
                    }
                    if (action === 'advanced') {
                        var contextid = $("input[name='contextid']").val();
                        var id = simple.attr('id');
                        var params, checkEditArea;
                        params = {contextid: contextid, cmid: cmid, id: id, draftitemid: draftitemid};
                        fragment.loadFragment('mod_hsuforum', 'editor', contextid, params).done(
                            function(html, js) {
                                templates.replaceNodeContents($('#editor-info'), html, js);
                                parent.find('.hsuforum-use-advanced').text(M.util.get_string('hideadvancededitor', 'hsuforum'));
                                if (simple.html().length > 0) {
                                    checkEditArea = setInterval(function(){
                                        var container = parent.find('div[id^="editor-target-container-"].editor_atto_content');
                                        if (container.length == 1) {
                                            container.html(simple.html());
                                            clearInterval(checkEditArea);
                                        }
                                    }, 100);
                                }
                            }
                        );
                    } else if (action === 'simple') {
                        simple.html(advanced.html());
                        simple.show();
                        editor.hide();
                        parent.find('.hsuforum-use-advanced').text(M.util.get_string('useadvancededitor', 'hsuforum'));
                    } else if (action === 'show') {
                        parent.find('.hsuforum-use-advanced').text(M.util.get_string('hideadvancededitor', 'hsuforum'));
                        advanced.html(simple.html());
                        simple.hide();
                        editor.show();
                    }
                };

                /**
                 * Update the form with the advanced editor or hide it.
                 *
                 * @param {jQuery} parent  Parent node for the action trigger element.
                 * @param {int} cmid Course module ID
                 */
                var updateEditor = function(parent, cmid) {
                    var action = 'advanced';
                    var atto = parent.find('.editor_atto');
                    var tinymce = parent.find('.mceEditor');
                    var editor = '';
                    if (atto.length === 1 || tinymce.length === 1) {
                        if (parent.find('.hsuforum-use-advanced').text() === M.util.get_string('useadvancededitor', 'hsuforum'))
                        {
                            action = 'show';
                        } else {
                            action = 'simple';
                        }
                    }

                    editor = atto.length ? atto : tinymce;
                    setEditor(action, editor, parent, cmid);
                };

                $(selector).on({
                    click: function(e) {
                        e.preventDefault();
                        // Find the node parent of the "Use advanced aditor" button for TinyMCE or Atto.
                        var parent = $(e.target).parent('.hsuforum-post-body');
                        if (parent.length === 0) {
                            // Atto specific.
                            parent = $(e.target).parent('.editor_atto_wrap');
                        }
                        updateEditor(parent, cmid);
                    }
                }, '.hsuforum-use-advanced');
            },
            /**
             * Initialise.
             * @param {string} selector Target fot the advanced editor. The div target will be hidden when this loads up.
             * @param {int} cmid  Course module ID, necessary to create a new instance of the editor.
             *
             */
            initialize: function(selector, cmid) {
                this.advancedEditor(selector, cmid);
            }
        };
    }
);