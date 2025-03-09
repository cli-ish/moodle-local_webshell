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
 * The command_executed event.
 *
 * @package     local_webshell
 * @copyright   2025 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webshell\event;

/**
 * The command_executed event class.
 *
 * @package     local_webshell
 * @copyright   2025 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class command_executed extends \core\event\base {
    /**
     * Init the event with needed data
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get the event name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('eventcommand_executed', 'local_webshell');
    }

    /**
     * Get the event description.
     *
     * @return \lang_string
     * @throws \coding_exception
     */
    public function get_description(): \lang_string {
        return new \lang_string('eventcommand_executeddesc', 'local_webshell',
            (object) [
                'command' => s($this->other['command']),
                'userid' => $this->userid,
            ]);
    }
}
