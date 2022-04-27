<?php

namespace Drupal\dgi_fixity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if an entity field has a unique entity reference value.
 *
 * @Constraint(
 *   id = "UniqueFieldEntityReference",
 *   label = @Translation("Unique field entity reference constraint", context = "Validation"),
 *   type = { "entity_reference" }
 * )
 */
class UniqueFieldEntityReferenceConstraint extends Constraint {
  /**
   * The message to display to the user on invalid condition.
   *
   * @var string
   */
  public $message = 'The @referenced_entity_type: %entity_referenced is already a referenced by @entity_type: %entity';

}
