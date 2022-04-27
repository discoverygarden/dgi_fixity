<?php

namespace Drupal\dgi_fixity\Plugin\Action;

/**
 * Enable periodic checks on the the entity.
 *
 * @Action(
 *   id = "dgi_fixity:periodic_enable_action",
 *   action_label = @Translation("Enable periodic checks"),
 *   deriver = "Drupal\dgi_fixity\Plugin\Action\Derivative\FixityCheckActionDeriver",
 * )
 */
class PeriodicEnableAction extends FixityCheckActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $check = $this->getCheck($entity);
    if ($check) {
      $check->setPeriodic(TRUE);
      $check->save();
    }
  }

}
