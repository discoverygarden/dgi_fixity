<?php

namespace Drupal\dgi_fixity\Plugin\Action;

/**
 * Performs a fixity checks on the entity.
 *
 * @Action(
 *   id = "dgi_fixity:check_action",
 *   action_label = @Translation("Check"),
 *   deriver = "Drupal\dgi_fixity\Plugin\Action\Derivative\FixityCheckActionDeriver",
 * )
 */
class CheckAction extends FixityCheckActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $check = $this->getCheck($entity);
    if ($check) {
      $this->fixity->check($check->getFile(), TRUE);
    }
  }

}
