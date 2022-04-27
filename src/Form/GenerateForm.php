<?php

namespace Drupal\dgi_fixity\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dgi_fixity\FixityCheckBatchGenerate;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates a fixity check entity for all previously existing files.
 *
 * @internal
 */
class GenerateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dgi_fixity_generate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\dgi_fixity\FixityCheckStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('fixity_check');
    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('
          <p>Submitting this form generate fixity checks for @count files.</p>
          <p>Generally this should only be required when the module is first installed.</p>
        ',
        ['@count' => $storage->countMissing()]
      ),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate'),
        '#button_type' => 'primary',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = FixityCheckBatchGenerate::build();
    batch_set($batch);
  }

}
