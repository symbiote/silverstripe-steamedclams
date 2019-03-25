<?php

namespace Symbiote\SteamedClams;

use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use Symbiote\SteamedClams\Model\ClamAVScan;
use SilverStripe\Assets\File;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Security\BasicAuth;
use SilverStripe\Core\Config\Config;

class ClamAVExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function setUp()
    {
        parent::setUp();

        TestAssetStore::activate('UploadTest');
        BasicAuth::config()->set('ignore_cli', false);
    }

    //protected static $fixture_file = 'ClamAVExtensionTest.yml';

    protected function getMockFile($name = 'test-file.txt')
    {
        $absoluteTmpPath = ASSETS_PATH . DIRECTORY_SEPARATOR . $name;
        file_put_contents($absoluteTmpPath, 'testtext');

        return $absoluteTmpPath;
    }
    /**
     *
     */
    public function testBlockFileWriteIfVirusAndDenyOnFailure()
    {
        ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
        ClamAV::config()->deny_on_failure = true;

        $scanCount = ClamAVScan::get()->count();

        $name = 'updated-file.txt';
        $fileCount = File::get()->count();
        $record = File::create();
        $record->File->setFromLocalFile($this->getMockFile($name), $name);

        try {
            $record->write();
        } catch (ValidationException $e) {
            //
        }

        // Ensure only one scan file gets created
        $this->assertEquals($scanCount + 1, ClamAVScan::get()->count());

        // Ensure no file got created if it had a virus
        $this->assertEquals($fileCount, File::get()->count());
    }

    public function testFileLogIfVirus()
    {
        ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
        ClamAV::config()->deny_on_failure = false;

        $name = 'updated-file.txt';

        $fileCount = File::get()->count();
        $scanCount = ClamAVScan::get()->count();
        $record = File::create();
        $record->File->setFromLocalFile($this->getMockFile($name), $name);

        try {
            $record->write();
        } catch (ValidationException $e) {
            //
        }

        // Ensure scan is created
        $this->assertEquals($scanCount + 1, ClamAVScan::get()->count());

        // Ensure file not get created if it has a virus
        $this->assertEquals($fileCount, File::get()->count());
    }

    public function testFileLogIfVirusScannerOffline()
    {
        ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_OFFLINE;
        ClamAV::config()->deny_on_failure = false;

        $name = 'updated-file.txt';
        $filePathName = $this->getMockFile($name);

        $fileCount = File::get()->count();
        $scanCount = ClamAVScan::get()->count();
        $record = File::create();
        $record->Name = $name;
        $record->File->setFromLocalFile($this->getMockFile($name), $name);

        try {
            $record->write();
        } catch (ValidationException $e) {
            //
        }

        // Ensure scan is created
        $this->assertEquals($scanCount + 1, ClamAVScan::get()->count());

        // Ensure file gets created regardless of whether it has a virus
        $this->assertEquals($fileCount, File::get()->count());
    }

    public function testPhysicalFileRemovalOnNewFileRecordIfDenied()
    {
        ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
        ClamAV::config()->deny_on_failure = true;

        $filename = 'clamav_' . __FUNCTION__ . '.txt';
        $filepath = ASSETS_PATH . DIRECTORY_SEPARATOR . $filename;

        $this->assertFalse(file_exists($filepath));
        file_put_contents($filepath, 'testtext');

        $this->assertTrue(file_exists($filepath));

        $record = File::create();
        $record->File->setFromLocalFile($filepath, $filename);

        $fileMetaData = $record->File->getMetadata();
        $newFilepath = ASSETS_PATH . '/' . Config::inst()->get(ProtectedAssetAdapter::class, 'secure_folder')
            . '/'. $fileMetaData['path'];

        try {
            $record->write();
        } catch (ValidationException $e) {
            //
        }

        // Ensure the file is removed during File::validate()
        $fileExists = file_exists($newFilepath);
        // Cleanup from file system for local testing reasons
        @unlink($filepath);
        $this->assertFalse($fileExists);
    }

    public function testPhysicalFileRemovalOnNewFileRecordIfNotDenied()
    {
        ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
        ClamAV::config()->deny_on_failure = false;

        $filename = 'clamav_' . __FUNCTION__ . '.txt';
        $filepath = ASSETS_PATH . DIRECTORY_SEPARATOR . $filename;
        // Ensure file didn't already exist on system
        $this->assertFalse(file_exists($filepath));
        file_put_contents($filepath, 'testtext');
        $this->assertTrue(file_exists($filepath));

        $record = File::create();
        $record->Filename = ASSETS_DIR . '/' . $filename;
        try {
            $record->write();
        } catch (ValidationException $e) {
        }

        // Ensure the file stays if not denying files
        $fileExists = file_exists($filepath);
        // Cleanup from file system for local testing reasons
        @unlink($filepath);
        $this->assertTrue($fileExists);
    }
}
