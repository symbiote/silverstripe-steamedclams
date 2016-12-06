<?php

namespace SilbinaryWolf\SteamedClams;
use Config;
use File;
use DB;
use Debug;
use LogicException;

class ClamAVBaseTask extends \BuildTask {
	/** 
	 * @var ClamAV
	 */
	protected $clamAV;

	/**
	 * @var ClamAVJob
	 */ 
	protected $job;

	/**
	 * Limit the `File` lists for testing purposes
	 *
	 * @var int
	 */
	protected $default_limit = 0;

	public function run($request, $job = null) {
		// Check if online before starting
		$this->clamAV = singleton('SilbinaryWolf\SteamedClams\ClamAV');
		$this->job = $job;
		$version = $this->clamAV->version();
		if ($version === ClamAV::OFFLINE) {
			$this->log('ClamAV daemon is offline. Cannot scan.');
			return false;
		}
		return true;
	}

	protected function scanList($list) {
		foreach ($list as $file) {
			// Skip `Folder` type
			if (!$file->isVirusScannable()) {
				$this->log('Skipping '.$file->ClassName.' #'.$file->ID.', this type cannot be scanned.');
				continue;
			}

			$path = $file->getFullPath();
			if (!$path) {
				$this->log('Skipping '.$file->ClassName.' #'.$file->ID.', no path on record. getFullPath = "'.$path.'"');
			}
			$logRecord = $file->scanForVirus();
			if ($logRecord === ClamAV::OFFLINE) {
				$this->log('ClamAV daemon is offline.', 'error');
				return false;
			}
			if (!$logRecord) {
				$this->log('Skipping '.$file->ClassName.' #'.$file->ID.'. File doesn\'t exist.');
				continue;
			}
			if ($logRecord->IsInfected) {
				$this->log($file->ClassName.' #'.$file->ID.' has a virus.', 'error');
			} else {
				$this->log($file->ClassName.' #'.$file->ID.' is clean.', 'created');
			}
			$logRecord->write();
		}
		return true;
	}

	/** 
	 * Scan files "bit-by-bit" to avoid filling up and blowing low memory limits
	 */
	protected function scanListChunked($list, $limit = null, $chunkSize = 100) {
		if ($limit === null && $this->default_limit > 0) {
			$limit = $this->default_limit;
		}

		$totalCount = $list->count();
		if ($limit > 0) {
			if ($limit > $totalCount) {
				$limit = $totalCount;
			}
			if ($chunkSize > $limit) {
				$chunkSize = $limit;
			}
			$totalCount = $limit;
		}

		$offset = 0;
		while ($offset < $totalCount) {
			$subList = $list->limit($chunkSize, $offset);
			$offset += $chunkSize;
			if ($this->scanList($subList) === false) {
				return false;
			}
			gc_collect_cycles();
		}
		return true;
	}

	/**
	 * Hide this base task
	 *
	 * @return boolean
	 */
	public function isEnabled() {
		if ($this->class === __CLASS__) {
			return false;
		}
		return parent::isEnabled();
	}

	protected function log($messageOrDataObject, $type = '', Exception $exception = null, $indent = 0) {
		$message = '';
		for ($i = 0; $i < $indent; ++$i) {
			$message .= '--';
		}
		if ($message) {
			$message .= ' ';
		}

		if (is_object($messageOrDataObject)) {
			$record = $messageOrDataObject;
			switch ($type)
			{
				case 'created':
					$message .= 'Added "'.$record->Title.'" ('.$record->class.') to #'.$record->ID;
				break;

				case 'error':
					$message .= 'Failed to write #'.$record->ID;
				break;

				case 'changed':
				case 'notice':
					$message .= 'Changed "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
				break;

				case 'deleted':
					$message = 'Deleted "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'created';
				break;

				// Special Cases for $record
				case 'published':
					$message .= 'Published "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'changed';
				break;

				case 'unpublished':
					$message .= 'Unpublished "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'changed';
				break;

				case 'archive':
					$message .= 'Archive "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'changed';
				break;

				case 'delete_error':
					$message = 'Unable to delete "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'error';
				break;

				case 'unpublished_error':
					$message .= 'Unable to unpublish "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'error';
				break;

				case 'archive_error':
					$message .= 'Unable to archive "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = 'error';
				break;

				case 'nochange':
					$message = 'No changes to "'.$record->Title.'" ('.$record->class.') #'.$record->ID;
					$type = '';
				break;

				default:
					throw new Exception('Invalid log type ("'.$type.'") passed with $record.');
				break;
			}
		} else {
			$message .= $messageOrDataObject;
		}
		if ($exception) {
			$message .= ' -- '.$exception->getMessage() . ' -- File: '.basename($exception->getFile()).' -- Line '.$exception->getLine();
		}

		switch ($type)
		{
			case '':
				DB::alteration_message($message);
			break;

			case 'created':
				DB::alteration_message($message, 'created');
			break;

			case 'error':
				set_error_handler(array($this, 'log_error_handler'));
				user_error($message, E_USER_WARNING);
				restore_error_handler();
			break;

			case 'changed':
				DB::alteration_message($message, 'changed');
			break;

			case 'notice':
			case 'warning':
				DB::alteration_message($message, 'notice');
			break;

			default:
				throw new Exception('Invalid log $type ('.$type.') passed.');
			break;
		}
	}

	 /**
	 * Custom error handler so that 'user_error' underneath the 'log' function just prints 
	 * like everything else.
	 */ 
	public function log_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
		DB::alteration_message($errstr, 'error');

		// Send out the error details to the logger for writing
		\SS_Log::log(
			array(
				'errno' => $errno,
				'errstr' => $errstr,
				'errfile' => $errfile,
				'errline' => $errline,
				'errcontext' => $errcontext
			),
			\SS_Log::ERR
		);
	}
}