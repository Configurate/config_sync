<?php

namespace Drupal\config_sync;

/**
 * The ConfigSyncSnapshotter provides helper functions for taking snapshots of
 * extension-provided configuration.
 */
interface ConfigSyncSnapshotterInterface {

  /**
   * Takes a snapshot of configuration from modules and themes.
   *
   * Only items not already in the snapshot storage are added.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   Optional list of extensions for which to refresh the snapshot. If
   *   omitted, the entire snapshot will be refreshed.
   */
  public function refreshSnapshot(array $extensions = []);

  /**
   * Deletes all records from the snapshot.
   */
  public function deleteSnapshot();

}
