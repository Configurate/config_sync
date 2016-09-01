<?php

namespace Drupal\config_sync;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\config_sync\ConfigSyncUtility;
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
   * The snapshot config storage for values from the extension storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $snapshotExtensionStorage;

  /**
   * The snapshot config storage for values from the active storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $snapshotActiveStorage;

  /**
   * Constructs a ConfigSyncSnapshotter object.
   *
   * @param \Drupal\config_update\ConfigDiffInterface $config_diff
   *   The config differ.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_extension_storage
   *   The snapshot storage for the items from the extension storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_active_storage
   *   The snapshot storage for the items from the active storage.
   */
  public function __construct(ConfigDiffInterface $config_diff, StorageInterface $active_storage, StorageInterface $snapshot_extension_storage, StorageInterface $snapshot_active_storage) {
    $this->configDiff = $config_diff;
    $this->activeStorage = $active_storage;
    $this->snapshotExtensionStorage = $snapshot_extension_storage;
    $this->snapshotActiveStorage = $snapshot_active_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function createExtensionSnapshot($type, $name) {
    // List the configuration items provided by the requested extension.
    if ($extension_storage = ConfigSyncUtility::getExtensionInstallStorage($type, $name)) {
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
    // Snapshot the configuration item as provided by the extension.
    if ($extension_value = $extension_storage->read($item_name)) {
      $this->snapshotExtensionStorage->write($item_name, $extension_value);
    }

    // Snapshot the configuration item as installed in the active storage.
    if ($active_value = $this->activeStorage->read($item_name)) {
      $this->snapshotActiveStorage->write($item_name, $active_value);
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
    $this->snapshotExtensionStorage->deleteAll();
    $this->snapshotActiveStorage->deleteAll();
  }

}
