<?php

namespace Drupal\dgi_fixity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dgi_fixity\FixityCheckInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for fixity_check revisions.
 *
 * @ingroup fixity_check_access
 */
class FixityCheckRevisionAccessCheck implements AccessInterface {

  /**
   * The fixity_check storage.
   *
   * @var \Drupal\dgi_fixity\FixityCheckStorageInterface
   */
  protected $storage;

  /**
   * The fixity_check access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessControlHandler;

  /**
   * A static cache of access checks.
   *
   * @var array
   */
  protected $access = [];

  /**
   * Constructs a new FixityCheckRevisionAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->storage = $entity_type_manager->getStorage('fixity_check');
    $this->accessControlHandler = $entity_type_manager->getAccessControlHandler('fixity_check');
  }

  /**
   * Checks routing access for the fixity_check revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $fixity_check_revision
   *   (optional) The fixity_check revision ID. If not specified, but 
   *   $fixity_check is, access is checked for that object's revision.
   * @param \Drupal\dgi_fixity\FixityCheckInterface $fixity_check
   *   (optional) A fixity_check object. Used for checking access to a 
   *   fixity_check's default revision when $fixity_check_revision is
   *   unspecified. Ignored when $fixity_check_revision is specified. 
   *   If neither $fixity_check_revision nor $fixity_check are specified, 
   *   then access is denied.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, $fixity_check_revision = NULL, FixityCheckInterface $fixity_check = NULL) {
    if ($fixity_check_revision) {
      $fixity_check = $this->storage->loadRevision($fixity_check_revision);
    }
    $operation = $route->getRequirement('_access_fixity_check_revision');
    return AccessResult::allowedIf($fixity_check && $this->checkAccess($fixity_check, $account, $operation))->cachePerPermissions()->addCacheableDependency($fixity_check);
  }

  /**
   * Checks fixity_check revision access.
   *
   * @param \Drupal\dgi_fixity\FixityCheckInterface $fixity_check
   *   The fixity_check revision to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $op
   *   (optional) The specific operation being checked. Defaults to 'view'.
   *
   * @return bool
   *   TRUE if the operation may be performed, FALSE otherwise.
   */
  public function checkAccess(FixityCheckInterface $fixity_check, AccountInterface $account, $op = 'view') {
    $map = [
      'view' => 'view fixity checks',
      'delete' => 'administer fixity checks',
    ];

    if (!$fixity_check || !isset($map[$op])) {
      // If there was no fixity_check to check against, or the $op was not one of the
      // supported ones, we return access denied.
      return FALSE;
    }

    // Statically cache access by revision ID, user account ID, and operation.
    $cid = $fixity_check->getRevisionId() . ':' . $account->id() . ':' . $op;

    if (!isset($this->access[$cid])) {
      $has_perm = $account->hasPermission($map[$op]);
      $has_admin_perm = $account->hasPermission($fixity_check->getEntityType()->getAdminPermission());
      // Perform basic permission checks first.
      if (!$has_perm && !$has_admin_perm) {
        $this->access[$cid] = FALSE;
        return $this->access[$cid];
      }
      // Do not allow for the deletion of the the default revision.
      elseif ($fixity_check->isDefaultRevision() && $op === 'delete') {
        $this->access[$cid] = FALSE;
      }
      elseif ($has_admin_perm) {
        $this->access[$cid] = TRUE;
      }
      else {
        // First check the access to the default revision and finally, if the
        // fixity_check passed in is not the default revision then check access to
        // that, too.
        $this->access[$cid] = $this->accessControlHandler->access($this->storage->load($fixity_check->id()), $op, $account) && ($fixity_check->isDefaultRevision() || $this->accessControlHandler->access($fixity_check, $op, $account));
      }
    }
    return $this->access[$cid];
  }

}
