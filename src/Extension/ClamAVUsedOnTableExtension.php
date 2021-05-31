<?php

namespace Symbiote\SteamedClams\Extension;

use SilverStripe\Core\Extension;
use Symbiote\SteamedClams\Model\ClamAVScan;

/**
 * Hides Clam AV Scans from file used on table
 */
class ClamAVUsedOnTableExtension extends Extension
{
    /**
     * @var string[] $excludedClasses
     */
    public function updateUsageExcludedClasses(&$excludedClasses)
    {
        $excludedClasses[] = ClamAVScan::class;
    }
}
