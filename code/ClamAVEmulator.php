<?php

namespace SilbinaryWolf\SteamedClams;

use Config;
use File;
use Debug;
use LogicException;

/**
 * For emulating/faking ClamAV results
 *
 * This was implemented so Windows users and inexperienced developers can
 * focus on the logic surrounding the ClamAV daemon, without needing to
 * know how install it.
 */
class ClamAVEmulator extends ClamAV
{
    const MODE_UNKNOWN = 0;
    const MODE_NO_VIRUS = 1;
    const MODE_HAS_VIRUS = 2;
    const MODE_OFFLINE = 3;

    /**
     * The state of ClamAV to fake
     *
     * @var int
     */
    private static $mode = self::MODE_UNKNOWN;

    /**
     * The version string to return when emulating.
     *
     * @var string
     */
    private static $emulate_version = 'ClamAV 0.99.2/22585/Wed Nov 23 00:21:08 2016';

    /**
     * {@inheritDoc}
     */
    public function version()
    {
        $mode = Config::inst()->get(__CLASS__, 'mode');
        switch ($mode) {
            case self::MODE_UNKNOWN:
                return $this->modeUnknown();
                break;

            case self::MODE_NO_VIRUS:
            case self::MODE_HAS_VIRUS:
                return $this->config()->emulate_version;
                break;

            case self::MODE_OFFLINE:
                return $this->modeOffline();
                break;

            default:
                return $this->modeInvalid();
                break;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function fileScan($filepath)
    {
        $mode = Config::inst()->get(__CLASS__, 'mode');
        switch ($mode) {
            case self::MODE_UNKNOWN:
                return $this->modeUnknown();
                break;

            case self::MODE_NO_VIRUS:
                return array(
                    'file'  => $filepath,
                    'stats' => 'OK',
                );
                break;

            case self::MODE_HAS_VIRUS:
                return array(
                    'file'  => $filepath,
                    'stats' => 'Eicar-Test-Signature FOUND',
                );
                break;

            case self::MODE_OFFLINE:
                return $this->modeOffline();
                break;

            default:
                return $this->modeInvalid();
                break;
        }
    }

    protected function modeUnknown()
    {
        throw new LogicException('Must configure ' . __CLASS__ . '::mode config');
    }

    protected function modeOffline()
    {
        $this->last_exception = new \ClamdSocketException('*EMULATE MODE* No such file or directory "/not-real-root-folder/run/clamav/clamd.ctl"',
            2);

        return self::OFFLINE;
    }

    protected function modeInvalid()
    {
        throw new LogicException('Invalid "mode" config with value "' . Config::inst()->get(__CLASS__,
                'mode') . '". Use constants provided in ' . __CLASS__ . ' class.');
    }
}