<?php

namespace Drupal\config_sync;

/**
 * Provides methods for updating site configuration from extensions.
 */
interface ConfigSyncInitializerInterface {

  /**
   * Initializes the merge storage with available configuration updates.
   */
  public function initialize();

}
