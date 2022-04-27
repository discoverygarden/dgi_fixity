<?php

namespace Drupal\dgi_fixity\Plugin\Action;

/**
 * Disables periodic checks on the the entity.
 *
 * @Action(
 *   id = "dgi_fixity:periodic_disable_action",
 *   action_label = @Translation("Disable periodic checks"),
 *   deriver = "Drupal\dgi_fixity\Plugin\Action\Derivative\FixityCheckActionDeriver",
 * )
 */
class PeriodicDisableAction extends FixityCheckActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $check = $this->getCheck($entity);
    if ($check) {
      $check->setPeriodic(FALSE);
      $check->save();
    }
  }

}
