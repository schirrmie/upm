<?php

  require_once 'phpseclib/Net/SSH2.php';
  require_once 'phpseclib/File/ANSI.php';
  require_once 'phpseclib/Math/BigInteger.php';
  require_once 'phpseclib/Crypt/Base.php';
  require_once 'phpseclib/Crypt/Rijndael.php';
  require_once 'phpseclib/Crypt/AES.php';
  require_once 'phpseclib/Crypt/Blowfish.php';
  require_once 'phpseclib/Crypt/DES.php';
  require_once 'phpseclib/Crypt/Hash.php';
  require_once 'phpseclib/Crypt/Random.php';
  require_once 'phpseclib/Crypt/RC2.php';
  require_once 'phpseclib/Crypt/RC4.php';
  require_once 'phpseclib/Crypt/TripleDES.php';
  require_once 'phpseclib/Crypt/Twofish.php';
  require_once 'phpseclib/Crypt/RSA.php';
  require_once 'meekrodb.2.3.class.php';

  class ConfigCommands {
    private static $ssh;
    public static function init() {
        $config = include('config.php');
        DB::$user = $config['database']['user'];
        DB::$password = $config['database']['pass'];
        DB::$dbName = $config['database']['name'];
        DB::$host = $config['database']['host'];
        DB::$encoding = 'utf8';
        DB::$error_handler = false;
        DB::$throw_exception_on_error = true;
        DB::$throw_exception_on_nonsql_error = true;

        ConfigCommands::$ssh = null;
        
        // session for saving config between ajax calls
        session_start();
        session_write_close();
        if( !isset($_SESSION['default_ssh_private_key']) ) {
            ConfigCommands::loadGlobalConfig();
        }
        if( !isset($_SESSION['distribution_config']) ) {
            ConfigCommands::loadDistributionConfig();
        }
        if( !isset($_SESSION['eol_config']) ) {
            ConfigCommands::loadEolConfig();
        }
    }
    public static function updateServerInfo($server_id, $uptime, $restart_required, $distribution, $distribution_version, $EOL, $sheduled_restart, $inventory_time) {
			try {
				DB::update("upm_server", array(
					'uptime' => $uptime,
					'restart_required' => $restart_required,
					'distribution' => $distribution,
					'distribution_version' => $distribution_version,
					'EOL' => $EOL,
					'sheduled_restart' => $sheduled_restart,
					'last_inventoried' => $inventory_time), "server_id=%d", $server_id);
				return true;
			} catch(MeekroDBException $e) {
				error_log( "DB error " . $e->getMessage() );
				error_log( $e->getQuery() );
				return false;
			}
		}		
		public static function setServerConfig($server_id, $name, $hostname, $user_distribution,
      $ssh_private_key, $ssh_port, $ssh_username, &$serverinfo) {

      if( $ssh_port == "" )
        $ssh_port = 0;

      try {
        DB::update("upm_server", array(
          'name' => $name,
          'hostname' => $hostname,
          'user_distribution' => $user_distribution,
          'ssh_private_key' => $ssh_private_key,
          'ssh_port' => $ssh_port,
          'ssh_username' => $ssh_username), "server_id=%d", $server_id);
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function setFolderConfig($folder_id, $name, $icon,
      $ssh_private_key, $ssh_port, $ssh_username) {

      if( $ssh_port == "" )
        $ssh_port = 0;

      try {
        DB::update("upm_folder", array(
          'name' => $name,
          'icon' => $icon,
          'ssh_private_key' => $ssh_private_key,
          'ssh_port' => $ssh_port,
          'ssh_username' => $ssh_username), "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getDistributionConfig($config_id, &$config) {
      try {
        $config = DB::queryFirstRow("SELECT * FROM upm_distri_config WHERE config_id=%d", $config_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getDistributionOverview(&$configs) {
      try {
        $results = DB::query("SELECT config_id, distribution_match FROM upm_distri_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addDistributionConfig($config_name, $distri_name, $distri_version,
      $uptime, $restart, $update_list, $package_info, $package_changelog,
      $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) {

      try {
        DB::insert('upm_distri_config',
          array( 'distribution_match' => $config_name, 'distribution_command' => $distri_name,
          'distribution_version_command' => $distri_version, 'uptime_command' => $uptime,
          'restart_command' => $restart, 'updates_list_command' => $update_list, 'update_info_command' => $package_info,
          'update_changelog_command' => $package_changelog, 'update_system_command' => $system_update,
          'update_package_command' => $package_update, 'reboot_set_command' => $shedule_reboot_add,
          'reboot_get_command' => $shedule_reboot_get, 'reboot_del_command' => $shedule_reboot_del) );
        ConfigCommands::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateDistributionConfig($config_id, $config_name, $distri_name, $distri_version,
      $uptime, $restart, $update_list, $package_info, $package_changelog,
      $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) {

      try {
        DB::update('upm_distri_config',
          array( 'distribution_match' => $config_name, 'distribution_command' => $distri_name,
          'distribution_version_command' => $distri_version, 'uptime_command' => $uptime,
          'restart_command' => $restart, 'updates_list_command' => $update_list, 'update_info_command' => $package_info,
          'update_changelog_command' => $package_changelog, 'update_system_command' => $system_update,
          'update_package_command' => $package_update, 'reboot_set_command' => $shedule_reboot_add,
          'reboot_get_command' => $shedule_reboot_get, 'reboot_del_command' => $shedule_reboot_del), "config_id=%d", $config_id);
        ConfigCommands::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteDistributionConfig($config_id) {
      try {
        DB::delete('upm_distri_config', "config_id=%d", $config_id);
        ConfigCommands::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function loadDistributionConfig() {
      try {
        $results = DB::query("SELECT * FROM upm_distri_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        session_start();
        $_SESSION['distribution_config'] = $configs;
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
		}
		public static function getEolOverview(&$configs) {
      try {
        $results = DB::query("SELECT * FROM upm_eol_config ORDER BY distribution_match");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getEolConfig($eol_id, &$eol) {
      try {
        $eol = DB::queryFirstRow("SELECT * FROM upm_eol_config WHERE eol_id=%d", $eol_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addEolConfig($distri_name, $eol) {
      try {
        DB::insert('upm_eol_config',
          array( 'distribution_match' => $distri_name, 'EOL' => $eol) );
        ConfigCommands::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateEolConfig($eol_id, $distri_name, $eol) {
      try {
        DB::update("upm_eol_config", array(
          'distribution_match' => $distri_name,
          'EOL' => $eol), "eol_id=%d", $eol_id);
        ConfigCommands::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteEolConfig($eol_id) {
      try {
        DB::delete('upm_eol_config', "eol_id=%d", $eol_id);
        ConfigCommands::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function loadEolConfig() {
      try {
        $results = DB::query("SELECT * FROM upm_eol_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        session_start();
        $_SESSION['eol_config'] = $configs;
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
		}
		public static function getGlobalConfig(&$config) {
      try {
        $config = DB::queryFirstRow('SELECT * FROM upm_global_config');
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateGlobalConfig($default_ssh_private_key, 
      $default_ssh_port, $default_ssh_username, $default_distribution_command, $default_distribution_version_command) {
      try {
        $count = DB::queryFirstField("SELECT COUNT(*) AS C FROM upm_global_config");
        if( $count == 0 ) {
          DB::insert('upm_global_config',
            array( 'default_distribution_command' => $default_distribution_command,
            'default_distribution_version_command' => $default_distribution_version_command,
            'default_ssh_port' => $default_ssh_port, 'default_ssh_username' => $default_ssh_username,
            'default_ssh_private_key' => $default_ssh_private_key ) );
        } else {
          DB::update('upm_global_config',
            array( 'default_distribution_command' => $default_distribution_command,
            'default_distribution_version_command' => $default_distribution_version_command,
            'default_ssh_port' => $default_ssh_port, 'default_ssh_username' => $default_ssh_username,
            'default_ssh_private_key' => $default_ssh_private_key), '1=1' );
        }
        ConfigCommands::loadGlobalConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    public static function serverDetectDistribution($server_id, &$distri, &$distri_version, &$error) {
        if( !ServerCommands::serverRunCommand($server_id, $_SESSION['default_distribution_command'], $command_ret, $error) ) {
          return false;
        }
        $distri = $command_ret;
        if( !ServerCommands::serverRunCommand($server_id, $_SESSION['default_distribution_version_command'], $command_ret, $error) )
          return false;
        $distri_version = $command_ret;
        if( !ConfigCommands::serverInsertDistribution($server_id, $distri, $distri_version) ) {
          $error = "Can't update server info with distribution + version!";
          return false;
        }
        return true;
    }
    private static function serverInsertDistribution($server_id, $distri, $distri_version) {
        try {
            DB::update('upm_server',
            array( 'distribution' => $distri, 'distribution_version' => $distri_version), "server_id=%d", $server_id );
            return true;
        } catch(MeekroDBException $e) {
            error_log( "DB error " . $e->getMessage() );
            error_log( $e->getQuery() );
            return false;
        }
    }
    public static function loadGlobalConfig() {
      try {
        $config = DB::queryFirstRow("SELECT * FROM upm_global_config");
        session_start();
        $_SESSION['default_ssh_private_key'] = $config['default_ssh_private_key'];
        $_SESSION['default_ssh_public_key'] = $config['default_ssh_public_key'];
        $_SESSION['default_ssh_port'] = $config['default_ssh_port'];
        $_SESSION['default_ssh_username'] = $config['default_ssh_username'];
        $_SESSION['default_distribution_command'] = $config['default_distribution_command'];
        $_SESSION['default_distribution_version_command'] = $config['default_distribution_version_command'];
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getDistributionCommand($distri, $distri_version, $commandname, &$cmd) {
      foreach( $_SESSION['distribution_config'] as &$value) {
        if( $value['distribution_match'] == $distri . " " . $distri_version ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( $value['distribution_match'] == $distri ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri . " " . $distri_version) ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri) ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      return false;
    }
  }
  ConfigCommands::init();