<?php

namespace Drupal\dgi_fixity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\dgi_fixity\Entity\FixityCheck;

/**
 * Displays a human readable label for the state.
 *
 * @FieldFormatter(
 *   id = "dgi_fixity_state",
 *   label = @Translation("State"),
 *   field_types = {
 *     "integer"
 *   },
 * )
 */
class StateFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => FixityCheck::getStateProperty($item->value, 'label')];
    }
    return $elements;
  }

}
