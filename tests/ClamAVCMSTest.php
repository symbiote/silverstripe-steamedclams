<?php

namespace SilbinaryWolf\SteamedClams;

use File;
use FunctionalTest;
use ValidationException;
use Debug;
use Controller;
use Member;

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
    public function testModelAdmin()
    {
        $this->logInAs('admin');

        // Test ModelAdmin listing
        $controller = singleton('SilbinaryWolf\SteamedClams\ClamAVAdmin');
        $response = $this->get($controller->Link());
    }

    public function testClamAVReport()
    {
        if (!class_exists('SS_Report')) {
            return;
        }
        $this->logInAs('admin');

        // Test Report page
        $controller = singleton('ClamAVScanReport');
        $response = $this->get($controller->getLink());
    }
}