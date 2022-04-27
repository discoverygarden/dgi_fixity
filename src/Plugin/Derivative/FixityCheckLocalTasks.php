<?php

namespace Drupal\dgi_fixity\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates fixity related local tasks.
 */
class FixityCheckLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a local tasks for fixity checks on supported entities.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager) {
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('fixity-audit')) {
        $this->derivatives["$entity_type_id.fixity"] = [
          'route_name' => "entity.{$entity_type_id}.fixity_audit",
          'title' => $this->t('Fixity'),
          'base_route' => "entity.{$entity_type_id}.canonical",
          'weight' => 10,
        ];
        $this->derivatives["$entity_type_id.fixity.audit"] = [
          'route_name' => "entity.$entity_type_id.fixity_audit",
          'title' => $this->t('Audit'),
          'parent_id' => "dgi_fixity.entities:$entity_type_id.fixity",
          'weight' => 10,
        ];
        if ($entity_type->hasLinkTemplate('fixity-check')) {
          $this->derivatives["$entity_type_id.fixity.check"] = [
            'route_name' => "entity.$entity_type_id.fixity_check",
            'title' => $this->t('Check'),
            'parent_id' => "dgi_fixity.entities:$entity_type_id.fixity",
            'weight' => 10,
          ];
        }
      }
    }
    return $this->derivatives;
  }

}
