# Introduction

Scans files as their uploaded and denies uploading if a virus was detected. If the ClamAV daemon is down it will log
that the file needs to be scanned, wherein you can either manually scan via the CMS once the daemon is back online, run a
nightly cron that scans the files or if you have queuedjobs installed, it will automatically scan missed files at nightly.

# Screenshots

![steamedhams](https://cloud.githubusercontent.com/assets/3859574/20907335/b8459310-bba1-11e6-86d2-3a5f6cc6e959.jpg)

# Quick Start

1) Install ClamAV

2) Setup socket permissions
NOTE: I am by no means a *nix/server expert, but this is what I did to get it going.
```
sudo mkdir /var/run/clamav
sudo chown -R defaultsite:defaultsite /var/run/clamav
clamd
```
* 'defaultsite' being the user and group that has ownership.

3) Configure clamd.conf:
```
# Path to a local socket file the daemon will listen on.
# Default: disabled (must be specified by a user)
LocalSocket /var/run/clamav/clamd.ctl
```

(optional) You can use a different socket path, but you will need to change it in
the config YML like below to match your clamd.conf:
```
SilbinaryWolf\SteamedClams\ClamAV:
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
```

4) After running dev/build, all files should scan for viruses automatically during uploading / validation.

5) To check to see if it's running properly, it should show that it's ONLINE at: http://{mysite.com}/admin/clamav

# Configuration

```
SilbinaryWolf\SteamedClams\ClamAV:
  # Make this the same as your clamd.conf settings
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
  # If true and the ClamAV daemon isn't running or isn't installed the file will be denied as if it has a virus.
  deny_on_failure: false
  # For configuring on existing site builds and ignoring the scanning of old `File` records. 
  initial_scan_ignore_before_datetime: '1970-12-25 00:00:00'
```

If you have the QueuedJobs module installed, you can configure when files missed by ClamAV daemon are scanned.
This job will only queue if the daemon couldn't be connected to at the time that the file was uploaded.

```
SilbinaryWolf\SteamedClams\ClamAVScanJob:
  # This job will queue itself on dev/build by default if `File` records have been missed in scanning.
  disable_queue_on_devbuild: false
  # Repeat at daily by default (in seconds).
  repeat_time: 86400
  # Repeat at 2am by default
  time: '02:00:00'
```

# Emulate Mode

To emulate ClamAV results, put in your YML

```
Injector:
  SilbinaryWolf\SteamedClams\ClamAV:
    class: SilbinaryWolf\SteamedClams\ClamAVEmulator
```

Then in your _config.php, switch between various testing modes:
```
use SilbinaryWolf\SteamedClams\ClamAV;
use SilbinaryWolf\SteamedClams\ClamAVEmulator;

// Use this instead of YAML for quicker testing
Config::inst()->update('Injector', 'SilbinaryWolf\SteamedClams\ClamAV', array('class' => 'SilbinaryWolf\SteamedClams\ClamAVEmulator'))

// If no virus found
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_NO_VIRUS;

// If virus found (Eicar-Test-Signature)
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;

// If ClamAV daemon isn't running
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_OFFLINE;
```

# Supports
- Silverstripe 3.2 and up (3.1 *should* work, create an issue if you determine otherwise)
- [Versioned Files](https://github.com/silverstripe-australia/silverstripe-versionedfiles)
- [CDN Content](https://github.com/silverstripe-australia/silverstripe-cdncontent)

# Credits

[Barakat S](https://github.com/FileZ/php-clamd) for clamd PHP interface
["How to Forge" users](https://web.archive.org/web/20161124000346/https://www.howtoforge.com/community/threads/clamd-will-not-start.34559/) for fixing permission issues