<?php

namespace Drupal\dgi_fixity\Routing;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts an entity identifier into fixity_check entity.
 */
class FixityCheckConverter extends EntityConverter {

  /**
   * The fixity service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * Construct a new FixityCheckConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, FixityCheckServiceInterface $fixity) {
    parent::__construct($entity_type_manager, $entity_repository);
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = parent::convert($value, $definition, $name, $defaults);
    return $this->fixity->fromEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    $supported_entity_types = $this->fixity->fromEntityTypes();
    if (!empty($definition['type']) && strpos($definition['type'], 'fixity:') === 0) {
      $entity_type_id = substr($definition['type'], strlen('fixity:'));
      if (strpos($definition['type'], '{') !== FALSE) {
        $entity_type_slug = substr($entity_type_id, 1, -1);
        if ($name != $entity_type_slug && in_array($entity_type_slug, $route->compile()->getVariables(), TRUE)) {
          return in_array($entity_type_slug, $supported_entity_types);
        }
      }
      return in_array($entity_type_id, $supported_entity_types);
    }
    return FALSE;
  }

}
