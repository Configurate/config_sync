<?php

namespace Drupal\config_sync\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\config_sync\ConfigSyncInitializerInterface;
use Drupal\config_sync\ConfigSyncListerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\config\ConfigCommands;
use Drush\Drupal\Commands\config\ConfigImportCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Drush integration for the Configuration Synchronizer module.
 */
class ConfigSyncCommands extends DrushCommands {

  /**
   * The config synchronisation lister service.
   *
   * @var \Drupal\config_sync\ConfigSyncListerInterface
   */
  protected $configSyncLister;

  /**
   * The config synchronization initializer service.
   *
   * @var \Drupal\config_sync\ConfigSyncInitializerInterface
   */
  protected $configSyncInitializer;

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

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
   * The service containing Drush commands for regular core config imports.
   *
   * @var \Drush\Drupal\Commands\config\ConfigImportCommands
   */
  protected $configImportCommands;

  /**
   * Constructs a new ConfigSyncCommands object.
   *
   * @param \Drupal\config_sync\ConfigSyncListerInterface $configSyncLister
   *   The config synchronisation lister service.
   * @param \Drupal\config_sync\ConfigSyncInitializerInterface $configSyncInitializer
   *   The config synchronization initializer service.
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   *   The active configuration storage.
   * @param \Drupal\Core\Config\StorageInterface $mergedStorage
   *   The merged storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The configuration manager.
   * @param \Drush\Drupal\Commands\config\ConfigImportCommands $configImportCommands
   *   The service containing Drush commands for regular core config imports.
   */
  public function __construct(ConfigSyncListerInterface $configSyncLister, ConfigSyncInitializerInterface $configSyncInitializer, StorageInterface $activeStorage, StorageInterface $mergedStorage, ConfigManagerInterface $configManager, ConfigImportCommands $configImportCommands) {
    parent::__construct();
    $this->configSyncLister = $configSyncLister;
    $this->configSyncInitializer = $configSyncInitializer;
    $this->activeStorage = $activeStorage;
    $this->mergedStorage = $mergedStorage;
    $this->configManager = $configManager;
    $this->configImportCommands = $configImportCommands;
  }

  /**
   * Display a list of all extensions with available configuration updates.
   *
   * @command config-sync-list-updates
   * @usage drush config-sync-list-updates
   *   Display a list of all extensions with available configuration updates.
   * @aliases cs-list
   * @field-labels
   *   type: Operation type
   *   id: Config ID
   *   label: Label
   *   extension_type: Extension type
   *   extension: Extension
   * @default-fields extension,type,label
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function syncListUpdates($options = ['format' => 'table']) {
    $rows = [];
    foreach ($this->configSyncLister->getExtensionChangelists() as $extension_type => $extensions) {
      foreach ($extensions as $extension_id => $operation_types) {
        foreach ($operation_types as $operation_type => $configurations) {
          foreach ($configurations as $config_id => $config_label) {
            $rows[$config_id] = [
              'type' => $operation_type,
              'id' => $config_id,
              'label' => $config_label,
              'extension_type' => $extension_type,
              'extension' => $extension_id,
            ];
          }
        }
      }
    }

    return new RowsOfFields($rows);
  }

  /**
   * Apply configuration updates.
   *
   * @command config-sync-update
   * @option discard-overrides The active configuration will be reverted to the new configuration state as defined by distributions and modules, discarding any changes that have been made in the active configuration. If this option is omitted, the overrides will be merged with the configuration updates.
   * @usage drush config-sync-update
   *   Apply updates to all extensions.
   * @aliases cs-update
   */
  public function syncUpdate($options = ['discard-overrides' => FALSE]) {
    $this->configSyncInitializer->initialize(!$options['discard-overrides']);

    $storage_comparer = new StorageComparer($this->mergedStorage, $this->activeStorage, $this->configManager);

    if (!$storage_comparer->createChangelist()->hasChanges()) {
      $this->logger()->notice(('There are no changes to import.'));
      return;
    }

    // Output the changes that will be performed to the config.
    $change_list = [];
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $storage_comparer->getChangelist(NULL, $collection);
    }
    ConfigCommands::configChangesTablePrint($change_list);

    if ($this->io()->confirm(dt('Import the listed configuration changes?'))) {
      // Import the config using the default Drush command.
      // @see \Drush\Drupal\Commands\config\ConfigImportCommands::doImport()
      return drush_op([$this->configImportCommands, 'doImport'], $storage_comparer);
    }

    throw new UserAbortException();
  }

}
