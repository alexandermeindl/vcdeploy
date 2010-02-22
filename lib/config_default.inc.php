<?php
/**
 * @file
 *   global configuration
 *   you can overwrite these settings in sldeploy.ini
 *
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 */

// configuration
$conf    = array();

// projects
$project = array();

$conf['git_bin']        = '/usr/bin/git';
$conf['svn_bin']        = '/usr/bin/svn';
$conf['cvs_bin']        = '/usr/bin/cvs';
$conf['nice_bin']       = '/usr/bin/nice';
$conf['cp_bin']         = '/bin/cp';
$conf['gzip_bin']       = '/bin/gzip';
$conf['gunzip_bin']     = '/bin/gunzip';
$conf['tar_bin']        = '/bin/tar';
$conf['mysqldump_bin']  = '/usr/bin/mysqldump';
$conf['mysql_bin']      = '/usr/bin/mysql';
$conf['mysqladmin_bin'] = '/usr/bin/mysqladmin';
$conf['scp_bin']        = '/usr/bin/scp';
$conf['ssh_bin']        = '/usr/bin/ssh';

// root directory of all projects
$conf['www_path'] = '/www';

// system configuration directory
$conf['system_source'] = '/root/system_source';

// git, svn or cvs
$conf['source_scm'] = 'git';

// backup directory (used by reset-db)
$conf['backup_dir'] = '/srv/backups';

// temporary directory
$conf['tmp_dir'] = '/tmp';

// system source code mananagement system: git, svn or cvs
$conf['system_scm']    = 'git';

// activate log
$conf['write_to_log'] = TRUE;

// log file
$conf['log_file'] = '/var/log/sldeploy.log';

$conf['deploy_git']     = array();
$conf['deploy_svn']     = array();
$conf['deploy_cvs']     = array();
$conf['permissions']    = array();
$conf['reset_db']       = array();
$conf['reset_dir']      = array();
$conf['drush']          = array();

$conf['init-system'] = array();
$conf['init-system']['dirs']     = array();
$conf['init-system']['packages'] = '';

// default access for all plugins
$plugin = array('root_only' => FALSE);