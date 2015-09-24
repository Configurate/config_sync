<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncSnapshotter.
 */

namespace Drupal\config_sync;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\config_update\ConfigDiffInterface;

/**
 * The ConfigSyncSnapshotter provides helper functions for taking snapshots of
 * extension-provided configuration.
 */
class ConfigSyncSnapshotter implements ConfigSyncSnapshotterInterface {

  /**
   * The config differ.
   *
   * @var \Drupal\config_update\ConfigDiffInterface
   */
  protected $configDiff;

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The snapshot config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $snapshotStorage;

  /**
   * Constructs a ConfigSyncSnapshotter object.
   *
   * @param \Drupal\config_update\ConfigDiffInterface $config_diff
   *   The config differ.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_storage
   *   The snapshot storage.
   */
  public function __construct(ConfigDiffInterface $config_diff, StorageInterface $active_storage, StorageInterface $snapshot_storage) {
    $this->configDiff = $config_diff;
    $this->activeStorage = $active_storage;
    $this->snapshotStorage = $snapshot_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function createExtensionSnapshot($type, $name) {
    // List the configuration items provided by the requested extension.
    if ($extension_storage = $this->getExtensionInstallStorage($type, $name)) {
      $item_names = $extension_storage->listAll();
      foreach ($item_names as $item_name) {
        $this->createItemSnapshot($extension_storage, $item_name);
      }
    }
  }

  /**
   * Creates a snapshot of a given configuration item as provided by an
   * extension.
   *
   * @param FileStorage $extension_storage
   *   An extension's configuration file storage.
   * @param string $item_name
   *   The name of the configuration item.
   */
  function createItemSnapshot(FileStorage $extension_storage, $item_name) {
    $extension_value = $extension_storage->read($item_name);
    $active_value = $this->activeStorage->read($item_name);
    // If the active value is equivalent to the extension-provided one, use
    // the active value so that it will include UUID values, used to
    // determine rename changes..
    if ($this->configDiff->same($extension_value, $active_value)) {
      $value = $active_value;
    }
    else {
      $value = $extension_value;
    }
    $this->snapshotStorage->write($item_name, $value);
  }

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
  protected function getExtensionInstallStorage($type, $name) {
    // drupal_get_path() expects 'profile' type for profile.
    $path_type = $type == 'module' && $name == drupal_get_profile() ? 'profile' : $type;
    $config_path = drupal_get_path($path_type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    if (is_dir($config_path)) {
      return new FileStorage($config_path);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createExtensionSnapshotMultiple($type, array $extension_names) {
    foreach ($extension_names as $name) {
      $this->createExtensionSnapshot($type, $name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createFullSnapshot() {
    $this->deleteSnapshot();
    $extension_config = \Drupal::config('core.extension');
    foreach (array('module', 'theme') as $type) {
      $extension_names = array_keys($extension_config->get($type));
      $this->createExtensionSnapshotMultiple($type, $extension_names);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSnapshot() {
    $this->snapshotStorage->deleteAll();
  }

}
