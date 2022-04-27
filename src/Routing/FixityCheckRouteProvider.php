<?php

namespace Drupal\dgi_fixity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Sets defaults on HTML routes for the fixity_check entity.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider.
 */
class FixityCheckRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCanonicalRoute($entity_type)) {
      $route->setOption('_admin_route', TRUE);
      return $route;
    }
  }

}
