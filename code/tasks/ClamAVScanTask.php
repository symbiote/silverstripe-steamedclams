<?php

namespace SilbinaryWolf\SteamedClams;

use Config;
use File;
use DB;
use Debug;
use LogicException;

class ClamAVScanTask extends ClamAVBaseTask
{
    protected $title = 'ClamAV Virus Scan Task';

    protected $description = 'Scans files missed due to ClamAV daemon being unavailable at time of file upload.';

    /**
     * Limit the `File` lists for testing purposes
     */
    protected $debug_limit = 0;

    public function run($request, $job = null)
    {
        if (parent::run($request, $job) === false) {
            return;
        }

        $this->log('Starting ClamAV task...');

        $list = $this->clamAV->getFailedToScanFileList();
        $listCount = $list->count();
        if ($listCount > 0) {
            $this->log('------------------------------------');
            $this->log('Scanning the ' . $listCount . ' files that couldn\'t be scanned due to previous ClamAV daemon connectivity issues.');
            $this->log('------------------------------------');
            $this->scanListChunked($list, $this->debug_limit);
            $this->log('Finished ClamAV task.');
        } else {
            $this->log('Finished ClamAV task. No action was required.');
        }
    }
}