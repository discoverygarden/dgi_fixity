<?php

/**
 * @file
 * Provide views data for file.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function dgi_fixity_views_data_alter(&$data) {
  // Reverse relationship on fixity_check.file to file_managed.fid.
  $field_id = 'file';
  $entity_type_id = 'fixity_check';

  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_type = $entity_type_manager->getDefinition($entity_type_id);

  /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');
  $field_definitions = $entity_field_manager->getBaseFieldDefinitions($entity_type_id);
  $field_type = $field_definitions[$field_id];

  // Allow relations to both the entity and revision base table.
  // The fixity_check entity does use data tables.
  $tables = [$entity_type->getBaseTable(), $entity_type->getRevisionTable()];
  foreach ($tables as $table) {
    $group = $data[$table]['table']['group'];
    $pseudo_field_name = 'reverse_' . $field_type->getName() . '_' . $table;
    $data['file_managed'][$pseudo_field_name] = [
      'real field' => $field_type->getName(),
      'relationship' => [
        'title' => \t('@entity using @field',
          [
            '@entity' => $entity_type->getLabel(),
            '@field' => $field_type->getLabel(),
          ],
        ),
        'label' => $group,
        'help' => \t('Relate each @entity with a @field set to the file.',
          [
            '@entity' => $entity_type->getLabel(),
            '@field' => $field_type->getLabel(),
          ],
        ),
        'group' => $group,
        'id' => 'standard',
        'base' => $table,
        'base field' => $field_type->getName(),
        'relationship field' => 'fid',
      ],
    ];
  }
}
