<?php

namespace Drupal\config_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_sync\ConfigSyncInitializerInterface;
use Drupal\config_sync\ConfigSyncListerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigSyncInitialize extends FormBase {

  /**
   * @var \Drupal\config_sync\configSyncInitializer
   */
  protected $configSyncInitializer;

  /**
   * Constructs a new ConfigSync object.
   *
   * @param \Drupal\config_sync\ConfigSyncInitializerInterface $config_sync_initializer
   *   The configuration syncronizer initializer.
   */
  public function __construct(ConfigSyncInitializerInterface $config_sync_initializer) {
    $this->configSyncInitializer = $config_sync_initializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config_sync.initializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_sync_initialize';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#markup' => $this->t('Use the button below to initialize data to be imported from updated modules and themes.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Initialize'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configSyncInitializer->initializeAll();
    $form_state->setRedirect('config_sync.import');
  }

}
