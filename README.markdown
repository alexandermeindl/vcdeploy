# vcdeploy - Version control deploy

## Requirements

* PHP 5.3.x
* SCM (git, svn or cvs)

## Optional
- rsync
- tar


##Installation


1) Copy config.inc.php.dist to config.inc.php (or to /etc/vcdeploy.inc.php). Change config.inc.php
to fit your system and project settings.

2) Create a symbolic link

	$ cd {VCDEPLOY_ROOT}
	$ ln -s vcdeploy.php /usr/local/bin/vcdeploy


## Usage


Run vcdeploy -h for more information
