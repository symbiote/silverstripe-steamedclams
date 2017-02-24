<?php

namespace SilbinaryWolf\SteamedClams;
use File;
use ValidationException;

class ClamAVExtensionTest extends \SapphireTest {
	public function setUp() {
		parent::setUp();
	}

	//protected static $fixture_file = 'ClamAVExtensionTest.yml';

	/**
	 * 
	 */
	public function testBlockFileWriteIfVirusAndDenyOnFailure() {
		ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
		ClamAV::config()->deny_on_failure = true;

		$scanCount = ClamAVScan::get()->count();
		$fileCount = File::get()->count();
		$record = File::create();
		try {
			$record->write();
		} catch (ValidationException $e) {
		}

		// Ensure only one scan file gets created
		$this->assertEquals($scanCount + 1, ClamAVScan::get()->count());

		// Ensure no file got created if it had a virus
		$this->assertEquals($fileCount, File::get()->count());
	}

	public function testFileLogIfVirus() {
		ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;

		$fileCount = File::get()->count();
		$record = File::create();
		$record->write();

		// Ensure scan is created and attached to file
		$this->assertTrue($record->ClamAVScans()->count() > 0);

		// Ensure file gets created regardless of whether it has a virus
		$this->assertEquals($fileCount + 1, File::get()->count());
	}

	public function testFileLogIfVirusScannerOffline() {
		ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_OFFLINE;

		$fileCount = File::get()->count();

		$record = File::create();
		$record->write();

		// Ensure scan is created and attached to file
		$this->assertTrue($record->ClamAVScans()->count() > 0);

		// Ensure file gets created regardless of whether it has a virus
		$this->assertEquals($fileCount + 1, File::get()->count());
	}

	public function testPhysicalFileRemovalOnNewFileRecordIfDenied() {
		ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;
		ClamAV::config()->deny_on_failure = true;

		$filename = 'clamav_'.__FUNCTION__.'.txt';
		$filepath = ASSETS_PATH.DIRECTORY_SEPARATOR.$filename;
		$this->assertFalse(file_exists($filepath));
		file_put_contents($filepath, 'testtext');
		$this->assertTrue(file_exists($filepath));

		$record = File::create();
		$record->Filename = ASSETS_DIR.'/'.$filename;
		try {
			$record->write();
		} catch (ValidationException $e) {
		}

		// Ensure the file is removed during File::validate()
		$fileExists = file_exists($filepath);
		@unlink($filepath);
		$this->assertFalse($fileExists);
	}

	public function testPhysicalFileRemovalOnNewFileRecordIfNotDenied() {
		ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;

		$filename = 'clamav_'.__FUNCTION__.'.txt';
		$filepath = ASSETS_PATH.DIRECTORY_SEPARATOR.$filename;
		// Ensure file didn't already exist on system
		$this->assertFalse(file_exists($filepath));
		file_put_contents($filepath, 'testtext');
		$this->assertTrue(file_exists($filepath));

		$record = File::create();
		$record->Filename = ASSETS_DIR.'/'.$filename;
		try {
			$record->write();
		} catch (ValidationException $e) {
		}

		// Ensure the file stays if not denying files
		$fileExists = file_exists($filepath);
		@unlink($filepath);
		$this->assertTrue($fileExists);
	}
}