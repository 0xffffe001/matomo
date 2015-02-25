<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\CoreUpdater\tests\Integration\Commands;

use Piwik\Config;
use Piwik\Db;
use Piwik\Option;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;
use Piwik\Version;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * @group CoreUpdater
 * @group CoreUpdater_Integration
 */
class UpdateTest extends ConsoleCommandTestCase
{
    const VERSION_TO_UPDATE_FROM = '2.9.0';
    const EXPECTED_SQL_FROM_2_10 = "UPDATE report SET reports = REPLACE(reports, 'UserSettings_getBrowserVersion', 'DevicesDetection_getBrowserVersions');";

    private $oldScriptName = null;

    public function setUp()
    {
        parent::setUp();

        Config::getInstance()->setTestEnvironment();
        Option::set('version_core', self::VERSION_TO_UPDATE_FROM);

        $this->oldScriptName = $_SERVER['SCRIPT_NAME'];
        $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] . " console"; // update won't execute w/o this, see Common::isRunningConsoleCommand()
    }

    public function tearDown()
    {
        $_SERVER['SCRIPT_NAME'] = $this->oldScriptName;

        parent::tearDown();
    }

    public function test_UpdateCommand_SuccessfullyExecutesUpdate()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'core:update',
            '--yes' => true
        ));

        $this->assertEquals(0, $result);

        $this->assertDryRunExecuted($this->applicationTester->getDisplay());

        // make sure update went through
        $this->assertEquals(Version::VERSION, Option::get('version_core'));
    }

    public function test_UpdateCommand_DoesntExecuteSql_WhenUserSaysNo()
    {
        /** @var QuestionHelper $dialog */
        $dialog = $this->application->getHelperSet()->get('question');
        $dialog->setInputStream($this->getInputStream("N\n"));

        $result = $this->applicationTester->run(array(
            'command' => 'core:update'
        ));

        $this->assertEquals(0, $result);

        $this->assertDryRunExecuted($this->applicationTester->getDisplay());

        // make sure update did not go through
        $this->assertEquals(self::VERSION_TO_UPDATE_FROM, Option::get('version_core'));
    }

    public function test_UpdateCommand_DoesNotExecuteUpdate_IfPiwikUpToDate()
    {
        Option::set('version_core', Version::VERSION);

        $result = $this->applicationTester->run(array(
            'command' => 'core:update',
            '--yes' => true
        ));

        $this->assertEquals(0, $result);

        // check no update occurred
        $this->assertContains("Everything is already up to date.", $this->applicationTester->getDisplay());
        $this->assertEquals(Version::VERSION, Option::get('version_core'));
    }

    private function assertDryRunExecuted($output)
    {
        $this->assertContains("Note: this is a Dry Run", $output);
        $this->assertContains(self::EXPECTED_SQL_FROM_2_10, $output);
    }
}