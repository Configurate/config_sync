<?php

namespace Drupal\config_sync;

/**
 * Provides methods for updating site configuration from extensions.
 */
interface ConfigSyncInitializerInterface {

  /**
   * Initializes the merge storage with available configuration updates.
   *
   * @param boolean $safe_only
   *   Whether to apply only changes considered safe to make. Defaults to
   *   TRUE.
   */
  public function initialize($safe_only = TRUE);

}
