<?php

namespace Drupal\dgi_fixity\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Used for creating 'fixity_check' views with the wizard.
 *
 * @ViewsWizard(
 *   id = "fixity_check_revision",
 *   base_table = "fixity_check_revision",
 *   title = @Translation("Fixity Check Revision"),
 * )
 */
class FixityCheckRevision extends WizardPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $createdColumn = 'fixity_check_revision-performed';

}
