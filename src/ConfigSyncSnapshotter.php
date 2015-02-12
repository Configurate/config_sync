<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncSnapshotter.
 */

namespace Drupal\config_sync;

use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\config_update\ConfigDiffInterface;
use Drupal\config_update\ConfigListInterface;
use Drupal\config_update\ConfigRevertInterface;

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
   * The config lister.
   *
   * @var \Drupal\config_update\ConfigListInterface
   */
  protected $configList;

  /**
   * The config reverter.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configRevert;

  /**
   * The extension configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $extensionStorage;

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
   * @param \Drupal\config_update\ConfigListInterface $config_list
   *   The config lister.
   * @param \Drupal\config_update\ConfigRevertInterface $config_revert
   *   The config reverter.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_storage
   *   The snapshot storage.
   */
  public function __construct(ConfigDiffInterface $config_diff, ConfigListInterface $config_list, ConfigRevertInterface $config_revert, StorageInterface $active_storage, StorageInterface $snapshot_storage) {
    $this->configDiff = $config_diff;
    $this->configList = $config_list;
    $this->configRevert = $config_revert;
    $this->extensionStorage = new ExtensionInstallStorage($active_storage);
    $this->snapshotStorage = $snapshot_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function createExtensionSnapshot($type, $name) {
    // List the configuration items provided by the requested extension.
    $item_names = array_keys($this->extensionStorage->getComponentNames($type, array($name)));
    foreach ($item_names as $item_name) {
      $extension_value = $this->configRevert->getFromExtension('', $item_name);
      $active_value = $this->configRevert->getFromActive('', $item_name);
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
