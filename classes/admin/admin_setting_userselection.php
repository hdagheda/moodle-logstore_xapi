<?php
// This file is part of Echo360 Sync
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
 * @package     logstore_xapi
 * @author      Heena Agheda <heenaagheda@catalyst-au.net>
 * @copyright   2021 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/form/autocomplete.php');

class admin_setting_userselection extends \admin_setting {

    public function __construct($name, $visiblename, $description, $defaultsetting = []) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, null);
    }

     /**
     * Return the currently selected users.
     *
     * @return array The ids of the currently selected users.
     */
    public function get_setting() {
        return explode(',', $this->config_read($this->name));
    }

    /**
     * Returns autocomplete XHTML field.
     *
     * @param array $data An array of checked values
     * @param string $query
     * @return string XHTML field
     */
    public function output_html($data, $query='') {
        global $CFG, $OUTPUT, $USER;
        $context = \context_user::instance($USER->id);
        $selected = $this->get_setting();
        $options = array(
            'ajax' => 'logstore_xapi/userselection',
            'multiple' => true,
            'perpage' => $CFG->maxusersperpage,
            'userfields' => implode(',', get_extra_user_fields($context)),
            'valuehtmlcallback' => function($value) {
                global $DB, $OUTPUT;
                    $user = $DB->get_record('user', ['id' => (int)$value], '*', IGNORE_MISSING);
                    if (!$user || !user_can_view_profile($user)) {
                        return false;
                    }
                    $details = user_get_user_details($user);
                return $OUTPUT->render_from_template('logstore_xapi/form-user-selector-suggestion', $details);
            }
        );
        $autocomplete = new \MoodleQuickForm_autocomplete($this->get_full_name(), $this->visiblename, array(), $options);
        $autocomplete->setValue($selected);
        $html = $autocomplete->toHtml();
        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', '', $query);
    }

    public function write_setting($data) {
        return ($this->config_write($this->name, implode(',', $data)) ? '' : get_string('errorsetting', 'admin'));
    }
}
