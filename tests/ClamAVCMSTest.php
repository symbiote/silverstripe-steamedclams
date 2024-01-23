<?php

namespace Symbiote\SteamedClams\Tests;

use SilverStripe\Dev\FunctionalTest;
use Symbiote\SteamedClams\Admin\ClamAVAdmin;
use SilverStripe\Reports\Report;
use Symbiote\SteamedClams\Reports\ClamAVScanReport;

/**
 * Check various CMS areas of the ClamAV module and ensure it's not
 * breaking via unset variables, typos, etc.
 */
class ClamAVCMSTest extends FunctionalTest
{
    protected static $disable_themes = true;

    protected static $fixture_file = 'ClamAVCMSTest.yml';

    /**
     *
     */
    public function testModelAdmin(): void
    {
        $this->logInAs('admin');

        // Test ModelAdmin listing
        $controller = singleton(ClamAVAdmin::class);
        $response = $this->get($controller->Link());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     *
     */
    public function testClamAVReport(): void
    {
        if (!class_exists(Report::class)) {
            return;
        }

        $this->logInAs('admin');

        // Test Report page
        $controller = singleton(ClamAVScanReport::class);
        $response = $this->get($controller->getLink());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
