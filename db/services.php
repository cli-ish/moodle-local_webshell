<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Exposed services (APIs)
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_webshell_run' => [
        'classname' => '\local_webshell\external\run',
        'methodname' => 'execute',
        'description' => 'Run shell commands',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/webshell:runshell',
    ],
    'local_webshell_hinting' => [
        'classname' => '\local_webshell\external\run',
        'methodname' => 'hinting',
        'description' => 'Hint shell commands',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/webshell:runshell',
    ],
];
