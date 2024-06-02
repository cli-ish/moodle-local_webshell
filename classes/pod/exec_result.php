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
 * External API
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webshell\pod;

/**
 * Command result.
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exec_result {
    /**
     * Current working directory.
     *
     * @var string
     */
    private string $workingdir = '';
    /**
     * Current username + hostname
     *
     * @var string
     */
    private string $user = '';
    /**
     * Command Result.
     *
     * @var string
     */
    private string $result = '';

    /**
     * Mainly used as setter.
     *
     * @param string $workingdir
     * @param string $user
     * @param string $result
     */
    public function __construct(string $workingdir, string $user, string $result) {
        $this->workingdir = $workingdir;
        $this->user = $user;
        $this->result = $result;
    }

    /**
     * Get Working directory
     *
     * @return string
     */
    public function get_workingdir(): string {
        return $this->workingdir;
    }

    /**
     * Get username + hostname
     *
     * @return string
     */
    public function get_user(): string {
        return $this->user;
    }

    /**
     * Get Result
     *
     * @return string
     */
    public function get_result(): string {
        return $this->result;
    }
}
