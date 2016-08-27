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
    $path_type = $type == 'module' && $name == static::drupalGetProfile() ? 'profile' : $type;
    $config_path = drupal_get_path($path_type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    if (is_dir($config_path)) {
      return new FileStorage($config_path);
    }
    return FALSE;
  }

  /**
   * Gets the name of the currently active installation profile.
   *
   * @return string|null $profile
   *   The name of the installation profile or NULL if no installation profile is
   *   currently active. This is the case for example during the first steps of
   *   the installer or during unit tests.
   */
  public static function drupalGetProfile() {
    return drupal_get_profile();
  }
}
