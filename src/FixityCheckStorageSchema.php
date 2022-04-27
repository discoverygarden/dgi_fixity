<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the file schema handler.
 */
class FixityCheckStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping) {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    if ($table_name == $this->storage->getBaseTable()) {
      switch ($field_name) {
        case 'file':
          $this->addSharedTableFieldUniqueKey($storage_definition, $schema, TRUE);
          $this->addSharedTableFieldForeignKey($storage_definition, $schema, 'file_managed', 'fid');
          break;

        case 'state':
        case 'performed':
        case 'periodic':
        case 'queued':
          $this->addSharedTableFieldIndex($storage_definition, $schema, TRUE);
          break;

      }
    }

    return $schema;
  }

}
