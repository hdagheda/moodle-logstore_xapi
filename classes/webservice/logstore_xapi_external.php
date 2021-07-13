<?php
/**
 * External Web Service Functions for logstore_xapi
 *
 * @package   logstore_xapi
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . "/externallib.php");

class logstore_xapi_external extends external_api {
    /**
     * Returns description of get_users() parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_users_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_RAW, 'query'),
                'page' => new external_value(PARAM_INT, 'Page number'),
                'perpage' => new external_value(PARAM_INT, 'Number per page'),
            )
        );
    }

    /**
     * Get users.
     *
     * @param string $search The query
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array An array of users
     */
    public static function get_users($search, $page, $perpage) {
        global $PAGE, $DB, $CFG, $USER;

        require_once($CFG->dirroot . "/user/lib.php");
        $searchanywhere = false;
        $usercontext = context_user::instance($USER->id);

        $params = self::validate_parameters(self::get_users_parameters(),
                array('search' => $search, 'page' => $page, 'perpage' => $perpage));

        list($ufields, $params, $wherecondition) = self::xapi_get_basic_search_conditions($search, $searchanywhere);

        $fields      = 'SELECT ' . $ufields;
        $countfields = 'SELECT COUNT(u.id)';
        $sql = " FROM {user} u
                WHERE $wherecondition";
        
        $params['contextid'] = $usercontext->id;

        $users = self::xapi_execute_search_queries($search, $fields, $countfields, $sql, $params, $page, $perpage, 0, $returnexactcount);

        $results = array();
        // Add also extra user fields.
        $requiredfields = array_merge(
            ['id', 'fullname'],
            get_extra_user_fields($usercontext)
        );
        foreach ($users['users'] as $id => $user) {
            // Note: We pass the course here to validate that the current user can at least view user details in this course.
            // The user we are looking at is not in this course yet though - but we only fetch the minimal set of
            // user records, and the user has been validated to have course:enrolreview in this course. Otherwise
            // there is no way to find users who aren't in the course in order to enrol them.
            if ($userdetails = user_get_user_details($user, null, $requiredfields)) {
                $results[] = $userdetails;
            }
        }
        return $results;
    }

    /**
     * Returns description of get_users result value.
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_users_returns() {
        return new external_multiple_structure (
            new external_single_structure (
                array (
                    'id'          => new external_value(core_user::get_property_type('id'), 'ID of the user'),
                    'username'    => new external_value(core_user::get_property_type('username'), 'The username', VALUE_OPTIONAL),
                    'firstname'   => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user', VALUE_OPTIONAL),
                    'lastname'    => new external_value(core_user::get_property_type('lastname'), 'The family name of the user', VALUE_OPTIONAL),
                    'fullname'    => new external_value(core_user::get_property_type('firstname'), 'The fullname of the user'),
                    'email'       => new external_value(core_user::get_property_type('email'), 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                )
            )
        );
    }

    /**
     * Helper method used by {@link get_users()}.
     *
     * @param string $search the search term, if any.
     * @param bool $searchanywhere Can the search term be anywhere, or must it be at the start.
     * @return array with three elements:
     *     string list of fields to SELECT,
     *     string contents of SQL WHERE clause,
     *     array query params. Note that the SQL snippets use named parameters.
     */
    public static function xapi_get_basic_search_conditions($search, $searchanywhere) {
        global $DB, $CFG, $USER;
        // Add some additional sensible conditions
        $tests = array("u.id <> :guestid", 'u.deleted = 0', 'u.confirmed = 1');
        $params = array('guestid' => $CFG->siteguest);
        $usercontext = context_user::instance($USER->id);
        if (!empty($search)) {
            $conditions = get_extra_user_fields($usercontext);
            foreach (get_all_user_name_fields() as $field) {
                $conditions[] = 'u.'.$field;
            }
            $conditions[] = $DB->sql_fullname('u.firstname', 'u.lastname');
            if ($searchanywhere) {
                $searchparam = '%' . $search . '%';
            } else {
                $searchparam = $search . '%';
            }
            $i = 0;
            foreach ($conditions as $key => $condition) {
                $conditions[$key] = $DB->sql_like($condition, ":con{$i}00", false);
                $params["con{$i}00"] = $searchparam;
                $i++;
            }
            $tests[] = '(' . implode(' OR ', $conditions) . ')';
        }
        $wherecondition = implode(' AND ', $tests);

        $extrafields = get_extra_user_fields($usercontext, array('username', 'lastaccess'));
        $extrafields[] = 'username';
        $extrafields[] = 'lastaccess';
        $extrafields[] = 'maildisplay';
        $ufields = user_picture::fields('u', $extrafields);

        return array($ufields, $params, $wherecondition);
    }

    /**
     * Helper method used by {@link get_users()}.
     *
     * @param string $search the search string, if any.
     * @param string $fields the first bit of the SQL when returning some users.
     * @param string $countfields fhe first bit of the SQL when counting the users.
     * @param string $sql the bulk of the SQL statement.
     * @param array $params query parameters.
     * @param int $page which page number of the results to show.
     * @param int $perpage number of users per page.
     * @param int $addedenrollment number of users added to enrollment.
     * @param bool $returnexactcount Return the exact total users using count_record or not.
     * @return array with two or three elements:
     *      int totalusers Number users matching the search. (This element only exist if $returnexactcount was set to true)
     *      array users List of user objects returned by the query.
     *      boolean moreusers True if there are still more users, otherwise is False.
     * @throws dml_exception
     */
    public static function xapi_execute_search_queries($search, $fields, $countfields, $sql, array $params, $page, $perpage,
            $addedenrollment = 0, $returnexactcount = false) {
        global $DB, $CFG, $USER;

        $usercontext = context_user::instance($USER->id);
        list($sort, $sortparams) = users_order_by_sql('u', $search, $usercontext);
        $order = ' ORDER BY ' . $sort;

        $totalusers = 0;
        $moreusers = false;
        $results = [];

        $availableusers = $DB->get_records_sql($fields . $sql . $order,
                array_merge($params, $sortparams), ($page * $perpage) - $addedenrollment, $perpage + 1);
        if ($availableusers) {
            $totalusers = count($availableusers);
            $moreusers = $totalusers > $perpage;

            if ($moreusers) {
                // We need to discard the last record.
                array_pop($availableusers);
            }

            if ($returnexactcount && $moreusers) {
                // There is more data. We need to do the exact count.
                $totalusers = $DB->count_records_sql($countfields . $sql, $params);
            }
        }

        $results['users'] = $availableusers;
        $results['moreusers'] = $moreusers;

        if ($returnexactcount) {
            // Include totalusers in result if $returnexactcount flag is true.
            $results['totalusers'] = $totalusers;
        }

        return $results;
    }
}
