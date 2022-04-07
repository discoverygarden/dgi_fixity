<?php

namespace Drupal\dgi_fixity\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Used for creating 'fixity_check' views with the wizard.
 *
 * @ViewsWizard(
 *   id = "fixity_check",
 *   base_table = "fixity_check",
 *   title = @Translation("Fixity Check"),
 * )
 */
class FixityCheck extends WizardPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $createdColumn = 'fixity_check-performed';

}
