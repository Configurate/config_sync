<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncLister.
 */

namespace Drupal\config_sync;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Provides methods related to listing configuration changes.
 *
 * To determine if a change is considered safe, the currently installed
 * configuration is compared to a snapshot previously taken of extension-
 * provided configuration as installed.
 */
class ConfigSyncLister implements ConfigSyncListerInterface {

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The snapshot configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $snapshotStorage;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The list of changes between the latest snapshot of extension-provided
   * configuration and the active configuration storage.
   *
   * This property is not populated until
   * ConfigSyncLister::getSnapshotChangelist() is called.
   *
   * @var array
   */
  protected $snapshotChangelist;

  /**
   * Constructs a ConfigSyncLister object.
   *
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_storage
   *   The snapshot storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   */
  public function __construct(StorageInterface $active_storage, StorageInterface $snapshot_storage, ConfigManagerInterface $config_manager) {
    $this->activeStorage = $active_storage;
    $this->snapshotStorage = $snapshot_storage;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullChangelist($safe_only = TRUE) {
    $changelist = array();
    $extension_config = \Drupal::config('core.extension');
    foreach (array('module', 'theme') as $type) {
      $changelist[$type] = array();
      $names = array_keys($extension_config->get($type));
      foreach ($names as $name) {
        $changelist[$type][$name] = $this->getExtensionChangelist($type, $name, $safe_only = TRUE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionChangelist($type, $name, $safe_only = TRUE) {
    $config_path = drupal_get_path($type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;

    if (is_dir($config_path)) {
      $install_storage = new FileStorage($config_path);
      $snapshot_comparer = new StorageComparer($this->activeStorage, $install_storage, $this->configManager);
      if ($snapshot_comparer->createChangelist()->hasChanges()) {
        $changelist = $snapshot_comparer->getChangelist();
        // We're only concerned with creates and updates.
        $changelist = array_intersect_key($changelist, array_fill_keys(array('create', 'update'), NULL));
        if ($safe_only) {
          $this->setSafeChanges($changelist);
        }
        return $changelist;
      }
    }

    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getSnapshotChangelist() {
    if (empty($this->snapshotChangelist)) {
      $snapshot_comparer = new StorageComparer($this->activeStorage, $this->snapshotStorage, $this->configManager);
      $snapshot_comparer->createChangelist();
      $this->snapshotChangelist = $snapshot_comparer->getChangelist();
    }
    return $this->snapshotChangelist;
  }

  /**
   * Reduces an extension's change list to those items that can safely be
   * created or updated.
   *
   * To determine if a change is considered safe, the currently installed
   * configuration is compared to a snapshot previously taken of extension-
   * provided configuration as installed.
   *
   * @param array &$changelist
   *   Associative array of configuration changes keyed by extension type
   *   (module or theme) in which values are arrays keyed by extension name.
   */
  protected function setSafeChanges(array &$changelist) {
    $snapshot_changelist = $this->getSnapshotChangelist();
    // Items that have been deleted or renamed are not safe to create.
    if (!empty($changelist['create'])) {
      $changelist['create'] = array_diff(
        $changelist['create'],
        $snapshot_changelist['delete'],
        $snapshot_changelist['rename']
      );
    }
    // Updates in the snapshot changes indicate customized items, which are
    // not safe to update.
    if (!empty($changelist['update'])) {
      $changelist['update'] = array_diff(
        $changelist['update'],
        $snapshot_changelist['update']
      );
    }
  }

}
