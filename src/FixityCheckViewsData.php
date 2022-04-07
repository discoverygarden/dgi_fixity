<?php

namespace Drupal\dgi_fixity;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the fixity_check entity type.
 */
class FixityCheckViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['fixity_check']['table']['wizard_id'] = 'fixity_check';
    $data['fixity_check']['table']['group'] = $this->t('Fixity Check');
    $data['fixity_check']['table']['join'] = [
      'fixity_check_revision' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
      ],
      'file_managed' => [
        'left_field' => 'fid',
        'field' => 'file',
      ],
    ];
    $data['fixity_check_revision']['table']['wizard_id'] = 'fixity_check_revision';
    $data['fixity_check_revision']['table']['group'] = $this->t('Fixity Check revision');
    $data['fixity_check_revision']['table']['join'] = [
      'fixity_check' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
      ],
      'file_managed' => [
        'left_field' => 'fid',
        'field' => 'file',
      ],
    ];
    return $data;
  }

}
