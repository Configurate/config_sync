<?php

namespace Drupal\config_sync\Form;

use Drupal\config\Form\ConfigSync;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Construct the storage changes in a configuration synchronization form.
 */
class ConfigSyncImport extends ConfigSync {

  /**
   * The config sync snapshotter.
   *
   * @var \Drupal\config_sync\ConfigSyncSnapshotterInterface
   */
  protected $configSyncSnapshotter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $class = parent::create($container);
    // Substitute our storage for the default one.
    $class->syncStorage = $container->get('config_sync.merged_storage');
    $class->configSyncSnapshotter = $container->get('config_sync.snapshotter');
    return $class;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_sync_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function finishBatch($success, $results, $operations) {
    parent::finishBatch();

    if ($success) {
      // Refresh the configuration snapshots.
      $this->configSyncSnapshotter->createFullSnapshot();
    }
  }

}
