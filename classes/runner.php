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
 * Executor
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webshell;

/**
 * Run which hols all dangerous direct call commands.
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class runner {


    /**
     * Try to run the user code.
     *
     * This function should NEVER be called by other plugins since we do NOT check capabilities here again,
     * all checks are made in the WEBSERVICE's "execute" and "hinting"!
     *
     * @param string $cmd
     * @return string
     * @throws \moodle_exception
     */
    public static function run(string $cmd): string {
        if (!(defined('ALLOWED_SHELL_RUN') && ALLOWED_SHELL_RUN)) {
            return ''; // Dont allow direct calls!
        }
        $cmd = "$cmd 2>&1";

        self::prepare_execution();
        $methods = [
            'shell_exec' => fn($cmd) => self::exec_shell_exec($cmd),
            'exec' => fn($cmd) => self::exec_exec($cmd),
            'system' => fn($cmd) => self::exec_system($cmd),
            'passthru' => fn($cmd) => self::exec_passthru($cmd),
            'popen' => fn($cmd) => self::exec_popen($cmd),
            'proc_open' => fn($cmd) => self::exec_proc_open($cmd),
        ];

        foreach ($methods as $func => $executor) {
            if (function_exists($func) || self::all_function_exist(self::get_required_functions($func))) {
                return $executor($cmd);
            }
        }
        throw new \moodle_exception('noexecturofunctionfound', 'local_webshell');
    }

    /**
     * Prepare execution params.
     *
     * @return void
     */
    private static function prepare_execution(): void {
        if (function_exists('raise_memory_limit')) {
            raise_memory_limit(MEMORY_HUGE);
        }
        if (function_exists('set_time_limit') && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            set_time_limit(300);
        }
    }

    /**
     * Get a list of needed functions for given function call.
     *
     * @param string $func
     * @return string[]
     */
    private static function get_required_functions(string $func): array {
        $mapping = [
            'shell_exec' => ['shell_exec'],
            'exec' => ['exec'],
            'system' => ['system', 'ob_start', 'ob_get_contents', 'ob_end_clean'],
            'passthru' => ['passthru', 'ob_start', 'ob_get_contents', 'ob_end_clean'],
            'popen' => ['popen', 'feof', 'fread', 'pclose'],
            'proc_open' => ['proc_open', 'stream_get_contents', 'proc_close'],
        ];
        return $mapping[$func] ?? [];
    }

    /**
     * Wrapper for shell_exec call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_shell_exec(string $cmd): string {
        return shell_exec($cmd) ?? '';
    }

    /**
     * Wrapper for exec call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_exec(string $cmd): string {
        $output = [];
        exec($cmd, $output);
        return implode("\n", $output);
    }

    /**
     * Wrapper for system call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_system(string $cmd): string {
        ob_start();
        system($cmd);
        $output = ob_get_contents();
        ob_end_clean();
        return !$output ? '' : $output;
    }

    /**
     * Wrapper for passthru call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_passthru(string $cmd): string {
        ob_start();
        passthru($cmd);
        $output = ob_get_contents();
        ob_end_clean();
        return !$output ? '' : $output;
    }

    /**
     * Wrapper for popen call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_popen(string $cmd): string {
        $output = '';
        $handle = popen($cmd, 'r');
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
        return $output;
    }

    /**
     * Wrapper for proc_open call.
     *
     * @param string $cmd
     * @return string
     */
    private static function exec_proc_open(string $cmd): string {
        $handle = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        proc_close($handle);
        return !$output ? '' : $output;
    }

    /**
     * Determine the functions available to select the best approach for the executor.
     *
     * @param array $list
     * @return bool
     */
    public static function all_function_exist(array $list = []): bool {
        foreach ($list as $entry) {
            if (!function_exists($entry)) {
                return false;
            }
        }
        return true;
    }
}
