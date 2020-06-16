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
  require_once 'ServerCommands.php';
  require_once 'UpdateCommands.php';
  require_once 'ConfigCommands.php';

  class FolderCommands {

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

        FolderCommands::$ssh = null;
        
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
    public static function getFolders(&$folders) {
			try {
				$results = DB::query("
				SELECT folder_parent_id, F.name AS parent, folder_child_id, F2.name AS child,
				(SELECT COUNT(*) FROM upm_folder_folder AS B WHERE A.folder_child_id = B.folder_parent_id) AS has_childs 
				FROM upm_folder_folder AS A INNER JOIN upm_folder AS F ON A.folder_parent_id = F.folder_id
				INNER JOIN upm_folder AS F2 ON A.folder_child_id = F2.folder_id
				UNION ALL
				SELECT '0' as folder_parent_id, 'root' as parent, F.folder_id, F.name as child,
				(SELECT COUNT(*) FROM upm_folder_folder AS B WHERE F.folder_id = B.folder_parent_id) AS has_childs 
				FROM upm_folder AS F WHERE F.folder_id NOT IN (SELECT folder_child_id FROM upm_folder_folder) ORDER BY parent, child;
				");
				$folders = array();
				foreach($results as $row) {
					array_push($folders, $row);
				}
				return true;
			} catch(MeekroDBException $e) {
				error_log( "DB error " . $e->getMessage() );
				error_log( $e->getQuery() );
				return false;
			}
		}
		public static function addFolder($foldername, $parent_id = null) {
      try {
        DB::insert('upm_folder', array( 'name' => $foldername) );
        $folder_id = DB::insertId();
        if ( $parent_id != null) {
          FolderCommands::moveFolder($folder_id, $parent_id);
        }
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
    }
    public static function moveFolder($folder_id, $parent_id){
      if( $folder_id == 0 ) {
        return false;
      }
      try {
        DB::delete('upm_folder_folder', "folder_child_id=%d", $folder_id);
        if( $parent_id != 0 )
          DB::insert('upm_folder_folder', array( 'folder_parent_id' => $parent_id, 'folder_child_id' => $folder_id) );
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function getFirstFolderByName($foldername) {
      try {
        $folder_id = DB::queryFirstField("SELECT folder_id FROM upm_folder WHERE name=%s", $foldername);
        if( $folder_id == null )
          return -1;
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
    }
    private static function deleteFolderFromFolderServer($folder_id) {
      try {
        DB::delete('upm_folder_server', "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function deleteFolderFromFolderFolder($folder_id) {
      try {
        DB::delete('upm_folder_folder', "folder_parent_id=%d OR folder_child_id=%d", $folder_id, $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteFolder($folder_id) {
      if( $folder_id == 0 ) {
        error_log("Can't delete Root folder!");
        return false;
      }
      if( !FolderCommands::deleteFolderFromFolderServer( $folder_id) )
        return false;
      if( !FolderCommands::deleteFolderFromFolderFolder( $folder_id) )
        return false;

      try {
        DB::delete('upm_folder', "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
		}
		public static function getFolderInfo($folder_id, &$folderinfo) {
      try {
        $folderinfo = DB::queryFirstRow("SELECT * FROM upm_folder WHERE folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }

    public static function massImport( $importdata ) {
      $j = 0;
      foreach(preg_split("/((\r?\n)|(\r\n?))/", $importdata) as $string) {
        $data[$j] = $string;
        $j++;
      }

      $dataindex = 0;
      if( !FolderCommands::recursiveImport(0, $data, $dataindex, 0) )
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
          if( !FolderCommands::recursiveImport($NewParentID, $data, $dataindex, $WhiteSpaceCount) ) {
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
            $ServerID = ServerCommands::addServer($name, $name, $ParentID);
            if( $ServerID == -1 ) {
              return false;
            }
            break;
          case '#':
            $NewParentID = FolderCommands::getFirstFolderByName( $name );
            if( $NewParentID > 0 )
              break;
            $NewParentID = FolderCommands::addFolder($name, $ParentID);
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

  }
  FolderCommands::init();