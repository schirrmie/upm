<?php

require_once 'Server.php';
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

class SSH{
  public static function sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,$command, &$command_ret, &$error) {
    try { 
      $originalConnectionTimeout = ini_get('default_socket_timeout');
      ini_set('default_socket_timeout', 2);

      if( Server::$ssh != null ) {
        $command_ret = trim(Server::$ssh->exec( $command ));
        $command_exit_code = Server::$ssh->getExitStatus();

        //error_log( "hostname: $ssh_hostname reuse - $command_ret" );
        if( $command_exit_code != 0 ) {
          $error = "Error! executing command: $command return: $command_ret";
          return false;
        } else {
          return true;
        }
      }

      Server::$ssh = new phpseclib\Net\SSH2( $ssh_hostname, $ssh_port );

      ini_set('default_socket_timeout', $originalConnectionTimeout);

      if( !Server::$ssh ) {
        $error = "Error while connecting to host [$ssh_hostname].\n" . error_get_last()['message'];
        return false;
      }

      $key = new phpseclib\Crypt\RSA();
      $key->loadKey( $ssh_key );

      if( !$key ) {
        $error = "Error while loading private key!\n" . error_get_last()['message'];
        return false;
      }
      $login = Server::$ssh->login($ssh_username, $key);
      if( !$login ) {
        $error = "Error while loging in, with user [" . $ssh_username . "]. Check username or ssh private key!\n" . error_get_last()['message'];
        return false;
      }
      Server::$ssh->setTimeout(0);
      $command_ret = trim(Server::$ssh->exec( $command ));
      $command_exit_code = Server::$ssh->getExitStatus();
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
