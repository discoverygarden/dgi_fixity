<?php

namespace Drupal\dgi_fixity\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dgi_fixity\FixityCheckInterface;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for fixity_check entity-based actions.
 */
abstract class FixityCheckActionBase extends EntityActionBase {

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * Constructs a CheckAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FixityCheckServiceInterface $fixity) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
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
      $container->get('entity_type.manager'),
      $container->get('dgi_fixity.fixity_check'),
    );
  }

  /**
   * Gets the related fixity_check entity for the given entity if possible.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The related entity.
   *
   * @return \Drupal\dgi_fixity\FixityCheckInterface
   *   The related fixity_check if found or NULL.
   */
  protected function getCheck(EntityInterface $entity): ?FixityCheckInterface {
    return $entity instanceof FixityCheckInterface ?
      $entity :
      $this->fixity->fromEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // If it exists and the user has permission to administer fixity checks.
    $result = $this->getCheck($object) && $account->hasPermission('administer fixity checks') ?
      AccessResult::allowed() :
      AccessResult::forbidden();

    return $return_as_object ? $result : $result->isAllowed();
  }

}
