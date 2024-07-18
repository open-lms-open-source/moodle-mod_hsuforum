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
 * @package   mod_hsuforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Open LMS (https://www.openlms.net)
 * @author Mark Nielsen
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

use core_grades\component_gradeitems;

class mod_hsuforum_mod_form extends moodleform_mod {

    /** @var string Whether this is graded or rated. Taken from core moodleform_mod. */
    private $gradedorrated = null;

    function definition() {
        global $CFG, $COURSE, $PAGE;

        $config = get_config('hsuforum');

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('forumname', 'hsuforum'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('forumintro', 'hsuforum'));

        if (empty($config->hiderecentposts)) {
            // Display recent posts on course page?
            $mform->addElement('advcheckbox', 'showrecent', get_string('showrecent', 'hsuforum'));
            $mform->addHelpButton('showrecent', 'showrecent', 'hsuforum');
            $mform->setDefault('showrecent', 1);
        }

        $forumtypes = hsuforum_get_hsuforum_types();
        core_collator::asort($forumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('forumtype', 'hsuforum'), $forumtypes);
        $mform->addHelpButton('type', 'forumtype', 'hsuforum');
        $mform->setDefault('type', 'general');

        // Post options.
        $mform->addElement('header', 'postoptshdr', get_string('postoptions', 'hsuforum'));

        // Substantive flag visible?
        $mform->addElement('advcheckbox', 'showsubstantive', get_string('showsubstantive', 'hsuforum'));
        $mform->addHelpButton('showsubstantive', 'showsubstantive', 'hsuforum');
        $mform->setDefault('showsubstantive', 0);

        // Bookmarking flag visible?
        $mform->addElement('advcheckbox', 'showbookmark', get_string('showbookmark', 'hsuforum'));
        $mform->addHelpButton('showbookmark', 'showbookmark', 'hsuforum');
        $mform->setDefault('showbookmark', 0);

        // Allow private replies if checked.
        $mform->addElement('advcheckbox', 'allowprivatereplies', get_string('privatereplies', 'hsuforum'));
        $mform->addHelpButton('allowprivatereplies', 'privatereplies', 'hsuforum');

        // Allow anonymous replies?
        $mform->addElement('advcheckbox', 'anonymous', get_string('allowanonymous', 'hsuforum'));
        $mform->addHelpButton('anonymous', 'allowanonymous', 'hsuforum');

        // Display word count?
        $mform->addElement('advcheckbox', 'displaywordcount', get_string('displaywordcount', 'hsuforum'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'hsuforum');
        $mform->setDefault('displaywordcount', 0);

        // Attachments and word count.
        $mform->addElement('header', 'attachmentshdr', get_string('attachment', 'hsuforum'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $config->maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'hsuforum'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'hsuforum');
        $mform->setDefault('maxbytes', $config->maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'hsuforum'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'hsuforum');
        $mform->setDefault('maxattachments', $config->maxattachments);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionhdr', get_string('subscription', 'hsuforum'));

        $options = hsuforum_get_subscriptionmode_options();
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'hsuforum'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'hsuforum');
        if (isset($CFG->hsuforum_subscription)) {
            $defaultforumsubscription = $CFG->hsuforum_subscription;
        } else {
            $defaultforumsubscription = HSUFORUM_CHOOSESUBSCRIBE;
        }
        $mform->setDefault('forcesubscribe', $defaultforumsubscription);

        if ($CFG->enablerssfeeds && isset($config->enablerssfeeds) && $config->enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'hsuforum');
            $choices[2] = get_string('posts', 'hsuforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'hsuforum');
            if (isset($config->rsstype)) {
                $mform->setDefault('rsstype', $config->rsstype);
            }

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'hsuforum');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
            if (isset($config->rssarticles)) {
                $mform->setDefault('rssarticles', $config->rssarticles);
            }
        }

        $mform->addElement('header', 'discussionlocking', get_string('discussionlockingheader', 'hsuforum'));
        $options = [
            0               => get_string('discussionlockingdisabled', 'hsuforum'),
            1   * DAYSECS   => get_string('numday', 'core', 1),
            1   * WEEKSECS  => get_string('numweek', 'core', 1),
            2   * WEEKSECS  => get_string('numweeks', 'core', 2),
            30  * DAYSECS   => get_string('nummonth', 'core', 1),
            60  * DAYSECS   => get_string('nummonths', 'core', 2),
            90  * DAYSECS   => get_string('nummonths', 'core', 3),
            180 * DAYSECS   => get_string('nummonths', 'core', 6),
            1   * YEARSECS  => get_string('numyear', 'core', 1),
        ];
        $mform->addElement('select', 'lockdiscussionafter', get_string('lockdiscussionafter', 'hsuforum'), $options);
        $mform->addHelpButton('lockdiscussionafter', 'lockdiscussionafter', 'hsuforum');
        $mform->disabledIf('lockdiscussionafter', 'type', 'eq', 'single');

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'hsuforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','hsuforum');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'hsuforum'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'hsuforum');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'hsuforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'hsuforum');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'hsuforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'hsuforum');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_hsuforum_coursemodule_elements();

        $mform->addElement('select', 'gradetype', get_string('gradetype', 'hsuforum'), hsuforum_get_grading_types());
        $mform->setDefault('gradetype', HSUFORUM_GRADETYPE_NONE);
        $mform->setType('gradetype', PARAM_INT);
        $mform->addHelpButton('gradetype', 'gradetype', 'hsuforum');

        $mform->insertElementBefore($mform->removeElement('gradetype'), 'grade');
        $scale = $mform->insertElementBefore($mform->removeElement('scale'), 'grade');
        $scale->setLabel(get_string('gradenoun'));

        // Done abusing this poor fellow...
        $mform->removeElement('grade');

        if ($this->_features->advancedgrading) {
            foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                $mform->disabledIf('advancedgradingmethod_'.$areaname, 'gradetype', 'neq', HSUFORUM_GRADETYPE_MANUAL);
            }
        }
        $mform->disabledIf('gradepass', 'gradetype', 'neq', HSUFORUM_GRADETYPE_MANUAL);

        $reflection = new ReflectionClass($mform);
        $property = $reflection->getProperty('_hideifs');
        $property->setAccessible(true);
        $dependencies = $property->getValue($mform);

        if (isset($dependencies['assessed'])) {
            $key = array_search('scale', $dependencies['assessed']['eq'][0]);
            if ($key !== false) {
                unset($dependencies['assessed']['eq'][0][$key]);
                $property->setValue($mform, $dependencies);
            }
        }
        $mform->disabledIf('gradecat', 'gradetype', 'eq', HSUFORUM_GRADETYPE_NONE);
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

        if (!$this->_features->advancedgrading) {
            /** @var $renderer mod_hsuforum_renderer */
            $renderer = $PAGE->get_renderer('mod_hsuforum');
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_modform', array(HSUFORUM_GRADETYPE_MANUAL), false, $renderer->get_js_module());
        }
    }

    function standard_grading_coursemodule_elements() {
        $this->_features->rating = false;
        $this->standard_hsuforum_grading_coursemodule_elements();
        $this->_features->rating = true;
    }

    /**
     * Adds all the standard elements to a form to edit the settings for an activity module for Hsuforum.
     */
    protected function standard_hsuforum_coursemodule_elements() {
        global $COURSE, $CFG, $DB, $OUTPUT;
        $mform =& $this->_form;

        if (!empty($CFG->core_outcome_enable)) {
            $mform->addElement('header', 'outcomesheader', get_string('outcomes', 'outcome'));
            $mform->addElement('mapoutcome', 'outcomes');
            $mform->addHelpButton('outcomes', 'selectoutcomes', 'outcome');
            if (!has_capability('moodle/outcome:mapoutcomes', $this->context)) {
                $mform->hardFreeze('outcomes');
            }
        }

        $this->_outcomesused = false;
        if ($this->_features->outcomes) {
            if ($outcomes = grade_outcome::fetch_all_available($COURSE->id)) {
                $this->_outcomesused = true;
                $mform->addElement('header', 'modoutcomes', get_string('outcomes', 'grades'));
                foreach($outcomes as $outcome) {
                    $mform->addElement('advcheckbox', 'outcome_'.$outcome->id, $outcome->get_name());
                }
            }
        }

        if ($this->_features->rating) {
            $this->add_hsuforum_rating_settings();
        }

        $mform->addElement('header', 'modstandardelshdr', get_string('modstandardels', 'form'));

        $section = get_fast_modinfo($COURSE)->get_section_info($this->_section);
        $allowstealth = !empty($CFG->allowstealth) && $this->courseformat->allow_stealth_module_visibility($this->_cm, $section);
        if ($allowstealth && $section->visible) {
            $modvisiblelabel = 'modvisiblewithstealth';
        } else if ($section->visible) {
            $modvisiblelabel = 'modvisible';
        } else {
            $modvisiblelabel = 'modvisiblehiddensection';
        }
        $mform->addElement('modvisible', 'visible', get_string($modvisiblelabel), null,
            array('allowstealth' => $allowstealth, 'sectionvisible' => $section->visible, 'cm' => $this->_cm));
        $mform->addHelpButton('visible', $modvisiblelabel);
        if (!empty($this->_cm)) {
            $context = context_module::instance($this->_cm->id);
            if (!has_capability('moodle/course:activityvisibility', $context)) {
                $mform->hardFreeze('visible');
            }
        }

        if ($this->_features->idnumber) {
            $mform->addElement('text', 'cmidnumber', get_string('idnumbermod'));
            $mform->setType('cmidnumber', PARAM_RAW);
            $mform->addHelpButton('cmidnumber', 'idnumbermod');
        }

        if ($this->_features->groups) {
            $options = array(NOGROUPS       => get_string('groupsnone'),
                SEPARATEGROUPS => get_string('groupsseparate'),
                VISIBLEGROUPS  => get_string('groupsvisible'));
            $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $options, NOGROUPS);
            $mform->addHelpButton('groupmode', 'groupmode', 'group');
        }

        if ($this->_features->groupings) {
            // Groupings selector - used to select grouping for groups in activity.
            $options = array();
            if ($groupings = $DB->get_records('groupings', array('courseid'=>$COURSE->id))) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            core_collator::asort($options);
            $options = array(0 => get_string('none')) + $options;
            $mform->addElement('select', 'groupingid', get_string('grouping', 'group'), $options);
            $mform->addHelpButton('groupingid', 'grouping', 'group');
        }

        if (!empty($CFG->enableavailability)) {
            // Add special button to end of previous section if groups/groupings
            // are enabled.
            if ($this->_features->groups || $this->_features->groupings) {
                $mform->addElement('static', 'restrictgroupbutton', '',
                    html_writer::tag('button', get_string('restrictbygroup', 'availability'),
                        array('id' => 'restrictbygroup', 'disabled' => 'disabled', 'class' => 'btn btn-secondary')));
            }

            // Availability loading indicator. See MDL-78607.
            $loadingcontainer = $OUTPUT->container(
                $OUTPUT->render_from_template('core/loading', []),
                'd-flex justify-content-center py-5 icon-size-5',
                'availabilityconditions-loading'
            );
            $mform->addElement('html', $loadingcontainer);

            // Availability field. This is just a textarea; the user interface
            // interaction is all implemented in JavaScript.
            $mform->addElement('header', 'availabilityconditionsheader',
                get_string('restrictaccess', 'availability'));
            // Note: This field cannot be named 'availability' because that
            // conflicts with fields in existing modules (such as assign).
            // So it uses a long name that will not conflict.
            $mform->addElement('textarea', 'availabilityconditionsjson',
                get_string('accessrestrictions', 'availability'));
            // The _cm variable may not be a proper cm_info, so get one from modinfo.
            if ($this->_cm) {
                $modinfo = get_fast_modinfo($COURSE);
                $cm = $modinfo->get_cm($this->_cm->id);
            } else {
                $cm = null;
            }
            \core_availability\frontend::include_all_javascript($COURSE, $cm);
        }

        // Conditional activities: completion tracking section
        if(!isset($completion)) {
            $completion = new completion_info($COURSE);
        }
        if ($completion->is_enabled()) {
            $mform->addElement('header', 'activitycompletionheader', get_string('activitycompletion', 'completion'));
            // Unlock button for if people have completed it (will
            // be removed in definition_after_data if they haven't)
            $mform->addElement('submit', 'unlockcompletion', get_string('unlockcompletion', 'completion'));
            $mform->registerNoSubmitButton('unlockcompletion');
            $mform->addElement('hidden', 'completionunlocked', 0);
            $mform->setType('completionunlocked', PARAM_INT);

            $trackingdefault = COMPLETION_TRACKING_NONE;
            // If system and activity default is on, set it.
            if ($CFG->completiondefault && $this->_features->defaultcompletion) {
                $hasrules = plugin_supports('mod', $this->_modname, FEATURE_COMPLETION_HAS_RULES, true);
                $tracksviews = plugin_supports('mod', $this->_modname, FEATURE_COMPLETION_TRACKS_VIEWS, true);
                if ($hasrules || $tracksviews) {
                    $trackingdefault = COMPLETION_TRACKING_AUTOMATIC;
                } else {
                    $trackingdefault = COMPLETION_TRACKING_MANUAL;
                }
            }

            $mform->addElement('select', 'completion', get_string('completion', 'completion'),
                array(COMPLETION_TRACKING_NONE=>get_string('completion_none', 'completion'),
                    COMPLETION_TRACKING_MANUAL=>get_string('completion_manual', 'completion')));
            $mform->setDefault('completion', $trackingdefault);
            $mform->addHelpButton('completion', 'completion', 'completion');

            // Automatic completion once you view it
            $gotcompletionoptions = false;
            if (plugin_supports('mod', $this->_modname, FEATURE_COMPLETION_TRACKS_VIEWS, false)) {
                $mform->addElement('checkbox', 'completionview', get_string('completionview', 'completion'),
                    get_string('completionview_desc', 'completion'));
                $mform->hideIf('completionview', 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
                // Check by default if automatic completion tracking is set.
                if ($trackingdefault == COMPLETION_TRACKING_AUTOMATIC) {
                    $mform->setDefault('completionview', 1);
                }
                $gotcompletionoptions = true;
            }

            if (plugin_supports('mod', $this->_modname, FEATURE_GRADE_HAS_GRADE, false)) {
                // This activity supports grading.
                $gotcompletionoptions = true;

                $component = "mod_{$this->_modname}";
                $itemnames = component_gradeitems::get_itemname_mapping_for_component($component);

                if (count($itemnames) === 1) {
                    // Only one gradeitem in this activity.
                    // We use the completionusegrade field here.
                    $mform->addElement(
                        'checkbox',
                        'completionusegrade',
                        get_string('completionusegrade', 'completion'),
                        get_string('completionusegrade_desc', 'completion')
                    );
                    $mform->hideIf('completionusegrade', 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
                    $mform->addHelpButton('completionusegrade', 'completionusegrade', 'completion');

                    // The disabledIf logic differs between ratings and other grade items due to different field types.
                    if ($this->_features->rating) {
                        // If using the rating system, there is no grade unless ratings are enabled.
                        $mform->disabledIf('completionusegrade', 'assessed', 'eq', 0);
                    } else {
                        // All other field types use the '$gradefieldname' field's modgrade_type.
                        $itemnumbers = array_keys($itemnames);
                        $itemnumber = array_shift($itemnumbers);
                        $gradefieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'grade');
                        $mform->disabledIf('completionusegrade', "{$gradefieldname}[modgrade_type]", 'eq', 'none');
                    }
                } else if (count($itemnames) > 1) {
                    // There are multiple grade items in this activity.
                    // Show them all.
                    $options = [
                        '' => get_string('activitygradenotrequired', 'completion'),
                    ];
                    foreach ($itemnames as $itemnumber => $itemname) {
                        $options[$itemnumber] = get_string("grade_{$itemname}_name", $component);
                    }

                    $mform->addElement(
                        'select',
                        'completiongradeitemnumber',
                        get_string('completionusegrade', 'completion'),
                        $options
                    );
                    $mform->hideIf('completiongradeitemnumber', 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
                }
            }

            // Automatic completion according to module-specific rules
            $this->_customcompletionelements = $this->add_completion_rules();
            foreach ($this->_customcompletionelements as $element) {
                $mform->hideIf($element, 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
            }

            $gotcompletionoptions = $gotcompletionoptions ||
                count($this->_customcompletionelements)>0;

            // Automatic option only appears if possible
            if ($gotcompletionoptions) {
                $mform->getElement('completion')->addOption(
                    get_string('completion_automatic', 'completion'),
                    COMPLETION_TRACKING_AUTOMATIC);
            }

            // Completion expected at particular date? (For progress tracking)
            $mform->addElement('date_time_selector', 'completionexpected', get_string('completionexpected', 'completion'),
                array('optional' => true));
            $mform->addHelpButton('completionexpected', 'completionexpected', 'completion');
            $mform->hideIf('completionexpected', 'completion', 'eq', COMPLETION_TRACKING_NONE);
        }

        // Populate module tags.
        if (core_tag_tag::is_enabled('core', 'course_modules')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
            $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'course_modules', 'component' => 'core'));
            if ($this->_cm) {
                $tags = core_tag_tag::get_item_tags_array('core', 'course_modules', $this->_cm->id);
                $mform->setDefault('tags', $tags);
            }
        }

        $this->standard_hidden_coursemodule_elements();

        $this->plugin_extend_coursemodule_standard_elements();
    }

    /**
     * Add grading settings for Hsuforum.
     */
    public function standard_hsuforum_grading_coursemodule_elements() {
        global $COURSE, $CFG;
        $mform =& $this->_form;
        $isupdate = !empty($this->_cm);
        $gradeoptions = array('isupdate' => $isupdate,
            'currentgrade' => false,
            'hasgrades' => false,
            'canrescale' => $this->_features->canrescale,
            'useratings' => $this->_features->rating);

        $itemnumber = 0;
        $component = "mod_{$this->_modname}";
        $gradefieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'grade');
        $gradecatfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradecat');
        $gradepassfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradepass');

        if ($this->_features->hasgrades) {

            if ($this->_features->gradecat) {
                $mform->addElement('header', 'modstandardgrade', get_string('gradenoun'));
            }

            //if supports grades and grades aren't being handled via ratings
            if ($isupdate) {
                $gradeitem = grade_item::fetch(array('itemtype' => 'mod',
                    'itemmodule' => $this->_cm->modname,
                    'iteminstance' => $this->_cm->instance,
                    'itemnumber' => 0,
                    'courseid' => $COURSE->id));
                if ($gradeitem) {
                    $gradeoptions['currentgrade'] = $gradeitem->grademax;
                    $gradeoptions['currentgradetype'] = $gradeitem->gradetype;
                    $gradeoptions['currentscaleid'] = $gradeitem->scaleid;
                    $gradeoptions['hasgrades'] = $gradeitem->has_grades();
                }
            }
            $mform->addElement('modgrade', 'grade', get_string('gradenoun'), $gradeoptions);
            $mform->addHelpButton('grade', 'modgrade', 'grades');
            $mform->setDefault('grade', $CFG->gradepointdefault);

            if ($this->_features->advancedgrading
                and !empty($this->current->_advancedgradingdata['methods'])
                and !empty($this->current->_advancedgradingdata['areas'])) {

                if (count($this->current->_advancedgradingdata['areas']) == 1) {
                    // if there is just one gradable area (most cases), display just the selector
                    // without its name to make UI simpler
                    $areadata = reset($this->current->_advancedgradingdata['areas']);
                    $areaname = key($this->current->_advancedgradingdata['areas']);
                    $mform->addElement('select', 'advancedgradingmethod_'.$areaname,
                        get_string('gradingmethod', 'core_grading'), $this->current->_advancedgradingdata['methods']);
                    $mform->addHelpButton('advancedgradingmethod_'.$areaname, 'gradingmethod', 'core_grading');
                    if (!$this->_features->rating) {
                        $mform->hideIf('advancedgradingmethod_'.$areaname, 'grade[modgrade_type]', 'eq', 'none');
                    }

                } else {
                    // the module defines multiple gradable areas, display a selector
                    // for each of them together with a name of the area
                    $areasgroup = array();
                    foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                        $areasgroup[] = $mform->createElement('select', 'advancedgradingmethod_'.$areaname,
                            $areadata['title'], $this->current->_advancedgradingdata['methods']);
                        $areasgroup[] = $mform->createElement('static', 'advancedgradingareaname_'.$areaname, '', $areadata['title']);
                    }
                    $mform->addGroup($areasgroup, 'advancedgradingmethodsgroup', get_string('gradingmethods', 'core_grading'),
                        array(' ', '<br />'), false);
                }
            }

            if ($this->_features->gradecat) {
                $mform->addElement('select', $gradecatfieldname,
                    get_string('gradecategoryonmodform', 'grades'),
                    grade_get_categories_menu($COURSE->id, $this->_outcomesused));
                $mform->addHelpButton($gradecatfieldname, 'gradecategoryonmodform', 'grades');
                $mform->hideIf($gradecatfieldname, "{$gradefieldname}[modgrade_type]", 'eq', 'none');
            }

            // Grade to pass.
            $mform->addElement('text', $gradepassfieldname, get_string($gradepassfieldname, 'grades'));
            $mform->addHelpButton($gradepassfieldname, $gradepassfieldname, 'grades');
            $mform->setDefault($gradepassfieldname, '');
            $mform->setType($gradepassfieldname, PARAM_RAW);
            $mform->hideIf($gradepassfieldname, "{$gradefieldname}[modgrade_type]", 'eq', 'none');
        }
    }

    /**
     * Add rating settings for Hsuforum.
     */
    protected function add_hsuforum_rating_settings() {
        global $CFG, $COURSE;

        $mform =& $this->_form;
        $itemnumber = 0;

        if ($this->gradedorrated && $this->gradedorrated !== 'rated') {
            return;
        }
        $this->gradedorrated = 'rated';

        require_once("{$CFG->dirroot}/rating/lib.php");
        $rm = new rating_manager();

        $component = "mod_{$this->_modname}";
        $assessedfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'assessed');
        $scalefieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'scale');

        $mform->addElement('header', 'modstandardratings', get_string('ratings', 'rating'));

        $isupdate = !empty($this->_cm);

        $rolenamestring = null;
        if ($isupdate) {
            $context = context_module::instance($this->_cm->id);
            $capabilities = ['moodle/rating:rate', "mod/{$this->_cm->modname}:rate"];
            $rolenames = get_role_names_with_caps_in_context($context, $capabilities);
            $rolenamestring = implode(', ', $rolenames);
        } else {
            $rolenamestring = get_string('capabilitychecknotavailable', 'rating');
        }

        $mform->addElement('static', 'rolewarning', get_string('rolewarning', 'rating'), $rolenamestring);
        $mform->addHelpButton('rolewarning', 'rolewarning', 'rating');

        $mform->addElement('select', $assessedfieldname, get_string('aggregatetype', 'rating') , $rm->get_aggregate_types());
        $mform->setDefault($assessedfieldname, 0);
        $mform->addHelpButton($assessedfieldname, 'aggregatetype', 'rating');

        $gradeoptions = [
            'isupdate' => $isupdate,
            'currentgrade' => false,
            'hasgrades' => false,
            'canrescale' => false,
            'useratings' => true,
        ];
        if ($isupdate) {
            $gradeitem = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => $this->_cm->modname,
                'iteminstance' => $this->_cm->instance,
                'itemnumber' => $itemnumber,
                'courseid' => $COURSE->id,
            ]);
            if ($gradeitem) {
                $gradeoptions['currentgrade'] = $gradeitem->grademax;
                $gradeoptions['currentgradetype'] = $gradeitem->gradetype;
                $gradeoptions['currentscaleid'] = $gradeitem->scaleid;
                $gradeoptions['hasgrades'] = $gradeitem->has_grades();
            }
        }

        $mform->addElement('modgrade', $scalefieldname, get_string('scale'), $gradeoptions);
        $mform->hideIf($scalefieldname, $assessedfieldname, 'eq', 0);
        $mform->addHelpButton($scalefieldname, 'modgrade', 'grades');
        $mform->setDefault($scalefieldname, $CFG->gradepointdefault);

        $mform->addElement('checkbox', 'ratingtime', get_string('ratingtime', 'rating'));
        $mform->hideIf('ratingtime', $assessedfieldname, 'eq', 0);

        $mform->addElement('date_time_selector', 'assesstimestart', get_string('from', 'mod_hsuforum'));
        $mform->hideIf('assesstimestart', $assessedfieldname, 'eq', 0);
        $mform->hideIf('assesstimestart', 'ratingtime');

        $mform->addElement('date_time_selector', 'assesstimefinish', get_string('to', 'mod_hsuforum'));
        $mform->hideIf('assesstimefinish', $assessedfieldname, 'eq', 0);
        $mform->hideIf('assesstimefinish', 'ratingtime');
    }

    function definition_after_data() {
        $this->_features->rating = false;
        parent::definition_after_data();
        $this->_features->rating = true;

        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'hsuforum'), 'news');
            $mform->addHelpButton('type', 'namenews', 'hsuforum');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'hsuforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        $suffix = $this->get_suffix();
        $completiondiscussionsenabledel = 'completiondiscussionsenabled' . $suffix;
        $completiondiscussionsel = 'completiondiscussions' . $suffix;
        $completionrepliesenabledel = 'completionrepliesenabled' . $suffix;
        $completionrepliesel = 'completionreplies' . $suffix;
        $completionpostsel = 'completionposts' . $suffix;
        $completionpostsenabledel = 'completionpostsenabled' . $suffix;

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values[$completiondiscussionsenabledel]=
            !empty($default_values[$completiondiscussionsel]) ? 1 : 0;
        if (empty($default_values[$completiondiscussionsel])) {
            $default_values[$completiondiscussionsel]=1;
        }
        $default_values[$completionrepliesenabledel]=
            !empty($default_values[$completionrepliesel]) ? 1 : 0;
        if (empty($default_values[$completionrepliesel])) {
            $default_values[$completionrepliesel]=1;
        }
        // Tick by default if Add mode or if completion posts settings is set to 1 or more.
        if (empty($this->_instance) || !empty($default_values[$completionpostsel])) {
            $default_values[$completionpostsenabledel] = 1;
        } else {
            $default_values[$completionpostsenabledel] = 0;
        }
        if (empty($default_values[$completionpostsel])) {
            $default_values[$completionpostsel]=1;
        }
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $suffix = $this->get_suffix();

        $group=array();
        $completionpostsenabledel = 'completionpostsenabled' . $suffix;
        $group[] =& $mform->createElement('checkbox', $completionpostsenabledel, '', get_string('completionposts','hsuforum'));
        $completionpostsel = 'completionposts' . $suffix;
        $group[] =& $mform->createElement('text', $completionpostsel, '', array('size'=>3));
        $mform->setType($completionpostsel,PARAM_INT);
        $completionpostsgroupel = 'completionpostsgroup' . $suffix;
        $mform->addGroup($group, $completionpostsgroupel, get_string('completionpostsgroup','hsuforum'), array(' '), false);
        $mform->disabledIf($completionpostsel,$completionpostsenabledel,'notchecked');

        $group=array();
        $completiondiscussionsenabledel = 'completiondiscussionsenabled' . $suffix;
        $group[] =& $mform->createElement('checkbox', $completiondiscussionsenabledel, '', get_string('completiondiscussions','hsuforum'));
        $completiondiscussionsel = 'completiondiscussions' . $suffix;
        $group[] =& $mform->createElement('text', $completiondiscussionsel, '', array('size'=>3));
        $mform->setType($completiondiscussionsel,PARAM_INT);
        $completiondiscussionsgroupel = 'completiondiscussionsgroup' . $suffix;
        $mform->addGroup($group, $completiondiscussionsgroupel, get_string('completiondiscussionsgroup','hsuforum'), array(' '), false);
        $mform->disabledIf($completiondiscussionsel,$completiondiscussionsenabledel,'notchecked');

        $group=array();
        $completionrepliesenabledel = 'completionrepliesenabled' . $suffix;
        $group[] =& $mform->createElement('checkbox', $completionrepliesenabledel, '', get_string('completionreplies','hsuforum'));
        $completionrepliesel = 'completionreplies' . $suffix;
        $group[] =& $mform->createElement('text', $completionrepliesel, '', array('size'=>3));
        $mform->setType($completionrepliesel,PARAM_INT);
        $completionrepliesgroupel = 'completionrepliesgroup' . $suffix;
        $mform->addGroup($group, $completionrepliesgroupel, get_string('completionrepliesgroup','hsuforum'), array(' '), false);
        $mform->disabledIf($completionrepliesel,$completionrepliesenabledel,'notchecked');

        return array($completiondiscussionsgroupel,$completionrepliesgroupel, $completionpostsgroupel);
    }

    function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return (!empty($data['completiondiscussionsenabled' . $suffix]) && $data['completiondiscussions' . $suffix]!=0) ||
            (!empty($data['completionrepliesenabled' . $suffix]) && $data['completionreplies' . $suffix]!=0) ||
            (!empty($data['completionpostsenabled' . $suffix]) && $data['completionposts' . $suffix]!=0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix};
            $autocompletion = !empty($completion) && $completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->{'completiondiscussionsenabled' . $suffix}) || !$autocompletion) {
                $data->{'completiondiscussions' . $suffix} = 0;
            }
            if (empty($data->{'completionrepliesenabled' . $suffix}) || !$autocompletion) {
                $data->{'completionreplies' . $suffix} = 0;
            }
            if (empty($data->{'completionpostsenabled' . $suffix}) || !$autocompletion) {
                $data->{'completionposts' . $suffix} = 0;
            }
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['completionusegrade'])) {
            // This is the same logic as in hsuforum_grade_item_update() for determining that the gradetype is GRADE_TYPE_NONE
            // If GRADE_TYPE_NONE, then we cannot have this completion criteria because there may be no grade item!
            if ($data['gradetype'] == HSUFORUM_GRADETYPE_NONE or ($data['gradetype'] == HSUFORUM_GRADETYPE_RATING and !$data['assessed']) or $data['scale'] == 0) {
                $errors['completionusegrade'] = get_string('completionusegradeerror', 'hsuforum');
            }
        }
        if (($data['gradetype'] == HSUFORUM_GRADETYPE_MANUAL || $data['gradetype'] == HSUFORUM_GRADETYPE_RATING)
            && $data['scale'] == 0) {
            $errors['scale'] = get_string('modgradeerrorbadpoint', 'grades', get_config('core', 'gradepointmax'));
        }
        return $errors;
    }
}
