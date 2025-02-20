<?php

namespace Symbiote\SteamedClams;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\SteamedClams\Extension\ClamAVUsedOnTableExtension;
use Symbiote\SteamedClams\Model\ClamAVScan;
use SilverStripe\Assets\File;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Security\BasicAuth;
use SilverStripe\Core\Config\Config;

class ClamAVUsedOnTableExtensionTest extends SapphireTest
{
    public function testUpdateUsageExcludedClasses()
    {
        $extension = new ClamAVUsedOnTableExtension();
        $excluded = ['Page'];
        $extension->updateUsageExcludedClasses($excluded);
        $this->assertContains(ClamAVScan::class, $excluded, 'ClamAVScan has been added to exclusion list');
        $this->assertContains('Page', $excluded, 'Pre-existing exclusion list entries are retained');
    }
}
