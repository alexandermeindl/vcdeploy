<?php
/**
 * global configuration
 * you can overwrite these settings in vcdeploy.ini
 *
 * PHP version 5.3
 *
 * @category  Console
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2015 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

// projects
$project = array();

$conf['git_bin'] = '/usr/bin/git';
$conf['hg_bin'] = '/usr/bin/hg';
$conf['svn_bin'] = '/usr/bin/svn';
$conf['cvs_bin'] = '/usr/bin/cvs';
$conf['bzr_bin'] = '/usr/bin/bzr';
$conf['nice_bin'] = '/usr/bin/nice';
$conf['rsync_bin'] = '/usr/bin/rsync';
$conf['cp_bin'] = '/bin/cp';
$conf['gzip_bin'] = '/bin/gzip';
$conf['gunzip_bin'] = '/bin/gunzip';
$conf['tar_bin'] = '/bin/tar';
$conf['mysqldump_bin'] = '/usr/bin/mysqldump';
$conf['mysql_bin'] = '/usr/bin/mysql';
$conf['mysqladmin_bin'] = '/usr/bin/mysqladmin';
$conf['psql_bin'] = '/usr/bin/psql';
$conf['pg_dump_bin'] = '/usr/bin/pg_dump';
$conf['dropdb_bin'] = '/usr/bin/dropdb';
$conf['scp_bin'] = '/usr/bin/scp';
$conf['ssh_bin'] = '/usr/bin/ssh';

// root directory of all projects
$conf['www_path'] = '/www';

// scm system configuration directory
$conf['system_source'] = '/root/system_source';

// scm log directory
$conf['log_source'] = '/root/log_source';

// git, svn or cvs
$conf['source_scm'] = 'git';

// mysql or postgresql
$conf['dbtype'] = 'mysql';

// backup directory (used by reset-db)
$conf['backup_dir'] = '/srv/backups';

// If true, no auto backup will be created with reset-db, reset-files or rollout (this can be overwritten with the command line option)
$conf['without_backup'] = true;

// Max days to keep backups
$conf['backup_max_days'] = 7;

// Remote host for rsync
$conf['backup_remote_host'] = '';

// Remote directory for rsync
$conf['backup_remote_dir'] = '';

$conf['backup_daily'] = array();
$conf['backup_weekly'] = array();
$conf['backup_monthly'] = array();

// install parameters for gem packages
$conf['gem_options'] = '--conservative';

// additional options to use for a mysql dump
$conf['mysqldump_options'] = '--single-transaction --extended-insert=false';

// additional options to use for a postgresql dump
$conf['postgresqldump_options'] = '';

// Create hash files for all backup files
$conf['create_hashfiles'] = false;

// temporary directory
$conf['tmp_dir'] = '/tmp';

// database for temporary work (all data will be lost in this db!)
$conf['tmp_db'] = 'vcdeploy_tmp';

// system etc directory
$conf['etc_dir'] = '/etc';

// log etc directory in system-log mode
$conf['log_etc_dir'] = true;

// exclude all files from log with these patterns
$conf['log_excludes'] = '~ .dpkg-new .dpkg-old adjtime /mtab ld.so.cache';

// system source code mananagement system: git, svn, cvs or static
// git    = git version control usage
// svn    = subversion version control usage
// cvs    = cvs version control usage
// static = static directory without version control
$conf['system_scm'] = 'git';

// define system environment
// (this is used for daemon configuration and start/stop activity)
// supported systems:
// - debian (default)
// - ubuntu
// - centos
// - suse
$conf['system_os'] = 'debian';

// activate log
$conf['write_to_log'] = true;

// Debug mode
$conf['debug'] = false;

// high priority commands
$conf['nice_high'] = -10;

// low priority commands
$conf['nice_low'] = 15;

// log file
$conf['log_file'] = '/var/log/vcdeploy.log';

$conf['deploy_git'] = array();
$conf['deploy_svn'] = array();
$conf['deploy_cvs'] = array();
$conf['deploy_bzr'] = array();
$conf['permissions'] = array();
$conf['reset_db'] = array();
$conf['reset_dir'] = array();
$conf['drush'] = array();

$conf['init-system'] = array();
$conf['init-system']['dirs'] = array();
$conf['init-system']['packages'] = '';

// set path to custom plugin (relative to vcdeploy directory or absolute path)
$conf['custom_plugins'] = 'custom_plugins';

// array of all commands, which are defined in configuration file
$conf['commands'] = array();

// default access for all plugins
$plugin = array('root_only' => false);
