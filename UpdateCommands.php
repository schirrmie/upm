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
  require_once 'ServerCommands.php';
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
    const RebootSet = "reboot_set_command";
    const RebootGet = "reboot_get_command";
    const RebootDel = "reboot_del_command";
  }

  class UpdateCommands {

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

      UpdateCommands::$ssh = null;
      
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
    public static function deleteAllImportantUpdates($server_id) {
      try {
        DB::delete('upm_server_important_updates', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteAllUpdates($server_id) {
      try {
        DB::delete('upm_server_updates', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getServerUpdateOutput($server_update_output_id, &$server_id, &$output) {
      try {
        $row = DB::queryFirstRow("SELECT server_id, output FROM upm_server_update_output WHERE server_update_output_id=%d", $server_update_output_id);
        $server_id = $row['server_id'];
        $output = $row['output'];
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addImportantUpdate($server_id, $package_name, $comment, &$serverinfo) {
      try {
        DB::insert('upm_server_important_updates',
          array( 'server_id' => $server_id, 'package' => $package_name, 'comment' => $comment) );
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateImportantUpdate($server_id, $iu_id, $package_name, $comment, &$serverinfo) {
      try {
        DB::update("upm_server_important_updates", array(
          'package' => $package_name,
          'comment' => $comment), "important_update_id=%d", $iu_id);
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    public static function deleteImportantUpdate($server_id, $iu_id, &$serverinfo) {
      try {
        DB::delete('upm_server_important_updates', "important_update_id=%d", $iu_id);
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function serverClearUpdates($server_id) {
      try {
        DB::delete('upm_server_updates', 'server_id=%d', $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function serverDeleteOldUpdates($server_id, $update_list) {
      try {
        $results = DB::query("SELECT * FROM upm_server_updates WHERE server_id=%d", $server_id);
        $db_updates = array();
        foreach( $results as $row ) {
          array_push($db_updates, $row);
        }
        foreach($db_updates as &$db_update) {
          $is_present = false;
          foreach($update_list as &$update) {
            if( $db_update['package'] == $update ) {
              $is_present = true;
              break;
            }
          }
          if( $is_present == false ) {
            if( !UpdateCommands::deleteServerUpdate($db_update['update_id']) )
              return false;
          }
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteServerUpdate($update_id) {
      try {
        DB::delete('upm_server_updates', 'update_id=%d', $update_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addServerUpdate($server_id, $package) {
      try {
        $count = DB::queryFirstField("SELECT COUNT(*) FROM upm_server_updates WHERE server_id=%d AND package=%s", $server_id, $package);
        if( $count > 0 )
          return true;

        DB::insert('upm_server_updates', array( 'server_id' => $server_id, 'package' => $package) );
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
	  }
    public static function getPackageChangelog($update_id, &$changelog) {
        try {
        $changelog = DB::queryFirstField("SELECT changelog FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( $changelog == null ) {
            $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
            $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
            if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
                if( !ConfigCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
                    $error = "Can't detect distribution: " . $error2;
                    return false;
                }
            }
            if( !ConfigCommands::getDistributionCommand($distri, $distri_version, CommandNames::PatchChangelog, $cmd) ) {
                $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
                return false;
            }
            $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
            if( !ServerCommands::serverRunCommand($server_id, $cmdReplaced, $changelog, $error) ) {
                return false;
            }
            DB::update("upm_server_updates", array('changelog' => $changelog), "update_id=%d", $update_id);
        }
        return true;
        } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
        }
    }
    public static function getPackageInfo($update_id, &$info) {
      try {
        $info = DB::queryFirstField("SELECT info FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( $info == null ) {
          $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
          $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
          if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
            if( !ServerCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
              $error = "Can't detect distribution: " . $error2;
              return false;
            }
          }
          if( !ConfigCommands::getDistributionCommand($distri, $distri_version, CommandNames::PatchInfo, $cmd) ) {
            $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
            return false;
          }
          $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
          if( !ServerCommands::serverRunCommand($server_id, $cmdReplaced, $info, $error) ) {
            return false;
          }
          DB::update("upm_server_updates", array('info' => $info), "update_id=%d", $update_id);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updatePackage($update_id, &$output, &$serverinfo) {
      try {
        $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
        $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( !ServerCommands::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !ServerCommands::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        
        if( !ConfigCommands::getDistributionCommand($distri, $distri_version, CommandNames::UpdatePackage, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
        if( !ServerCommands::serverRunCommand($server_id, $cmdReplaced, $output, $error) ) {
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
            if( !UpdateCommands::addServerUpdate($server_id, $value) ) {
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

        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateServer($server_id, &$output, &$serverinfo) {
      try {
        $update_time = date("Y-m-d H:i:s");
        if( !ServerCommands::serverRunCommandName($server_id, CommandNames::UpdateSystem, $output, $error) ) {
          return false;
        }
        if( !UpdateCommands::serverClearUpdates($server_id) ) {
          $error = "Error while clearing server updates";
          return false;
        }
        DB::update("upm_server", array('last_updated' => $update_time), "server_id=%d", $server_id);
        DB::insert('upm_server_update_output', array( 'server_id' => $server_id, 'update_date' => $update_time, 'output' => $output) );
        ServerCommands::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
}
UpdateCommands::init();