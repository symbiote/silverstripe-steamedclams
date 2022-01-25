# Introduction

Using ClamAV, this module scans files as their uploaded and denies uploading if a virus was detected. If the ClamAV daemon is down it will log
that the file needs to be scanned, wherein you can either manually scan via the CMS once the daemon is back online, run a
nightly cron that scans the files or if you have queuedjobs installed, it will automatically scan missed files at nightly.

# Composer Install

```
composer require symbiote/silverstripe-steamedclams
```

# Screenshots

![ModelAdmin](https://cloud.githubusercontent.com/assets/3859574/20911711/000abbd2-bbbe-11e6-9b93-f0490cc055f7.png)

![UploadField](https://cloud.githubusercontent.com/assets/3859574/20907335/b8459310-bba1-11e6-86d2-3a5f6cc6e959.jpg)

# Quick Start

1) Install ClamAV in Unix/Linux.
```
sudo apt install clamav clamav-daemon
```
run ``` sudo apt-get install apt-get update``` when necessary.

2) Start clamav-daemon
```
sudo service clamav-freshclam restart
# wait ~2 minutes
sudo service clamav-daemon start
```
And check the clamav-daemon is running.
```
 sudo service clamav-daemon status
```

3) Setup socket permissions
The clamav-daemon creates this /var/run/clamav/clamd.ctl if not.
```
sudo mkdir /var/run/clamav
sudo chown -R user:group /var/run/clamav
```
'defaultsite' being the user and group that has ownership.

4) Configure clamd.conf:
```
# Path to a local socket file the daemon will listen on.
# Default: disabled (must be specified by a user)
LocalSocket /var/run/clamav/clamd.ctl
```

(optional) You can use a different socket path, but you will need to change it in
the config YML like below to match your clamd.conf:
```yml
Symbiote\SteamedClams\ClamAV:
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
```

5) After running dev/build?flush, all files should scan for viruses automatically during uploading / validation. 
If you are using 

6) To check to see if it's running properly, it should show that it's ONLINE at: http://{mysite.com}/admin/clamav

# Configuration

```yml
Symbiote\SteamedClams\ClamAV:
  # Make this the same as your clamd.conf settings
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
  # If true and the ClamAV daemon isn't running or isn't installed the file will be denied as if it has a virus.
  deny_on_failure: false
  # For configuring on existing site builds and ignoring the scanning of pre-module install `File` records. 
  initial_scan_ignore_before_datetime: '1970-12-25 00:00:00'
  # If true will send files to clamd as streams (by default files are referenced using their path). Useful when files are stored remotely and/or encrypted at rest.
  use_streams: false
```

If you have the QueuedJobs module installed, you can configure when files missed by ClamAV daemon are scanned.
This job will only queue if the daemon couldn't be connected to at the time that the file was uploaded.

```yml
Symbiote\SteamedClams\ClamAVScanJob:
  # This job will queue itself on dev/build by default if `File` records have been missed in scanning.
  disable_queue_on_devbuild: false
  # Repeat at daily by default (in seconds).
  repeat_time: 86400
  # Repeat at 2am by default
  time: '02:00:00'
```

# Install on existing project

By running the task below, all files uploaded before installation of the module will be
scanned.

```
/dev/tasks/Symbiote-SteamedClams-ClamAVInstallTask
```

To ignore certain files before a specific date, you can configure the datetime in your `YML` files, as below:

```yml
Symbiote\SteamedClams\ClamAV:
  initial_scan_ignore_before_datetime: '2015-06-06 00:00:00'
```


# Emulate Mode

To emulate ClamAV results, put in your YML

```yml
Injector:
  Symbiote\SteamedClams\ClamAV:
    class: Symbiote\SteamedClams\ClamAVEmulator
```

Then in your _config.php, switch between various testing modes:
```php
<?php

use Symbiote\SteamedClams\ClamAV;
use Symbiote\SteamedClams\ClamAVEmulator;

// Use this instead of YAML for quicker testing
Config::inst()->update('Injector', 'Symbiote\SteamedClams\ClamAV', array('class' => 'Symbiote\SteamedClams\ClamAVEmulator'));

// If no virus found
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_NO_VIRUS;

// If virus found (Eicar-Test-Signature)
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_HAS_VIRUS;

// If ClamAV daemon isn't running
ClamAVEmulator::config()->mode = ClamAVEmulator::MODE_OFFLINE;
```

# Supports
- Silverstripe 5.0 and up
- [Versioned Files](https://github.com/symbiote/silverstripe-versionedfiles)
- [CDN Content](https://github.com/symbiote/silverstripe-cdncontent)
- For Silverstripe 4.x use 3.0
- For Silverstripe 3.2 and up (3.1 *should* work, create an issue if you determine otherwise) use 1.0

# Credits
[Barakat S](https://github.com/FileZ/php-clamd) for clamd PHP interface
["How to Forge" users](https://web.archive.org/web/20161124000346/https://www.howtoforge.com/community/threads/clamd-will-not-start.34559/) for fixing permission issues
