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

namespace src\transformer\events\mod_bigbluebuttonbn;
use function src\transformer\events\mod_bigbluebuttonbn\create_stmt;

/**
 * The mod_bigbluebuttonbn meeting ended event (triggered by bbb_ajax.php and index.php when the meeting is ended by the user).
 *
 * @author Paul Walter (https://github.com/paulito-bandito)
 * @param array $config
 * @param \stdClass $event
 * @return array
 */
function meeting_ended(array $config, \stdClass $event) {

    return create_stmt( $config, $event, 'http://id.tincanapi.com/verb/adjourned', 'adjourned' );
}