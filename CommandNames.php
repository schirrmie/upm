<?php
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