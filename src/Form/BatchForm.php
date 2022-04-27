<?php

namespace Drupal\dgi_fixity\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dgi_fixity\FixityCheckBatchCheck;

/**
 * Trigger a batch check of the files selected by the modules configuration.
 *
 * @internal
 */
class BatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dgi_fixity_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Submitting this form will perform fixity checks against all files with periodic checks enabled.<br>This will automatically be done via cron, but it can be performed manually here.'),
    ];
    $form['force'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip time elapsed check'),
      '#description' => $this->t('If enabled, all files will be checked without regard to the time elapsed since the previous check was performed on the selected file.'),
      '#default' => FALSE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Check'),
        '#button_type' => 'primary',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $force = boolval($form_state->getValue('force'));
    try {
      $batch = FixityCheckBatchCheck::build(NULL, $force);
      batch_set($batch);
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
