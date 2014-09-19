# vcdeploy - Version control deploy

## Requirements

* PHP 5.3.x
* SCM (git, svn or cvs)

## Optional
- rsync
- tar


##Installation

1) Installing via Composer

```
curl -s http://getcomposer.org/installer | php
```

For a system-wide installation via Composer, you can run:
```
composer global require "alphanodes/vcdeploy=dev-master" --no-dev
```

Make sure you have ~/.composer/vendor/bin/ in your path.

To keep your tools up to date, you simply do this:

```
composer global update
```

2) Copy config.inc.php.dist to config.inc.php (or to /etc/vcdeploy.inc.php). Change config.inc.php
to fit your system and project settings.



## Usage

Run vcdeploy -h for more information
