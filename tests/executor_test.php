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

namespace local_webshell;

/**
 * Tests related with local_webshell/classes/executor.php
 *
 * @package     local_webshell
 * @category    test
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class executor_test extends \basic_testcase {
    /**
     * Always store the real path we are in.
     *
     * @var $oldpath string
     */
    private $oldpath = '';

    /**
     * Change path for each test function to be in local/webshell/tests
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->oldpath = getcwd();
        chdir($CFG->dirroot . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR .
            'webshell' . DIRECTORY_SEPARATOR . 'tests');
    }

    /**
     * Change back testing path
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();
        chdir($this->oldpath);
        $this->oldpath = '';
    }

    /**
     * Verify hinting() behaviour of the class executor.
     *
     * @covers \local_webshell\executor::hinting
     */
    public function test_hinting(): void {
        // Validate executable hinting.
        $executor = new executor();
        $result = $executor->hinting("whoam", 'binary');
        $this->assertContains('whoami', $result);
        // Validate file hinting.
        $executor = new executor();
        $result = $executor->hinting("executor_", 'file');
        $this->assertContains('executor_test.php', $result);
        // Validate file hinting with directory.
        $executor = new executor();
        $result = $executor->hinting("ress", 'file');
        $this->assertContains('ressources', $result);
    }

    /**
     * Verify cwd() behaviour of the class executor.
     *
     * @covers \local_webshell\executor::cwd
     */
    public function test_cwd(): void {
        global $CFG;
        // Check local path changes.
        $executor = new executor();
        $path = $executor->get_working_dir();
        $this->assertTrue($executor->cwd("ressources"));
        $pathnew = $executor->get_working_dir();
        $this->assertEquals($path . DIRECTORY_SEPARATOR . 'ressources', $pathnew);
        // Check real path changes.
        $executor = new executor();
        $realpath = $CFG->dirroot . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR .
            'webshell' . DIRECTORY_SEPARATOR . 'tests';
        $this->assertTrue($executor->cwd($realpath));
        $pathnew = $executor->get_working_dir();
        $this->assertEquals($realpath, $pathnew);
        // Check directory traversal via "../".
        $executor = new executor();
        $path = $executor->get_working_dir();
        $this->assertTrue($executor->cwd("../"));
        $pathnew = $executor->get_working_dir();
        $this->assertEquals(dirname($path), $pathnew);
    }

    /**
     * Verify execute() behaviour of the class executor.
     *
     * @covers \local_webshell\executor::execute
     */
    public function test_execute(): void {
        global $CFG;
        // Execute basic command which should always work.
        $executor = new executor();
        $result = $executor->execute('echo "123456"');
        $this->assertEquals('123456', $result->get_result());
        // Execute basic script which changes the workingdir path.
        $executor = new executor();
        $result = $executor->execute('. ressources/path_change.sh');
        $this->assertEquals('path change', $result->get_result());
        $this->assertEquals($CFG->dirroot . DIRECTORY_SEPARATOR . 'local', $result->get_workingdir());
        // Todo: improve testing for the username.
    }

}
