<?php

namespace Drupal\dgi_fixity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Url;

/**
 * Formats file reference as a link to the file.
 *
 * @FieldFormatter(
 *   id = "dgi_fixity_file_reference",
 *   label = @Translation("Fixity File Reference"),
 *   description = @Translation("Display a link to the file being referenced by a Fixity Check."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class FileReferenceFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\file\Entity\File $entity */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();
      $uri = $entity->createFileUrl(FALSE);
      if (isset($uri)) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => Url::fromUri($uri),
        ];
      }
      else {
        $elements[$delta] = ['#plain_text' => $label];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }
    return $elements;
  }

}
