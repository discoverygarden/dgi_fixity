<?php

namespace Drupal\dgi_fixity\Form;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure this module.
 *
 * @internal
 */
class SettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'dgi_fixity.settings';
  const SOURCES = 'sources';
  const THRESHOLD = 'threshold';
  const BATCH_SIZE = 'batch_size';
  const NOTIFY_STATUS = 'notify_status';
  const NOTIFY_USER = 'notify_user';
  const NOTIFY_USER_THRESHOLD = 'notify_user_threshold';

  const NOTIFY_STATUS_NEVER = 0;
  const NOTIFY_STATUS_ALWAYS = 1;
  const NOTIFY_STATUS_ERROR = 2;

  // This is not stored as a configuration value but as a state value
  // Though we allow the user to view it and reset it via this form.
  const STATE_LAST_NOTIFICATION = 'dgi_fixity.last_notification';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state manager.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Manages entity type plugin definitions.
   * @param \Drupal\Core\State\StateInterface $state
   *   State manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('config.factory'),
        $container->get('entity_type.manager'),
        $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dgi_fixity_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);
    $displays = Views::getApplicableViews('entity_reference_display');
    $view_storage = $this->entityTypeManager->getStorage('view');
    $sources = [];
    foreach ($displays as $data) {
      [$view_id, $display_id] = $data;
      /** @var \Drupal\views\Entity\View $view */
      $view = $view_storage->load($view_id);
      $tag = $view->getExecutable()->storage->get('tag');
      // Only list views tagged with 'fixity'.
      if (in_array('fixity', Tags::explode($tag))) {
        $entity_type = $view->getExecutable()->getBaseEntityType();
        // Only use views that return 'file' entities, as the batch and workers
        // dynamically filter the view by relating it to 'fixity_checks' that
        // do not yet have their 'periodic' flag set to TRUE.
        if ($entity_type && $entity_type->id() == 'file') {
          $display = $view->get('display');
          $set_name = $view_id . ':' . $display_id;
          $sources[$set_name] = $display[$display_id]['display_title'] . ' (' . $set_name . ')';
        }
      }
    }

    $form['checks'] = [
      '#type' => 'details',
      '#title' => $this->t('Fixity Checks'),
      '#open' => TRUE,
      static::SOURCES => [
        '#title' => $this->t('File Selection'),
        '#description' => $this->t('
          <p>Select one or more <strong>Views</strong>. Whose results are used to determine which files have periodic checks enabled according to the schedule below.</p>
          <p>Only <em>File Entity</em> <strong>Views</strong> are supported.</p>
          <p>Only <strong>entity_reference</strong> or <strong>entity_reference_revisions</strong> displays are supported.</p>
          <p>If selecting multiple Views ideally they should not overlap, if only for the sake of efficiency.</p>
          <p>Views must be have an administrative tag <strong>fixity</strong> to appear in this list.</p>
        '),
        '#type' => 'checkboxes',
        '#options' => $sources,
        '#default_value' => $config->get(static::SOURCES) ?: [],
      ],
      static::THRESHOLD => [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Time elapsed'),
        '#description' => $this->t('
          <p>Time threshold is relative to "<em>now</em>". For example "<em>-1 month</em>" would prevent any checks that occurred less than a month ago.</p>
          <p>Check <a href="https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative">Relative Formats</a> for acceptable values</p>
        '),
        '#default_value' => $config->get(static::THRESHOLD) ?: '-1 month',
        '#element_validate' => [
          [$this, 'validateThreshold'],
        ],
      ],
      static::BATCH_SIZE => [
        '#type' => 'number',
        '#required' => TRUE,
        '#title' => $this->t('Batch size'),
        '#description' => $this->t('
          <p>Set how many files will be processed at once when performing a batch / cron job</p>
        '),
        '#default_value' => 100,
      ],
    ];

    // Default to the admin user if not given.
    $user = $config->get(static::NOTIFY_USER);
    $user = is_int($user) ?
      $this->entityTypeManager->getStorage('user')->load($user) :
      $this->entityTypeManager->getStorage('user')->load(1);

    $notification_threshold = $config->get(static::NOTIFY_USER_THRESHOLD) ?: '-1 week';
    $last_notification = $this->state->get(static::STATE_LAST_NOTIFICATION);
    if ($last_notification !== NULL) {
      $next_notification = $last_notification + (time() - strtotime($notification_threshold));
    }

    $form['notify'] = [
      '#type' => 'details',
      '#title' => $this->t('Notifications'),
      '#description' => $this->t('Notifications are sent by email to the selected user.'),
      '#open' => TRUE,
      static::NOTIFY_STATUS => [
        '#type' => 'select',
        '#title' => $this->t('Notification Status'),
        '#description' => $this->t('
          <p>Choose under what conditions should notifications be sent to the selected user.</p>
        '),
        '#options' => [
          static::NOTIFY_STATUS_NEVER => $this->t('Never'),
          static::NOTIFY_STATUS_ALWAYS => $this->t('Always'),
          static::NOTIFY_STATUS_ERROR => $this->t('Only if error'),
        ],
        '#default_value' => $config->get(static::NOTIFY_STATUS) ?? static::NOTIFY_STATUS_ERROR,
      ],
      static::NOTIFY_USER => [
        '#type' => 'entity_autocomplete',
        '#required' => TRUE,
        '#title' => $this->t('User'),
        '#description' => $this->t('
          <p>The user to be notified should one or more fixity checks fail.</p>
          <p>The user will be notified by email.</p>
        '),
        '#target_type' => 'user',
        '#default_value' => $user,
        '#selection_settings' => [
          'include_anonymous' => FALSE,
        ],
      ],
      static::NOTIFY_USER_THRESHOLD => [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Time elapsed'),
        '#description' => $this->t('
          <p>Time threshold is relative to "<em>now</em>". For example "<em>-1 week</em>" would mean a week must pass between notifications.</p>
          <p>Check <a href="https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative">Relative Formats</a> for acceptable values</p>
        '),
        '#default_value' => $notification_threshold,
        '#element_validate' => [
          [$this, 'validateThreshold'],
        ],
      ],
      'last' => $last_notification ?
        [
          '#type' => 'details',
          '#title' => $this->t('Last notification'),
          '#description' => $this->t('
            <p>The last notification was sent on %last.</p>
            <p>At earliest the next message can be sent %next.</p>
            ', [
              '%last' => date(DATE_RFC7231, $last_notification),
              '%next' => date(DATE_RFC7231, $next_notification),
            ]
          ),
          static::STATE_LAST_NOTIFICATION => [
            '#type' => 'button',
            '#value' => $this->t('Reset'),
            '#limit_validation_errors' => [],
            '#executes_submit_callback' => TRUE,
            '#submit' => [
              [$this, 'resetLastNotification'],
            ],
          ],
        ] : NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Element validate callback; validate the threshold is valid.
   */
  public function validateThreshold(array $element, FormStateInterface $form_state, array $form) {
    $value = $form_state->getValue($element['#parents']);
    if (strtotime($value) === FALSE) {
      $form_state->setError($element, $this->t('The given threshold is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue(static::NOTIFY_USER);
    /** @var \Drupal\user\Entity\User $user */
    $user = is_numeric($value) ?
      $this->entityTypeManager->getStorage('user')->load($value) :
      NULL;
    if ($user === NULL) {
      // Just a precaution the default form element validation should
      // catch this case anyways.
      $form_state->setError($form['notify'][static::NOTIFY_USER], $this->t('The given user does not exist.'));
    }
    elseif ($user->getEmail() === NULL) {
      $form_state->setError($form['notify'][static::NOTIFY_USER], $this->t('The given user does <em>not</em> have an email address associated with their account.'));
    }
  }

  /**
   * Resets the stored last notification.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetLastNotification(array &$form, FormStateInterface $form_state) {
    $this->state->delete(static::STATE_LAST_NOTIFICATION);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config(static::CONFIG_NAME);
    $sources = array_keys(array_filter($values[static::SOURCES]));
    $config
      ->set(static::SOURCES, array_combine($sources, $sources))
      ->set(static::THRESHOLD, $values[static::THRESHOLD])
      ->set(static::BATCH_SIZE, $values[static::BATCH_SIZE])
      ->set(static::NOTIFY_STATUS, $values[static::NOTIFY_STATUS])
      ->set(static::NOTIFY_USER, $values[static::NOTIFY_USER])
      ->set(static::NOTIFY_USER_THRESHOLD, $values[static::NOTIFY_USER_THRESHOLD])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
