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
  require_once 'FolderCommands.php';
  require_once 'UpdateCommands.php';
  require_once 'ConfigCommands.php';

  abstract class CommandNames {
    const Distribution = "distribution_command";
    const DistributionVersion = "distribution_version_command";
    const Uptime = "uptime_command";
    const RestartRequired = "restart_command";
    const ListUpdates = "updates_list_command";
    const PatchInfo = "update_info_command";
    const PatchChangelog = "update_changelog_command";
    const UpdateSystem = "update_system_command";
    const UpdatePackage = "update_package_command";
    const RebootGet = "reboot_get_command";
  }

  class ServerCommands {

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

      ServerCommands::$ssh = null;
      
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
    public static function getServers(&$servers) {
			try {
				$results = DB::query("SELECT S.server_id FROM upm_server AS S;");
				$servers = array();
				foreach($results as $row) {
					array_push($servers, $row);
				}
				return true;
			} catch(MeekroDBException $e) {
				error_log( "DB error " . $e->getMessage() );
				error_log( $e->getQuery() );
				return false;
			}
    }
    public static function addServer($servername, $hostname, $folder_id = null) {
			try {
					DB::insert('upm_server', array( 'name' => $servername, 'hostname' => $hostname) );

					$server_id = DB::insertId();
					if ( $folder_id != null ) {
					if( !ServerCommands::moveServer($server_id, $folder_id) )

							return -1;
					}
					return $server_id;
			} catch(MeekroDBException $e) {
					error_log( "DB error " . $e->getMessage() );
					error_log( $e->getQuery() );
					return -1;
			}
    }
    public static function moveServer($server_id, $folder_id) {
			try {
				DB::delete('upm_folder_server', 'server_id=%d', $server_id);
				if( $folder_id > 0 ) {
					DB::insert('upm_folder_server', array( 'folder_id' => $folder_id, 'server_id' => $server_id) );
				}
				return true;
			} catch(MeekroDBException $e) {
				error_log( "DB error " . $e->getMessage() );
				error_log( $e->getQuery() );
				return false;
			}
		}
		public static function getServerInfo($server_id, &$serverinfo) {
      try {
        $serverinfo = DB::queryFirstRow('SELECT S.*, FS.folder_id FROM upm_server AS S LEFT OUTER JOIN upm_folder_server AS FS ON FS.server_id = S.server_id WHERE S.server_id=%d', $server_id);
        $results = DB::query("SELECT U.update_id, U.server_id, U.package, IU.important_update_id, IU.comment 
          FROM upm_server_updates AS U LEFT OUTER JOIN upm_server_important_updates AS IU 
          ON U.server_id = IU.server_id AND U.package = IU.package 
          WHERE U.server_id=%d ORDER BY U.package", $server_id);
        $updates = array();
        foreach( $results as $row ) {
          array_push($updates, $row);
        }
        $serverinfo['update_list'] = $updates;
        $serverinfo['updates'] = sizeof($updates);

        $results = DB::query("SELECT IU.important_update_id, IU.package, IU.comment
          FROM upm_server_important_updates AS IU
          WHERE IU.server_id=%d ORDER BY IU.package", $server_id);
        $imp_updates = array();
        foreach( $results as $row ) {
          array_push($imp_updates, $row);
        }
        $serverinfo['imp_updates'] = $imp_updates;

        $results = DB::query("SELECT server_update_output_id, update_date
          FROM upm_server_update_output WHERE server_id=%d ORDER BY update_date DESC", $server_id);
        $update_outputs = array();
        foreach( $results as $row ) {
          array_push($update_outputs, $row);
        }
        $serverinfo['update_outputs'] = $update_outputs;
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
		public static function inventoryServer($server_id, &$serverinfo, &$error) {
      $inventory_time = date("Y-m-d H:i:s");
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::Uptime, $uptime, $error) ) {
        return false;
      }
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::RestartRequired, $restart_required, $error) ) {
        return false;
      }
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::RebootGet, $sheduled_restart, $error) ) {
        return false;
      }
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::Distribution, $distribution, $error) ) {
        return false;
      }
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::DistributionVersion, $distribution_version, $error) ) {
        return false;
      }
      if( !ServerCommands::serverRunCommandName($server_id, CommandNames::ListUpdates, $update_list_str, $error) ) {
        return false;
      }

      if( $update_list_str == "" ) {
        if( !UpdateCommands::serverClearUpdates($server_id) ) {
          $error = "Error while clearing server updates";
          return false;
        }
      } else {
        $update_list = array_map('trim', explode("\n", $update_list_str));
        foreach( $update_list as &$value) {
          if( ! UpdateCommands::addServerUpdate($server_id, $value) ) {
            $error = "Error while adding server update to table!";
            return false;
          }
        }
        if( !UpdateCommands::serverDeleteOldUpdates($server_id, $update_list) ) {
          $error = "Error while deleting old server updates in table!";
          return false;
        }
        unset($value);
      }

      if( ! ServerCommands::serverGetEOL($server_id, $EOL) ) {
        $EOL = null;
      }
      if( $sheduled_restart > 0 )
        $sheduled_restart = date("Y-m-d H:i:s", $sheduled_restart);
      else
        $sheduled_restart = null;

      if( ! ConfigCommands::updateServerInfo($server_id, $uptime, $restart_required,
        $distribution, $distribution_version, $EOL, $sheduled_restart, $inventory_time) ) {
        $error = "Error while updating host info in table";
        return false;
      }
      if( !ServerCommands::getServerInfo($server_id, $serverinfo)) {
        $error = "Error while getting host info from table!";
        return false;
      }
      return true;
		}
		private static function deleteServerFromAnyFolder($server_id) {
      try {
        DB::delete('upm_folder_server', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    // ToDo delete server output and more?
    public static function deleteServer($server_id) {
      if( !UpdateCommands::deleteAllImportantUpdates($server_id) )
        return false;
      if( !UpdateCommands::deleteAllUpdates($server_id) )
        return false;
      if( !ServerCommands::deleteServerFromAnyFolder( $server_id) )
        return false;

      try {
        DB::delete('upm_server', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
		}
		public static function serverRunCommandName($server_id, $commandname, &$command_ret, &$error) {
      if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
        if( !ConfigCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
          $error = "Can't detect distribution: " . $error2;
          return false;
        }
      }
      if( !ConfigCommands::getDistributionCommand($distri, $distri_version, $commandname, $cmd) ) {
        $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
        return false;
      }
      return ServerCommands::serverRunCommand($server_id, $cmd, $command_ret, $error);
    }
    private static function getFolderIdFromServer( $server_id ) {
      try {
        $folder_id = DB::queryFirstField("SELECT folder_id FROM upm_folder_server WHERE server_id=%d", $server_id);
        if( $folder_id == null )
          return -1;
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
  }
    public static function serverRunCommand($server_id, $command, &$command_ret, &$error) {
      if( !ServerCommands::getServerInfo($server_id, $server) ) {
        $error = "Can't receive server!";
        return false;
      }
      $folder_id = ServerCommands::getFolderIdFromServer( $server_id );
      $folder = null;
      if( $folder_id >= 0 ) {
        if( !FolderCommands::getFolderInfo($folder_id, $folder) ) {
          $error = "Can't receive folder!";
          return false;
        }
      }

      $ssh_port = 0;
      $ssh_key = "";
      $ssh_username = "";
      $ssh_hostname = $server['hostname'];

      if( $server['ssh_port'] > 0 )
        $ssh_port = $server['ssh_port'];
      else if( $folder != null && $folder['ssh_port'] > 0 )
        $ssh_port = $folder['ssh_port'];
      else
        $ssh_port = $_SESSION['default_ssh_port'];

      if( $server['ssh_private_key'] != "" )
        $ssh_key = $server['ssh_private_key'];
      else if( $folder != null && $folder['ssh_private_key'] != "" )
        $ssh_key = $folder['ssh_private_key'];
      else
        $ssh_key = $_SESSION['default_ssh_private_key'];

      if( $server['ssh_username'] != "" )
        $ssh_username = $server['ssh_username'];
      else if( $folder != null && $folder['ssh_username'] != "" )
        $ssh_username = $folder['ssh_username'];
      else
        $ssh_username = $_SESSION['default_ssh_username'];

      $ret =  ServerCommands::sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,
      $command, $command_ret, $error); 

      return $ret;
    }
    public static function getServerDistribution($server_id, &$distri, &$distri_version) {
      if( !ServerCommands::getServerInfo($server_id, $server) )
        return false;

      $distribution_config = array();

      $distri = "";
      $distri_version = "";
      if( $server['user_distribution'] != "" ) {
        $distri = $server['user_distribution'];
      } else {
        if( $server['distribution'] == null && $server['distribution_version'] == null ) {
          return false;
        } else {
          $distri = $server['distribution'];
          $distri_version = $server['distribution_version'];
        }
      }
      return true;
    }
		private static function serverGetEOL($server_id, &$EOL) {
      if( ! ServerCommands::getServerDistribution($server_id, $distri, $distri_version) )
        return false;
      return ServerCommands::getEOLByDistribution($distri, $distri_version, $EOL);
		}
		private static function getEOLByDistribution($distri, $distri_version, &$EOL) {
      if( $distri_version != "" )
        $distri = $distri . " " . $distri_version;

      foreach( $_SESSION['eol_config']  as &$value) {
        if( $value['distribution_match'] == $distri ) {
          $EOL = $value['EOL'];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['eol_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri) ) {
          $EOL = $value['EOL'];
          return true;
        }
      }
      unset($value);

      return false;
		}
		public static function addSheduleReboot($server_id, $timestamp, &$sheduled_restart, &$serverinfo) {
      try {
        if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !ConfigCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !ConfigCommands::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $YYYY = $dt->format('Y');
        $MM =   $dt->format('m');
        $DD =   $dt->format('d');
        $hh =   $dt->format('H');
        $mm =   $dt->format('i');

        $cmd = str_replace('${YYYY}', $YYYY, $cmd);
        $cmd = str_replace('${MM}', $MM, $cmd);
        $cmd = str_replace('${DD}', $DD, $cmd);
        $cmd = str_replace('${hh}', $hh, $cmd);
        $cmd = str_replace('${mm}', $mm, $cmd);
        if( !ServerCommands::serverRunCommand($server_id, $cmd, $output, $error) ) {
          return false;
        }
        if( !ServerCommands::serverRunCommandName($server_id, CommandNames::RebootGet, $sheduled_restart, $error) ) {
          return false;
        }
        if( $sheduled_restart > 0 )
          $sheduled_restart = date("Y-m-d H:i:s", $sheduled_restart);
        else
          $sheduled_restart = null;

        DB::update("upm_server", array('sheduled_restart' => $sheduled_restart), "server_id=%d", $server_id);
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function delSheduleReboot($server_id, &$serverinfo) {
      try {
        if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !ConfigCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !ConfigCommands::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        if( !ServerCommands::serverRunCommandName($server_id, CommandNames::RebootDel, $output, $error) ) {
          return false;
        }

        DB::update("upm_server", array('sheduled_restart' => null), "server_id=%d", $server_id);
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,$command, &$command_ret, &$error) {
      try { 
       $originalConnectionTimeout = ini_get('default_socket_timeout');
       ini_set('default_socket_timeout', 2);
 
       if( ServerCommands::$ssh != null ) {
         $command_ret = trim(ServerCommands::$ssh->exec( $command ));
         $command_exit_code = ServerCommands::$ssh->getExitStatus();
 
         //error_log( "hostname: $ssh_hostname reuse - $command_ret" );
         if( $command_exit_code != 0 ) {
           $error = "Error! executing command: $command return: $command_ret";
           return false;
         } else {
           return true;
         }
       }
 
       ServerCommands::$ssh = new phpseclib\Net\SSH2( $ssh_hostname, $ssh_port );
 
       ini_set('default_socket_timeout', $originalConnectionTimeout);
 
       if( !ServerCommands::$ssh ) {
         $error = "Error while connecting to host [$ssh_hostname].\n" . error_get_last()['message'];
         return false;
       }
 
       $key = new phpseclib\Crypt\RSA();
       $key->loadKey( $ssh_key );
 
       if( !$key ) {
         $error = "Error while loading private key!\n" . error_get_last()['message'];
         return false;
       }
       $login = ServerCommands::$ssh->login($ssh_username, $key);
       if( !$login ) {
         $error = "Error while loging in, with user [" . $ssh_username . "]. Check username or ssh private key!\n" . error_get_last()['message'];
         return false;
       }
       ServerCommands::$ssh->setTimeout(0);
       $command_ret = trim(ServerCommands::$ssh->exec( $command ));
       $command_exit_code = ServerCommands::$ssh->getExitStatus();
       if( $command_exit_code != 0 ) {
         $error = "Error! executing command: $command return: $command_ret";
         return false;
       }
       //error_log( "hostname: $ssh_hostname new - $command_ret" );
 
      } catch (Exception $e) {
        $error = $e->getMessage();
        return false;
      }
       return true;
    }
}
ServerCommands::init();