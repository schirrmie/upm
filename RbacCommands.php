<?php
  require_once 'meekrodb.2.3.class.php';
  require_once 'PhpRbac/Rbac.php';

  use PhpRbac\Rbac;

  class RbacCommands {
    public static function init()
    {
      $config = include('config.php');
      DB::$user = $config['database']['user'];
      DB::$password = $config['database']['pass'];
      DB::$dbName = $config['database']['name'];
      DB::$host = $config['database']['host'];
      DB::$encoding = 'utf8';
      DB::$error_handler = false;
      DB::$throw_exception_on_error = true;
      DB::$throw_exception_on_nonsql_error = true;
      
//      $rbac = new src\PhpRbac\Rbac();
//      $rbacdb = new mysqli("localhost", "root", "", "upm_rbac");
    } 
//    $rbacdb = new mysqli("localhost", "root", "", "upm_rbac");
   // if ($rbacdb->connect_error){
   //   die("Connection failed: " . $rbacdb->connect_error);
   //  } 
      public static function getUsersWithRoles(&$users)
      {
        try {
          $results = DB::query("SELECT ID, Name From upm_users");
          $users = array();
          foreach($results as $row) {
            $roles_results = DB::query("SELECT r.Title FROM upm_roles AS r INNER JOIN upm_userroles AS ur ON r.ID = ur.RoleID INNER JOIN upm_users AS u ON u.ID = ur.UserID WHERE u.ID = %d", $row['ID']);
            $roles = array();
            foreach($roles_results as $r) {
              array_push($roles, $r);
            }
            array_push($users, array('name' => $row['Name'], 'roles' => $roles));
          }
          return true;
        } catch(MeekroDBException $e) {
          error_log( "DB error " . $e->getMessage() );
          error_log( $e->getQuery() );
          return false;
        }
      }
      public static function getRoles(&$roles)
      {
        try {
          $results = DB::query("SELECT Title, Description FROM upm_roles");
          $roles = array();
          foreach($results as $row) {
            array_push($roles, $row);
          }
          return true;
        } catch(MeekroDBException $e) {
          error_log( "DB error " . $e->getMessage() );
          error_log( $e->getQuery() );
          return false;
        }
      }
     
      public static function getUserRoles()
      {
        $rbac = new PhpRbac\Rbac();
        $perm_id = $rbac->Permissions->getTitle(1);
      
        error_log("perm_id: $perm_id");

        return $perm_id;
      }
      public static function addUserToRole($role_id,$user_id)
      {
        return($rbac->Users->assign($role_id, $user_id));
      }
      public static function removeUserFromRole($role_id,$user_id)
      {
         return($rbac->Users->unassign($role_id, $user_id));
      }
      public static function enforcePermissions($permission,$user_id)
      {
        $rbac = new PhpRbac\Rbac();
        return($rbac->check($permission, $user_id));

      }
  }
RbacCommands::init();
