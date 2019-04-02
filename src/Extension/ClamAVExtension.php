<?php

namespace Symbiote\SteamedClams\Extension;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\ProtectedAssetAdapter;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use Symbiote\SteamedClams\ClamAV;
use Symbiote\SteamedClams\Model\ClamAVScan;
use SilverStripe\Core\Config\Config;
use Silverstripe\SiteConfig\SiteConfig;

/**
 * Class Symbiote\SteamedClams\ClamAVExtension
 *
 * @property File|ClamAVExtension $owner
 * @method DataList|ClamAVScan[] ClamAVScans()
 */
class ClamAVExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $has_many = [
        'ClamAVScans' => ClamAVScan::class,
    ];

    /**
     * @var ClamAVScan
     */
    protected $_cache_scanForVirus = 0;

    /**
     *
     */
    //public function updateCMSFields(FieldList $fields) {
    // todo(Jake): Show 'ClamAVScans' on AssetAdmin/File level.
    //}

    /**
     * This is called within `File::write()` but before `File::onBeforeWrite()`.
     *
     * @param ValidationResult $validationResult
     *
     * @return null
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function validate(ValidationResult $validationResult)
    {
        // If its a new file, scan it.
        $doVirusScan = ($this->owner->ID == 0);

        // Support VersionedFiles module
        // ie. If file has been replaced, scan it.
        $changedFields = $this->owner->getChangedFields(true, DataObject::CHANGE_VALUE);
        $currentVersionIDChanged = (isset($changedFields['CurrentVersionID'])) ? $changedFields['CurrentVersionID'] : [];

        if ($currentVersionIDChanged && $currentVersionIDChanged['before'] != $currentVersionIDChanged['after']) {
            $doVirusScan = true;
        }

        // NOTE(Jake): Perhaps add $this->extend('updateDoVirusScan'); so other modules can support this.

        // Skip scanning unless the *physical* file on disk/CDN/etc has changed
        if (!$doVirusScan) {
            return;
        }

        $record = $this->owner->scanForVirus();

        if (!$record) {
            return;
        }

        $denyOnFailure = ClamAV::config()->get('deny_on_failure');

        $denyUpload = ($record->IsInfected || ($denyOnFailure && !$record->IsScanned));
        // todo(Jake): Allow for custom deny rules with virus scan and TEST.
        //$this->owner->extend('updateDeny', $denyUpload, $record, $validationResult);

        if (!$denyUpload) {
            // Add the scan/log if the file is clean / allowed
            $this->owner->ClamAVScans()->add($record);

            return;
        }

        $config = SiteConfig::current_site_config();

        $validationMessage = ($config->ValidationMessage) ? $config->ValidationMessage : 'A virus was detected.';

        $validationResult->addError(
            _t(
                'ClamAV.VIRUS_DETECTED',
                $validationMessage
            ),
            'VIRUS'
        );

        // Delete infected file
        // (If file hasn't been written to DB yet)
        if ($this->owner->ID == 0) {
            $filepath = $this->owner->getFullPath();

            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            $record->Action = ClamAVScan::ACTION_DELETED;
        }

        // Write log of infection to DB
        // (as this File record will never be written due to failing
        //  validation)
        if ($record && !$record->exists()) {
            $record->write();
        }
    }

    /**
     * Returns an unsaved `ClamAVScan` record with information regarding the virus scan
     *
     * @return ClamAVScan
     */
    public function scanForVirus()
    {
        if (!$this->isVirusScannable()) {
            return null;
        }

        if ($this->_cache_scanForVirus !== 0) {
            return $this->_cache_scanForVirus;
        }

        $record = Injector::inst()->get(ClamAV::class)->scanFileRecordForVirus($this->owner);

        return $this->_cache_scanForVirus = $record;
    }

    /**
     * Whether the file can be scanned or not.
     *
     * @return boolean
     */
    public function isVirusScannable()
    {
        if ($this->owner instanceof Folder) {
            return false;
        }
        // NOTE(Jake): Perhaps add $this->owner->extend() here? Maybe you want to avoid scanning
        // 2GB files or similar? But maybe we want a different function that works
        // like ::validate(). Too early to say.
        return true;
    }

    /**
     * Returns an absolute filesystem path to the file.
     * Use {@link getRelativePath()} to get the same path relative to the webroot.
     *
     * @return String
     */
    public function getFullPath()
    {
        $fileMetaData = $this->owner->File->getMetadata();

        return ASSETS_PATH . '/' . Config::inst()->get(ProtectedAssetAdapter::class, 'secure_folder')
            . '/' . $fileMetaData['path'];
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function onAfterDelete()
    {
        foreach ($this->owner->ClamAVScans() as $scan) {
            $scan->processFileActionDelete();
        }
    }
}
