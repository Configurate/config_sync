<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncLister.
 */

namespace Drupal\config_sync;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\config_sync\ConfigSyncStorageComparer;
use Drupal\config_update\ConfigDiffInterface;

/**
 * Provides methods related to listing configuration changes.
 *
 * To determine if a change is considered safe, the currently installed
 * configuration is compared to a snapshot previously taken of extension-
 * provided configuration as installed.
 */
class ConfigSyncLister implements ConfigSyncListerInterface {

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
   * @param \Drupal\config_update\ConfigDiffInterface $config_diff
   *   The config differ.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active storage.
   * @param \Drupal\Core\Config\StorageInterface $snapshot_storage
   *   The snapshot storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   */
  public function __construct(ConfigDiffInterface $config_diff, StorageInterface $active_storage, StorageInterface $snapshot_storage, ConfigManagerInterface $config_manager) {
    $this->configDiff = $config_diff;
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
      $names = array_keys($extension_config->get($type));
      foreach ($names as $name) {
        if ($extension_changelist = $this->getExtensionChangelist($type, $name, $safe_only = TRUE)) {
          if (!isset($changelist[$type])) {
            $changelist[$type] = array();
          }
          $changelist[$type][$name] = $extension_changelist;
        }
      }
    }
    return $changelist;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionChangelist($type, $name, $safe_only = TRUE) {
    $config_path = drupal_get_path($type, $name) . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;

    if (is_dir($config_path)) {
      $install_storage = new FileStorage($config_path);
      $snapshot_comparer = new ConfigSyncStorageComparer($install_storage, $this->activeStorage, $this->configManager, $this->configDiff);
      $snapshot_comparer->createChangelist();
      $changelist = $snapshot_comparer->getChangelist();
      // We're only concerned with creates and updates.
      $changelist = array_intersect_key($changelist, array_fill_keys(array('create', 'update'), NULL));

      // Only create items owned by enabled extensions.
      // @see ConfigInstaller::listDefaultConfigToInstall()
      if (isset($changelist['create'])) {
        // Core can provide configuration.
        $enabled_extensions = array('core');
        $extension_config = \Drupal::config('core.extension');
        foreach (array('module', 'theme') as $type) {
          $enabled_extensions = array_merge($enabled_extensions, array_keys($extension_config->get($type)));
        }
        $changelist['create'] = array_filter($changelist['create'], function ($config_name) use ($enabled_extensions) {
          // Ensure the configuration is provided by an enabled extension.
          $provider = Unicode::substr($config_name, 0, strpos($config_name, '.'));
          return in_array($provider, $enabled_extensions);
        });
      }

      if ($safe_only) {
        $this->setSafeChanges($changelist);
      }
      return array_filter($changelist);
    }

    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getSnapshotChangelist() {
    if (empty($this->snapshotChangelist)) {
      $snapshot_comparer = new ConfigSyncStorageComparer($this->snapshotStorage, $this->activeStorage, $this->configManager, $this->configDiff);
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
   *   Associative array of configuration changes keyed by change type (create,
   *   update, delete, rename).
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
