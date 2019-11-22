<?php

namespace Symbiote\SteamedClams\Tasks;


use SilverStripe\Control\HTTPRequest;

class ClamAVInstallTask extends ClamAVBaseTask
{
    /**
     * @var string
     */
    protected $title = 'ClamAV Virus Install Task';

    /**
     * @var string
     */
    protected $description = 'Scans all files that haven\'t been scanned yet and aren\'t queued for later scanning.';

    /**
     * Limit the `File` lists for testing purposes
     */
    protected $debug_limit = 0;

    /**
     * @param HTTPRequest $request
     * @param null $job
     *
     * @return bool|void
     * @throws \Exception
     */
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
