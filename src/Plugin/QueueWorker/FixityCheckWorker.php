<?php

namespace Drupal\dgi_fixity\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Drupal\dgi_fixity\FixityCheckInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function PHPUnit\Framework\isEmpty;

/**
 * Performs a fixity check.
 *
 * @QueueWorker(
 *   id = "dgi_fixity.fixity_check",
 *   title = @Translation("Fixity Checks"),
 *   cron = {"time" = 15}
 * )
 */
class FixityCheckWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    if ($data instanceof FixityCheckInterface) {
      if (isEmpty($data->getFile())) {
        $data->delete();
        return;
      }
      /** @var \Drupal\dgi_fixity\FixityCheckInterface $data */
      $this->fixity->check($data->getFile());
    }
  }

}
