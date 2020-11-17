<?php
  require_once 'src/PhpRbac/Rbac.php';

  use PhpRbac\Rbac;

  $rbac = new Rbac();

  $rbac->Roles->edit(1,"RBAC-Admin", "Can use RBAC commands.");
  $rbac->Roles->add("Admin", "Can edit important packages.");
  $rbac->Roles->add("Reboot", "Can use reboot commands.");
  $rbac->Roles->add("Configurator", "Can add and remove folders and servers.");
  $rbac->Roles->add("Updater", "Can update Servers.");
  $rbac->Roles->add("Inventorist","Can use the inventory command.");
  $rbac->Roles->add("Watcher", "Can not execute functions and only see data.");

  // PERMISSIONS SETUP
  $rbac->Permissions->edit(1,"RBAC", "Can use RBAC commands.");
  $rbac->Permissions->add("Admin", "Can edit important packages.");
  $rbac->Permissions->add("Reboot", "Can use reboot commands.");
  $rbac->Permissions->add("Configuration", "Can add and remove folders and servers.");
  $rbac->Permissions->add("Updater", "Can update Servers");
  $rbac->Permissions->add("Inventorist", "Can use the inventory command");
  $rbac->Permissions->add("Watcher", "Can not execute functions and only see data");
  // Assign Roles to Permissions
  $rbac->Assign("RBAC-Admin", "RBAC");
  $rbac->Assign("Admin", "Admin");
  $rbac->Assign("Reboot", "Reboot");
  $rbac->Assign("Configurator", "Configuration");
  $rbac->Assign("Updater", "Updater");
  $rbac->Assign("Inventorist", "Inventorist");
  $rbac->Assign("Watcher", "Watcher");
  // Assign Roles to Users
  $rbac->Users->assign("RBAC-Admin", 1);
  $rbac->Users->assign("Admin", 1);
  $rbac->Users->assign("Reboot",1);
  $rbac->Users->assign("Configurator", 1);
  $rbac->Users->assign("Updater", 1);
  $rbac->Users->assign("Inventorist", 1);
  $rbac->Users->assign("Watcher", 1);
  
  $rbac->Users->Assign("Reboot",2);
  $rbac->Users->assign("Configurator", 2);
  $rbac->Users->assign("Updater", 2);
  $rbac->Users->assign("Inventorist", 2);
  $rbac->Users->assign("Watcher", 2);

  $rbac->Users->assign("Updater", 3);
  $rbac->Users->assign("Inventorist", 3);
  $rbac->Users->assign("Watcher", 3);
?>

//TODO Automatisieren