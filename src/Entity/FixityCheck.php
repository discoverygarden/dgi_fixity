<?php

namespace Drupal\dgi_fixity\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dgi_fixity\FixityCheckInterface;
use Drupal\file\Entity\File;

/**
 * Defines the fixity_check entity class.
 *
 * @ContentEntityType(
 *   id = "fixity_check",
 *   label = @Translation("Fixity Check"),
 *   label_collection = @Translation("Audit"),
 *   label_singular = @Translation("Fixity Check"),
 *   label_plural = @Translation("Fixity Checks"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Fixity Check",
 *     plural = "@count Fixity Checks"
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\dgi_fixity\FixityCheckStorage",
 *     "storage_schema" = "Drupal\dgi_fixity\FixityCheckStorageSchema",
 *     "list_builder" = "Drupal\dgi_fixity\FixityCheckListBuilder",
 *     "views_data" = "Drupal\dgi_fixity\FixityCheckViewsData",
 *     "access" = "Drupal\dgi_fixity\FixityCheckAccessControlHandler",
 *     "form" = {
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "fixity-check" = "Drupal\dgi_fixity\Form\CheckForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\dgi_fixity\Routing\FixityCheckRouteProvider"
 *     },
 *   },
 *   base_table = "fixity_check",
 *   revision_table = "fixity_check_revision",
 *   show_revision_ui = FALSE,
 *   translatable = FALSE,
 *   common_reference_target = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *   },
 *   links = {
 *     "canonical" = "/fixity/{fixity_check}",
 *     "edit-form" = "/fixity/{fixity_check}/edit",
 *     "fixity-audit" = "/fixity/{fixity_check}/audit",
 *     "fixity-check" = "/fixity/{fixity_check}/check",
 *     "delete-form" = "/fixity/{fixity_check}/delete",
 *     "delete-multiple-form" = "/fixity/delete",
 *     "collection" = "/admin/reports/fixity",
 *   },
 *   admin_permission = "administer fixity checks",
 * )
 */
class FixityCheck extends ContentEntityBase implements FixityCheckInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('File'))
      ->setDescription(new TranslatableMarkup('The file entity the fixity check was performed against.'))
      ->setRequired(TRUE)
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      // It's not possible to have two fixity checks for the same file, they
      // should result in different versions of the same fixity_check entity.
      ->addConstraint('UniqueFieldEntityReference')
      ->setSetting('target_type', 'file')
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'dgi_fixity_file_reference',
        'weight' => 0,
      ]);

    $fields['state'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('State'))
      ->setDescription(new TranslatableMarkup('A flag indicating the state of the whether the check passed or not.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE)
      ->setInitialValue(static::STATE_UNDEFINED)
      ->setDefaultValue(static::STATE_UNDEFINED)
      // Define this via an options provider once.
      // https://www.drupal.org/node/2329937 is completed.
      ->addPropertyConstraints('value', [
        'AllowedValues' => ['callback' => static::class . '::getAllowedStates'],
      ])
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'dgi_fixity_state',
        'weight' => 1,
      ]);

    // A value of 0 indicates the check was never performed even though it is
    // a unix-timestamp, which means it is technically 1970-01-01 00:00:00.
    $fields['performed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Performed'))
      ->setDescription(t('The time the check was performed, 0 if never performed.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE)
      ->setInitialValue(0)
      ->setDefaultValue(0)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp_ago',
        'weight' => 2,
      ]);

    $fields['periodic'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Periodic'))
      ->setDescription(t('Enable/disable periodic fixity checks.'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setInitialValue(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'weight' => 3,
      ]);

    $fields['queued'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Queued'))
      ->setDescription(t('Time when this file was queued for fixity, 0 if not queued.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setInitialValue(0)
      ->setDefaultValue(0)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // Disallow changes to state / performed on existing revisions.
    // Unless this is the first revision and it the check was never performed.
    if (!$this->isNewRevision() && !($this->isLatestRevision() && !$this->original->wasPerformed())) {
      $immutable_fields = ['state', 'performed'];
      foreach ($immutable_fields as $field) {
        if ($this->{$field}->hasAffectingChanges($this->original->{$field}, LanguageInterface::LANGCODE_NOT_SPECIFIED)) {
          throw new \LogicException("Entity type {$this->getEntityTypeId()} does not support modifying the '{$field}' field of existing revisions.");
        }
      }
    }
    // The file field is immutable after creation.
    if ($this->original && $this->file->hasAffectingChanges($this->original->file, LanguageInterface::LANGCODE_NOT_SPECIFIED)) {
      throw new \LogicException("Entity type {$this->getEntityTypeId()} does not support modifying the file field after creation.");
    }
    // If performed has changed force queued to zero.
    if ($this->original && $this->performed->hasAffectingChanges($this->original->performed, LanguageInterface::LANGCODE_NOT_SPECIFIED)) {
      $this->setQueued(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);
    // Disallow creating revisions if performed is not set.
    if (!$this->isNew() && $this->isNewRevision() && $record->performed === 0) {
      throw new \LogicException("Entity type {$this->getEntityTypeId()} does not support creating new revisions without where the performed field is not set.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setNewRevision($value = TRUE) {
    parent::setNewRevision($value);
    // Reset the state and performed timestamp when creating a new revision
    // from an existing fixity_check.
    if (!$this->isNew() && $value) {
      $this->state = static::STATE_UNDEFINED;
      unset($this->performed);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): ?File {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $file */
    $file = $this->file;
    return $file->isEmpty() ? NULL : $file->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setFile(File $file): FixityCheckInterface {
    $this->set('file', $file);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): int {
    return $this->state->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setState(int $state): FixityCheckInterface {
    if (!in_array($state, static::getAllowedStates())) {
      throw new \InvalidArgumentException("Invalid state '$state' has been given");
    }
    $this->set('state', $state);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateLabel(): string {
    return static::getStateProperty($this->getState(), 'label');
  }

  /**
   * {@inheritdoc}
   */
  public static function getStateProperty(int $state, string $property) {
    return static::STATES[$state][$property] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function passed(): bool {
    $state = $this->getState();
    return static::STATES[$state]['passed'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPeriodic(): bool {
    return $this->periodic->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPeriodic(bool $periodic): FixityCheckInterface {
    $this->set('periodic', $periodic);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPerformed(): int {
    return $this->performed->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPerformed(int $performed): FixityCheckInterface {
    $this->set('performed', $performed);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function wasPerformed(): bool {
    return $this->getPerformed() !== 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueued(): int {
    return $this->queued->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueued(int $queued): FixityCheckInterface {
    $this->set('queued', $queued);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $file = $this->getFile();
    return ($file === NULL) ?
      $this->t('Fixity Check') :
      $file->label();
  }

  /**
   * Defines allowed states for AllowedValues constraints.
   *
   * @return int[]
   *   The allowed states.
   */
  public static function getAllowedStates() {
    return array_keys(static::STATES);
  }

}
