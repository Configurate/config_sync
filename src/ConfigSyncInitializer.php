<?php

namespace Drupal\config_sync;

use Drupal\config_sync\ConfigSyncInitializerInterface;
use Drupal\config_sync\ConfigSyncListerInterface;
use Drupal\config_sync\ConfigSyncMerger;
use Drupal\config_sync\ConfigSyncSnapshotterInterface;
use Drupal\config_sync\ConfigSyncUtility;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Returns responses for config module routes.
 */
class ConfigSyncInitializer implements ConfigSyncInitializerInterface {

  /**
   * The config sync lister.
   *
   * @var \Drupal\config_sync\ConfigSyncListerInterface
   */
  protected $configSyncLister;

  /**
   * The config sync snapshotter.
   *
   * @var \Drupal\config_sync\ConfigSyncSnapshotterInterface
   */
  protected $configSyncSnapshotter;

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The extension snapshot storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $extensionSnapshotStorage;

  /**
   * The merged storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $mergedStorage;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs a ConfigSyncInitializer object.
   *
   * @param \Drupal\config_update\ConfigSyncListerInterface $config_sync_lister
   *   The config lister.
   * @param \Drupal\config_update\ConfigSyncSnapshotterInterface $config_sync_snapshotter
   *   The config snapshotter.
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The extension snapshot storage.
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The merged storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   */
  public function __construct(ConfigSyncListerInterface $config_sync_lister, ConfigSyncSnapshotterInterface $config_sync_snapshotter, StorageInterface $active_storage, StorageInterface $extension_snapshot_storage, StorageInterface $merged_storage, ConfigManagerInterface $config_manager) {
    $this->configSyncLister = $config_sync_lister;
    $this->configSyncSnapshotter = $config_sync_snapshotter;
    $this->activeStorage = $active_storage;
    $this->extensionSnapshotStorage = $extension_snapshot_storage;
    $this->mergedStorage = $merged_storage;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeAll($safe_only = FALSE) {
    $this->seedMergeStorage();
    $config_list = $this->configSyncLister->getFullChangelist($safe_only);
    foreach ($config_list as $type => $extensions) {
      foreach ($extensions as $name => $changelist) {
        $this->initializeExtension($type, $name, $changelist);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function initializeExtension($type, $name, array $changelist = array(), $safe_only = FALSE) {
    // If no change list was passed, load one.
    if (empty($changelist)) {
      $changelist = $this->configSyncLister->getExtensionChangelist($type, $name, $safe_only);
    }
    if ($extension_storage = ConfigSyncUtility::getExtensionInstallStorage($type, $name)) {
      $active_config_items = $this->configManager->getConfigFactory()->listAll();
      // Process changes.
      if (!empty($changelist['create'])) {
        // Don't attempt to create items that already exist.
        $config_to_create = array_diff($changelist['create'], $active_config_items);
        // To create, we simply save the new item to the merge storage.
        foreach ($config_to_create as $item_name) {
          $this->mergedStorage->write($item_name, $extension_storage->read($name));
        }
      }
      // Process update changes.
      if (!empty($changelist['update'])) {
        // Don't attempt to update items that don't exist.
        $config_to_update = array_intersect($changelist['update'], $active_config_items);
        $config_sync_merger = new ConfigSyncMerger();
        // To update, we merge the value into that of the active storage.
        foreach ($config_to_update as $item_name) {
          $previous = $this->extensionSnapshotStorage->read($item_name);
          $current = $extension_storage->read($item_name);
          $active = $this->configManager->getConfigFactory()->get($item_name)->getRawData();
          $merged_value = $config_sync_merger->mergeConfigItemStates($previous, $current, $active);
          $this->mergedStorage->write($item_name, $merged_value);
        }
      }
      // Refresh the configuration snapshot.
      $this->configSyncSnapshotter->createExtensionSnapshot($type, $name);
    }
  }

  /**
   * Seeds the merge storage with the current active configuration.
   *
   * @see ConfigController::downloadExport()
   */
  protected function seedMergeStorage() {
    // Clear out any existing data.
    $this->mergedStorage->deleteAll();

    // First, export all configuration from the active storage.
    // Get raw configuration data without overrides.
    foreach ($this->configManager->getConfigFactory()->listAll() as $name) {
      $this->mergedStorage->write($name, $this->configManager->getConfigFactory()->get($name)->getRawData());
    }
    // Get all override data from the remaining collections.
    foreach ($this->activeStorage->getAllCollectionNames() as $collection) {
      $collection_storage = $this->activeStorage->createCollection($collection);
      $merged_collection_storage = $this->mergedStorage->createCollection($collection);
      $merged_collection_storage->deleteAll();
      foreach ($collection_storage->listAll() as $name) {
        $merged_collection_storage->write($name, $collection_storage->read($name));
      }
    }
  }

}
