<?php

namespace Drupal\dgi_fixity\Plugin\Action\Derivative;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityActionDeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action deriver that finds publishable entity types.
 *
 * @see \Drupal\Core\Action\Plugin\Action\PublishAction
 * @see \Drupal\Core\Action\Plugin\Action\UnpublishAction
 */
class FixityCheckActionDeriver extends EntityActionDeriverBase {

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * Constructs a new EntityActionDeriverBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, FixityCheckServiceInterface $fixity) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('dgi_fixity.fixity_check'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type) {
    $supported_entity_types = array_merge(['fixity_check'], $this->fixity->fromEntityTypes());
    return in_array($entity_type->id(), $supported_entity_types);
  }

}
