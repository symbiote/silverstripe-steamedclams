<?php

namespace Symbiote\SteamedClams;

use Config;
use File;
use DB;
use Debug;
use LogicException;

class ClamAVInstallTask extends ClamAVBaseTask
{
    protected $title = 'ClamAV Virus Install Task';

    protected $description = 'Scans all files that haven\'t been scanned yet and aren\'t queued for later scanning.';

    /**
     * Limit the `File` lists for testing purposes
     */
    protected $debug_limit = 0;

    public function run($request, $job = null)
    {
        if (parent::run($request, $job) === false) {
            return;
        }

        $this->log('Starting ClamAV install task...');

        $list = $this->clamAV->getInitialFileToScanList();
        $listCount = $list->count();
        if ($listCount > 0) {
            $this->log('------------------------------------');
            $this->log('Scanning the ' . $listCount . ' files that were uploaded before module installation');
            $this->log('------------------------------------');
            $this->scanListChunked($list);
            $this->log('Finished ClamAV task.');
        } else {
            $this->log('Finished ClamAV task. No action was required.');
        }
    }
}