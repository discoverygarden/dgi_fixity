<?php

namespace Drupal\dgi_fixity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueFieldEntityReferenceConstraint constraint.
 */
class UniqueFieldEntityReferenceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $items */
    /** @var UniqueFieldEntityReferenceConstraint $constraint */
    /** @var \Drupal\Core\Field\EntityReferenceFieldItem $item */
    if (!$item = $items->first()) {
      return;
    }
    $target_property = $item->mainPropertyName();
    $field_name = $items->getFieldDefinition()->getName();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');

    $query = \Drupal::entityQuery($entity_type_id);
    $query->accessCheck(FALSE);

    $entity_id = $entity->id();
    // Using isset() instead of !empty() as 0 and '0' are valid ID values for
    // entity types using string IDs.
    if (isset($entity_id)) {
      $query->condition($id_key, $entity_id, '<>');
    }
    $targets = [];
    foreach ($items as $item) {
      $targets[] = $item->{$target_property};
    }

    $targets = array_filter($targets);
    $results = $query
      ->condition($field_name, $targets, 'IN')
      ->range(0, 1)
      ->execute();

    if (count($results) > 0) {
      $used_by_entity_id = reset($results);
      $used_by_entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($used_by_entity_id);
      foreach ($used_by_entity->{$field_name}->referencedEntities() as $reference) {
        if (in_array($reference->id(), $targets)) {
          $this->context->addViolation($constraint->message, [
            '@referenced_entity_type' => $reference->getEntityTypeId(),
            '%entity_referenced' => $reference->id(),
            '@entity_type' => $entity_type_id,
            '%entity' => $used_by_entity_id,
          ]);
          break;
        }
      }
    }
  }

}
