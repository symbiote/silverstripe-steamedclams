<?php

namespace SilbinaryWolf\SteamedClams;
use AbstractQueuedJob;
use Config;
use Injector;
use DB;
use QueuedJob;

if (class_exists('AbstractQueuedJob')) {

class ClamAVScanJob extends AbstractQueuedJob {
    /**
     * Disable queueing on dev/build
     */
    private static $disable_queue_on_devbuild = false;

    /**
     * Repeat at daily by default (in seconds).
     */
    private static $repeat_time = 86400;

    /**
     * Repeat at 2am by default
     */
    private static $time = '02:00:00';

    /**
     * NOTE: Called from ClamAVScan::requireDefaultRecords (2016-12-01)
     */
    public function requireDefaultRecords()
    {
        if (Config::inst()->get(__CLASS__, 'disable_queue_on_devbuild')) {
            return;
        }
        $jobDescriptorID = $this->queueMyselfIfNeeded();
        if ($jobDescriptorID !== null) {
            DB::alteration_message('Queued ClamAVScanJob #' . $jobDescriptorID, 'created');
        }
    }

    public function setup()
    {
        parent::setup();

        // Recommended for long running jobs that don't increment 'currentStep'
        // https://github.com/silverstripe-australia/silverstripe-queuedjobs
        $this->currentStep = -1;
    }

    public function getTitle()
    {
        return 'ClamAV Virus Scan Task - Scan missed files';
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        $task = Injector::inst()->get('SilbinaryWolf\\SteamedClams\\ClamAVScanTask');
        $task->run(null, $this);

        $this->currentStep = 1;
        $this->isComplete = true;
    }

    public function afterComplete()
    {
        $this->queueMyselfIfNeeded();
    }

    /**
     * Add this job if there are files to scan for viruses.
     *
     * @var int|null
     * @return null
     */
    public function queueMyselfIfNeeded()
    {
        // NOTE(Jake): Perhaps add '$cache' flag here to stop
        // 			   thrashing in ClamAVScan::onAfterWrite()
        $clamAV = Injector::inst()->get('SilbinaryWolf\\SteamedClams\\ClamAV');
        $list = $clamAV->getFailedToScanFileList();
        if (!$list || $list->count() == 0) {
            return null;
        }

        return $this->queueMyself();
    }

    /**
     * Add this job to the queue at the desired times
     *
     * @var int|null
     * @return null
     */
    public function queueMyself()
    {
        $repeat_time = Config::inst()->get(__CLASS__, 'repeat_time');
        if (!$repeat_time) {
            return null;
        }
        $time = Config::inst()->get(__CLASS__, 'time');
        if (!$time) {
            return null;
        }

        $class = get_class();
        $nextJob = new $class();
        $job = Injector::inst()->get('QueuedJobService');
        $jobDescriptorID = $job->queueJob($nextJob, date('Y-m-d', time() + $repeat_time) . ' ' . $time);

        return $jobDescriptorID;
    }
}

}