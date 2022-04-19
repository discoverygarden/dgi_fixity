<?php

namespace Drupal\dgi_fixity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dgi_fixity\FixityCheckInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete a fixity_check revision.
 *
 * @internal
 */
class RevisionDeleteForm extends ConfirmFormBase {

  /**
   * The fixity_check revision to delete.
   *
   * @var \Drupal\dgi_fixity\FixityCheckInterface
   */
  protected $revision;

  /**
   * Entity revision storage.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $storage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $storage
   *   The revisionable storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(RevisionableStorageInterface $storage, DateFormatterInterface $date_formatter) {
    $this->storage = $storage;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('fixity_check'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dgi_fixity_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format(
        $this->revision->getPerformed()
      ),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.fixity_check.fixity_audit', [
      'fixity_check' => $this->revision->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FixityCheckInterface $fixity_check_revision = NULL) {
    $this->revision = $fixity_check_revision;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->storage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Fixity Check: deleted %title revision %revision.', [
      '%title' => $this->revision->label(),
      '%revision' => $this->revision->getRevisionId(),
    ]);
    $this->messenger()->addStatus(
      $this->t('Revision from %revision-date of %title has been deleted.',
      [
        '%revision-date' => $this->dateFormatter->format(
          $this->revision->getPerformed()
        ),
        '%title' => $this->revision->label(),
      ]
    ));
    $form_state->setRedirect('entity.fixity_check.fixity_audit', [
      'fixity_check' => $this->revision->id(),
    ]);
  }

}
