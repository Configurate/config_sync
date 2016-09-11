<?php

namespace Drupal\config_sync;

/**
 * Provides methods for updating site configuration from extensions.
 */
interface ConfigSyncInitializerInterface {

  /**
   * Initializes the merge storage with available configuration updates.
   *
   * @param bool $retain_active_overrides
   *   Whether to retain configuration customizations in the active
   *   configuration storage. Defaults to TRUE.
   */
  public function initialize($retain_active_overrides = TRUE);

}
