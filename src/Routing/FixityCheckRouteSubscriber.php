<?php

namespace Drupal\dgi_fixity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class FixityCheckRouteSubscriber extends RouteSubscriberBase {

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * Subscriber for Fixity Check routes.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity check service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, FixityCheckServiceInterface $fixity) {
    $this->entityTypeManager = $entity_manager;
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $supported_entity_types = array_merge(['fixity_check'], $this->fixity->fromEntityTypes());
    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($supported_entity_types as $entity_type_id) {
      $entity_type = $definitions[$entity_type_id];
      if ($route = $this->getFixityAuditRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.fixity_audit", $route);
      }
      if ($route = $this->getFixityCheckRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.fixity_check", $route);
      }
    }
  }

  /**
   * Gets the fixity check 'Audit' route for the given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getFixityAuditRoute(EntityTypeInterface $entity_type) {
    if ($fixity_audit = $entity_type->getLinkTemplate('fixity-audit')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($fixity_audit);
      $route
        ->addDefaults([
          '_controller' => '\Drupal\dgi_fixity\Controller\FixityCheckController::entityAudit',
          '_title' => 'Audit',
        ])
        ->addRequirements([
          '_permission' => 'view fixity checks',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_fixity_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      return $route;
    }
  }

  /**
   * Gets the fixity check 'Check' route for the given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getFixityCheckRoute(EntityTypeInterface $entity_type) {
    if ($fixity_audit = $entity_type->getLinkTemplate('fixity-check')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($fixity_audit);
      $route
        ->addDefaults([
          '_entity_form' => "{$entity_type_id}.fixity-check",
        ])
        ->addRequirements([
          '_permission' => 'administer fixity checks',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_fixity_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      return $route;
    }
  }

}
