<?php

namespace Drupal\dgi_fixity\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Drupal\user\Entity\User;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FixityCheckServiceInterface $fixity) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fixity = $fixity;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // To avoid expensive access calls
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity */
    $fixity = \Drupal::service('dgi_fixity.fixity_check');
    $view = $fixity->source($data, 1000);
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
      throw new RequeueException();
    }

    $account_switcher->switchBack();
  }

}
