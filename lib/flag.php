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

class hsuforum_lib_flag {
    /**
     * The possible flags
     *
     * @var array
     */
    protected $flags = array(
        'bookmark' => 'bookmark',
        'substantive' => 'substantive',
    );

    /**
     * @return array
     */
    public function get_flags() {
        return $this->flags;
    }

    /**
     * Get a human readable name for a flag
     *
     * @param string $flag The flag name
     * @return string
     */
    public function get_flag_name($flag) {
        return get_string("flag:$flag", 'hsuforum');
    }

    /**
     * Determine if the passed value contains the passed flag
     *
     * @param string $value The value to test
     * @param string $flag The flag name
     * @return bool
     */
    public function is_flagged($value, $flag) {
        return (strpos($value, $this->validate_flag($flag)) !== false);
    }

    /**
     * Toggle the flag in the passed value:
     *   - If flag is in value, remove it
     *   - If flag is not in value, add it
     *
     * Also cleans value of any bad flags
     * or duplicate flags
     *
     * @param string $value The value to test
     * @param string $flag The flag name
     * @return null|string
     */
    public function toggle_flag($value, $flag) {
        $this->validate_flag($flag);

        $values = explode(',', $value);
        $flags  = $this->get_flags();
        foreach ($values as $key => $value) {
            if (!in_array($value, $flags)) {
                unset($values[$key]);
            }
        }
        $values = array_unique($values);

        $key = array_search($flag, $values);
        if ($key !== false) {
            unset($values[$key]);
        } else {
            $values[] = $flag;
        }
        if (empty($values)) {
            return null;
        }
        return implode(',', $values);
    }

    /**
     * Validate the flag name
     *
     * @param string $name The flag name
     * @return string
     * @throws coding_exception
     */
    protected function validate_flag($name) {
        if (!in_array($name, $this->get_flags())) {
            throw new coding_exception("Flag does not exist: $name");
        }
        return $name;
    }
}