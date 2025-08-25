<?php

namespace Drupal\dgi_fixity\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes configured sources to find new files to periodically check.
 *
 * @QueueWorker(
 *   id = "dgi_fixity.process_source",
 *   title = @Translation("Fixity Check Process Source"),
 *   cron = {"time" = 15}
 * )
 */
class ProcessSourceWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FixityCheckWorker instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity check service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FixityCheckServiceInterface $fixity, AccountSwitcherInterface $account_switcher, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fixity = $fixity;
    $this->accountSwitcher = $account_switcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dgi_fixity.fixity_check'),
      $container->get('account_switcher'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // To avoid expensive access calls.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $account = $user_storage->load(1);

    if (!($account instanceof AccountInterface)) {
      return;
    }

    try {
      $this->accountSwitcher->switchTo($account);

      /** @var \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity */
      $fixity = \Drupal::service('dgi_fixity.fixity_check');
      $view = $fixity->source($data, 1000);
      if (!$view) {
        // Failed to load view? Abort.
        return;
      }
      $view->execute();
      // Only processes those which have not already enabled periodic checks.
      foreach ($view->result as $row) {
        /** @var \Drupal\dgi_fixity\FixityCheckInterface $check */
        $check = $view->field['periodic']->getEntity($row);
        $check->setPeriodic(TRUE);
        $check->save();
      }
      // Not finished processing.
      if (count($view->result) !== 0) {
        $this->accountSwitcher->switchBack();
        throw new RequeueException();
      }
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

}
