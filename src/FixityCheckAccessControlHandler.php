<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access control handler for fixity_check entities.
 */
class FixityCheckAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\dgi_fixity\FixityCheckInterface $entity */
    $admin_permission = $this->entityType->getAdminPermission();

    switch ($operation) {
      case 'view':
      case 'view revision':
        return AccessResult::allowedIfHasPermission($account, 'view fixity checks')->cachePerPermissions();

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, $admin_permission)->cachePerPermissions();

      case 'delete revision':
        // Not possible to delete the default revision, instead the user
        // should delete the actual entity.
        if ($entity->isDefaultRevision()) {
          return AccessResult::forbidden()->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, $admin_permission)->cachePerPermissions();

      default:
        return AccessResult::forbidden()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission());
  }

}
