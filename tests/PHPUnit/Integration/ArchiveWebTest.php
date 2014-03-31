<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

use Piwik\Access;
use Piwik\Date;
use Piwik\Option;
use Piwik\Plugins\SitesManager\API;

/**
 * Tests to call the archive.php script via web and check there is no error,
 * @group Integration
 */
class Test_Piwik_Integration_ArchiveWebTest extends IntegrationTestCase
{
    public static $fixture = null; // initialized below class definition

    public static function createAccessInstance()
    {
        Access::setSingletonInstance($access = new Test_Access_OverrideLogin());
        \Piwik\Piwik::postEvent('Request.initAuthenticationObject');
    }

    public function testWebArchiving()
    {
        self::$fixture->setUp();
        self::deleteArchiveTables();

        $host  = Fixture::getRootUrl();
        $token = Fixture::getTokenAuth();

        $urlTmp = Option::get('piwikUrl');
        Option::set('piwikUrl', $host . 'tests/PHPUnit/proxy/index.php');

        $streamContext = stream_context_create(array('http' => array('timeout' => 180)));

        $output = file_get_contents($host . 'tests/PHPUnit/proxy/archive.php?token_auth=' . $token . '&forcelogtoscreen=1', 0, $streamContext);

        if (!empty($urlTmp)) {
            Option::set('piwikUrl', $urlTmp);
        } else {
            Option::delete('piwikUrl');
        }

        $this->assertContains('Starting Piwik reports archiving...', $output);
        $this->assertContains('Archived website id = 1', $output);
        $this->assertContains('Done archiving!', $output);
        $this->compareArchivePhpOutputAgainstExpected($output);
    }

    private function compareArchivePhpOutputAgainstExpected($output)
    {
        $fileName = 'test_ArchiveCronTest_archive_php_cron_output.txt';
        list($pathProcessed, $pathExpected) = static::getProcessedAndExpectedDirs();

        $expectedOutputFile = $pathExpected . $fileName;

        try {
            $this->assertTrue(is_readable($expectedOutputFile));
            $this->assertEquals(file_get_contents($expectedOutputFile), $output);
        } catch (Exception $ex) {
            $this->comparisonFailures[] = $ex;
        }
    }
}

Test_Piwik_Integration_ArchiveWebTest::$fixture = new Test_Piwik_Fixture_ManySitesImportedLogs();
Test_Piwik_Integration_ArchiveWebTest::$fixture->addSegments = true;