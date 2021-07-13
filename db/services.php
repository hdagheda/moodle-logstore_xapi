<?php
/**
 * Custom external functions and service definitions.
 *
 * The functions and services defined on this file are
 * processed and registered into the Moodle DB after any
 * install or upgrade operation. All plugins support this.
 *
 * For more information, take a look to the documentation available:
 *     - Webservices API: {@link http://docs.moodle.org/dev/Web_services_API}
 *     - External API: {@link http://docs.moodle.org/dev/External_functions_API}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @package    logstore_xapi
 * @category   webservice
 * @copyright  2021 Heena Agheda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(
    'logstore_xapi_get_users' => array(
        'classname'   => 'logstore_xapi_external',
        'methodname'  => 'get_users',
        'classpath'   => 'admin/tool/log/store/xapi/classes/webservice/logstore_xapi_external.php',
        'description' => 'Get users by keyword',
        'type'        => 'read',
        'ajax'        => true,
    )
);
