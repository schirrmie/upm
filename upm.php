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

  class UPM {

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

      UPM::$ssh = null;
      
      // session for saving config between ajax calls
      session_start();
      session_write_close();
      if( !isset($_SESSION['default_ssh_private_key']) ) {
        UPM::loadGlobalConfig();
      }
      if( !isset($_SESSION['distribution_config']) ) {
        UPM::loadDistributionConfig();
      }
      if( !isset($_SESSION['eol_config']) ) {
        UPM::loadEolConfig();
      }
    }
    public static function massImport( $importdata ) {
      $j = 0;
      foreach(preg_split("/((\r?\n)|(\r\n?))/", $importdata) as $string) {
        $data[$j] = $string;
        $j++;
      }

      $dataindex = 0;
      if( !UPM::recursiveImport(0, $data, $dataindex, 0) )
        return false;
      return true;
    }
    private static function recursiveImport($ParentID, $data, &$dataindex, $WhiteSpaceCount) {
      $OldWhiteSpaceCount = $WhiteSpaceCount;
      $NewParentID = $ParentID;

      for( ; $dataindex < sizeof($data); $dataindex++ ) {
        $string = $data[$dataindex];

        if( $string == "" )
          return true;
        $len = strlen($string);
        $string = ltrim($string);
        $len2 = strlen($string);

        $WhiteSpaceCount = $len - $len2;
      
        if( $WhiteSpaceCount > $OldWhiteSpaceCount ) {
          if( !UPM::recursiveImport($NewParentID, $data, $dataindex, $WhiteSpaceCount) ) {
            return false;
          }
          $dataindex--;
          continue;
        } else if ( $WhiteSpaceCount < $OldWhiteSpaceCount ) {
          return true;
        }

        $name = substr($string, 1);
        $char = $string[0];
        switch($char) {
          case '+':
            $ServerID = UPM::addServer($name, $name, $ParentID);
            if( $ServerID == -1 ) {
              return false;
            }
            break;
          case '#':
            $NewParentID = UPM::getFirstFolderByName( $name );
            if( $NewParentID > 0 )
              break;
            $NewParentID = UPM::addFolder($name, $ParentID);
            if( $NewParentID == -1 ) {
              return false;
            }
            break;
          default:
            error_log("wrong format string: " . $string . " char: " . $char);
            return false;
        }
      }
      return true;
    }
    private static function sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,
      $command, &$command_ret, &$error) {

     try { 
      $originalConnectionTimeout = ini_get('default_socket_timeout');
      ini_set('default_socket_timeout', 2);

      if( UPM::$ssh != null ) {
		    $command_ret = trim(UPM::$ssh->exec( $command ));
        $command_exit_code = UPM::$ssh->getExitStatus();

        //error_log( "hostname: $ssh_hostname reuse - $command_ret" );
        if( $command_exit_code != 0 ) {
          $error = "Error! executing command: $command return: $command_ret";
          return false;
        } else {
          return true;
        }
      }

      UPM::$ssh = new phpseclib\Net\SSH2( $ssh_hostname, $ssh_port );

      ini_set('default_socket_timeout', $originalConnectionTimeout);

      if( !UPM::$ssh ) {
        $error = "Error while connecting to host [$ssh_hostname].\n" . error_get_last()['message'];
        return false;
      }

      $key = new phpseclib\Crypt\RSA();
      $key->loadKey( $ssh_key );

      if( !$key ) {
        $error = "Error while loading private key!\n" . error_get_last()['message'];
        return false;
      }
      $login = UPM::$ssh->login($ssh_username, $key);
      if( !$login ) {
        $error = "Error while loging in, with user [" . $ssh_username . "]. Check username or ssh private key!\n" . error_get_last()['message'];
        return false;
      }
      UPM::$ssh->setTimeout(0);
      $command_ret = trim(UPM::$ssh->exec( $command ));
      $command_exit_code = UPM::$ssh->getExitStatus();
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
    private static function getDistributionCommand($distri, $distri_version, $commandname, &$cmd) {
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
    public static function addSheduleReboot($server_id, $timestamp, &$sheduled_restart, &$serverinfo) {
      try {
        if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
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
        if( !UPM::serverRunCommand($server_id, $cmd, $output, $error) ) {
          return false;
        }
        if( !UPM::serverRunCommandName($server_id, CommandNames::RebootGet, $sheduled_restart, $error) ) {
          return false;
        }
        if( $sheduled_restart > 0 )
          $sheduled_restart = date("Y-m-d H:i:s", $sheduled_restart);
        else
          $sheduled_restart = null;

        DB::update("upm_server", array('sheduled_restart' => $sheduled_restart), "server_id=%d", $server_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function delSheduleReboot($server_id, &$serverinfo) {
      try {
        if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        if( !UPM::serverRunCommandName($server_id, CommandNames::RebootDel, $output, $error) ) {
          return false;
        }

        DB::update("upm_server", array('sheduled_restart' => null), "server_id=%d", $server_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
  }
  UPM::init();