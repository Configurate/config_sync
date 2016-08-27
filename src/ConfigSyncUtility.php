<?php

namespace Drupal\config_sync;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;

/**
 * Provides utility methods.
 */
class ConfigSyncUtility {

  /**
   * Returns a file storage object for a given extension's install directory,
   * or FALSE if no such directory exists.
   *
   * @param string $type
   *   The type of extension (module or theme).
   * @param string $name
   *   The machine name of the extension.
   *
   * @return
   *   A FileStorage object for the given extension's install directory, or
   *   FALSE if there is no such directory.
   */
  public static function getExtensionInstallStorage($type, $name) {
    // drupal_get_path() expects 'profile' type for profile.
    $path_type = $type == 'module' && $name == drupal_get_profile() ? 'profile' : $type;
    $config_path = drupal_get_path($path_type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    if (is_dir($config_path)) {
      return new FileStorage($config_path);
    }
    return FALSE;
  }
}
