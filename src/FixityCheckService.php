<?php

namespace Drupal\dgi_fixity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dgi_fixity\Entity\FixityCheck;
use Drupal\dgi_fixity\Form\SettingsForm;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\filehash\FileHash;
use Drupal\media\MediaInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Psr\Log\LoggerInterface;

/**
 * Decorates the FileHash services adding additional functionality.
 */
class FixityCheckService implements FixityCheckServiceInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A date time instance.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The logger for this service.
   *
   * @var Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The service to decorate.
   *
   * @var \Drupal\filehash\FileHash
   */
  protected $filehash;

  /**
   * Constructor.
   */
  public function __construct(TranslationInterface $string_translation, ConfigFactoryInterface $config, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, MailManagerInterface $mail_manager, LoggerInterface $logger, FileHash $filehash) {
    $this->stringTranslation = $string_translation;
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->mailManager = $mail_manager;
    $this->logger = $logger;
    $this->filehash = $filehash;
  }

  /**
   * {@inheritdoc}
   */
  public function fromEntityTypes(): array {
    return [
      'media',
      'file',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fromEntity(EntityInterface $entity): ?FixityCheckInterface {
    $entity_type_id = $entity->getEntityTypeId();
    switch ($entity_type_id) {
      case 'media':
        /** @var \Drupal\media\MediaInterface $entity */
        return $this->fromMedia($entity);

      case 'file':
        /** @var \Drupal\file\FileInterface $entity */
        return $this->fromFile($entity);

      default:
        throw new \InvalidArgumentException("Cannot convert {$entity_type_id} to fixity_check.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fromFile($file): ?FixityCheckInterface {
    $fid = $file instanceof FileInterface ? $file->id() : (int) $file;
    // It is only possible to have a single fixity_check entity per-file.
    $results = $this->entityTypeManager->getStorage('fixity_check')->loadByProperties(['file' => $fid]);
    if (count($results) === 1) {
      /** @var \Drupal\dgi_fixity\FixityCheckInterface $fixity_check */
      $fixity_check = reset($results);
    }
    else {
      $fixity_check = FixityCheck::create([
        'file' => $fid,
      ]);
    }
    return $fixity_check;
  }

  /**
   * {@inheritdoc}
   */
  public function fromMedia(MediaInterface $media): ?FixityCheckInterface {
    $fid = $media->getSource()->getSourceFieldValue($media);
    return $this->fromFile($fid);
  }

  /**
   * {@inheritdoc}
   */
  public function threshold(): int {
    $threshold = &drupal_static(__FUNCTION__);
    if (is_null($threshold)) {
      $settings = $this->config->get(SettingsForm::CONFIG_NAME);
      $threshold = strtotime($settings->get(SettingsForm::THRESHOLD), $this->time->getRequestTime());
    }
    return $threshold;
  }

  /**
   * {@inheritdoc}
   */
  public function scheduled(FixityCheckInterface $check): ?int {
    if ($check->getPeriodic()) {
      $now = time();
      if ($check->wasPerformed()) {
        $diff = $now - $this->threshold();
        return $check->getPerformed() + $diff;
      }
      // Never performed, can be performed immediately.
      return $now;
    }
    // Not periodic therefore not scheduled.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function source(string $source, int $limit): ?ViewExecutable {
    // Only process those which have not already enabled periodic checks.
    [$view_id, $display_id] = explode(':', $source);
    $view = Views::getView($view_id);
    if ($view) {
      $view->setDisplay($display_id);
      $view->getDisplay()->setOption('entity_reference_options', ['limit' => $limit]);
      $view->addHandler($display_id, 'relationship', 'file_managed', 'reverse_file_fixity_check');
      $view->addHandler(
        $display_id, 'filter', 'fixity_check', 'periodic',
        ['relationship' => 'reverse_file_fixity_check', 'value' => 0],
        'periodic'
      );
      $view->addHandler(
        $display_id, 'field', 'fixity_check', 'periodic',
        ['relationship' => 'reverse_file_fixity_check'],
      'periodic'
      );
    }
    return $view;
  }

  /**
   * {@inheritdoc}
   */
  public function check(File $file, bool $force = FALSE) {
    /** @var \Drupal\dgi_fixity\Entity\FixityCheckInterface[] $existing_checks */
    $existing_checks = $this->entityTypeManager->getStorage('fixity_check')->loadByProperties(['file' => $file->id()]);
    if (empty($existing_checks)) {
      $check = FixityCheck::create()->setFile($file);
    }
    else {
      // Should only ever be at most one due to the UniqueFieldEntityReference
      // constraint on the file field.
      $check = reset($existing_checks);
      // Do not perform if the threshold for time since the last check has not
      // been exceeded.
      if (!$force) {
        if ($check->getPerformed() > $this->threshold()) {
          return NULL;
        }
      }
      // Trigger a new revision (clears the performed / state fields).
      // If the check has never been performed before do not modify the
      // existing version.
      if ($check->wasPerformed()) {
        $check->setNewRevision();
      }
    }
    $uri = $file->getFileUri();
    // Assume success until proven untrue.
    $state = FixityCheck::STATE_MATCHES;
    // If column is set, only generate that hash.
    foreach ($this->filehash->algos() as $column => $algo) {
      // Nothing to do if the previous checksum value is not known.
      if (!isset($file->{$column})) {
        $state = FixityCheck::STATE_NO_CHECKSUM;
        break;
      }
      // Nothing to do if file URI is empty.
      if (NULL === $uri || '' === $uri || !file_exists($uri)) {
        $state = FixityCheck::STATE_MISSING;
        break;
      }
      // Unreadable files will have NULL hash values.
      elseif (preg_match('/^blake2b_([0-9]{3})$/', $algo, $matches)) {
        $hash = $this->filehash->blake2b($uri, $matches[1] / 8) ?: NULL;
      }
      else {
        $hash = hash_file($algo, $uri) ?: NULL;
      }
      if ($hash === NULL) {
        $state = FixityCheck::STATE_GENERATION_FAILED;
        break;
      }
      if ($file->{$column}->value !== $hash) {
        $state = FixityCheck::STATE_MISMATCHES;
        break;
      }
    }

    $check->setState($state);
    $check->setPerformed($this->time->getRequestTime());
    $check->setQueued(0);
    $check->save();

    // Log results.
    $message = '@entity-type %label: %state';
    $args = [
      '@entity-type' => $check->getEntityType()->getSingularLabel(),
      '%label' => $check->label(),
      '%state' => $check->getStateProperty($check->getState(), 'label'),
      'link' => Link::createFromRoute(
        $this->t('View'),
        'entity.fixity_check.revision',
        [
          'fixity_check' => $check->id(),
          'fixity_check_revision' => $check->getRevisionId(),
        ],
      )->toString(),
    ];
    if ($check->passed()) {
      $this->logger->info($message, $args);
    }
    else {
      $this->logger->error($message, $args);
    }

    return $check;
  }

  /**
   * {@inheritdoc}
   */
  public function stats(): array {
    $storage = $this->entityTypeManager->getStorage('fixity_check');

    // Group all current checks by their state.
    // Ignore those that have not been performed yet.
    $results = $storage->getAggregateQuery('AND')
      ->condition('performed', 0, '!=')
      ->groupBy('state')
      ->aggregate('id', 'COUNT')
      ->execute();

    $failed = 0;
    $states = [];
    foreach ($results as $result) {
      $state = $result['state'];
      $count = $result['id_count'];
      $states[$state] = $count;
      // If there are any checks which have not 'passed', the aggregate state
      // of all checks is failure.
      if (FixityCheck::getStateProperty($state, 'passed') === FALSE) {
        $failed += $count;
      }
    }

    // All active checks.
    $periodic = (int) $storage->getQuery('AND')
      ->count('id')
      ->condition('periodic', TRUE)
      ->execute();

    // All checks performed ever.
    $revisions = (int) $storage->getQuery('AND')
      ->allRevisions()
      ->count('id')
      ->execute();

    // Checks which have exceeded the threshold and should be performed again.
    $threshold = $this->threshold();
    $current = (int) $storage->getQuery('AND')
      ->condition('periodic', TRUE)
      ->condition('performed', $threshold, '>=')
      ->count('id')
      ->execute();

    // Up to date checks.
    $expired = $periodic - $current;

    return [
      'periodic' => [
        'total' => $periodic,
        'current' => $current,
        'expired' => $expired,
      ],
      'revisions' => $revisions,
      'states' => $states,
      'failed' => $failed,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summary(array $stats): array {
    $summary = [];
    $summary[] = $this->formatPlural(
      $stats['revisions'],
      '@count check has been performed since tracking started.',
      '@count checks have been performed since tracking started.',
    );
    $summary[] = $this->formatPlural(
      $stats['periodic']['total'],
      '@count file is set to be checked periodically.',
      '@count files are set to be checked periodically.',
    );
    $summary[] = $this->formatPlural(
      $stats['periodic']['current'],
      '@count periodic check is up to date.',
      '@count periodic checks are up to date.',
    );
    if ($stats['periodic']['expired'] > 0) {
      $summary[] = $this->formatPlural(
        $stats['periodic']['expired'],
        '@count periodic check is out to date.',
        '@count periodic checks are out to date.',
      );
    }
    if ($stats['failed'] > 0) {
      $summary[] = $this->formatPlural(
        $stats['failed'],
        '@count check has failed.',
        '@count checks have failed.',
      );
      foreach ($stats['states'] as $state => $count) {
        $summary[] = $this->formatPlural(
          $count,
          FixityCheck::getStateProperty($state, 'singular'),
          FixityCheck::getStateProperty($state, 'plural'),
        );
      }
    }
    return $summary;
  }

}
