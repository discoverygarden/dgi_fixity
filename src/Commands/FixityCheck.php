<?php

namespace Drupal\dgi_fixity\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dgi_fixity\FixityCheckBatchCheck;
use Drupal\dgi_fixity\FixityCheckBatchGenerate;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

/**
 * Drush command to perform fixity checks.
 */
class FixityCheck extends DrushCommands {

  use StringTranslationTrait;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates the drush command object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(TranslationInterface $string_translation, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->stringTranslation = $string_translation;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Sets the periodic check flag to FALSE for all files.
   *
   * @command dgi_fixity:clear
   */
  public function clear() {
    /** @var \Drupal\dgi_fixity\FixityCheckStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('fixity_check');
    $count = $storage->countPeriodic();
    if ($this->io()->confirm("This will remove periodic checks on ${count} files, are you sure?", FALSE)) {
      $storage->clearPeriodic();
    }
  }

  /**
   * Creates a fixity_check entity for all previously created files.
   *
   * @command dgi_fixity:generate
   */
  public function generate() {
    $batch = FixityCheckBatchGenerate::build();
    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Perform fixity checks on files.
   *
   * @option fids   Comma separated list of file identifiers, or a path to a
   *                file containing file identifiers. The file should have each
   *                fid separated by a new line. If not specified the modules
   *                settings for sources is used to determine which files to
   *                check.
   * @option force  Skip time elapsed threshold check when processing files.
   *
   * @command dgi_fixity:check
   */
  public function check(array $options = [
    'fids' => NULL,
    'force' => FALSE,
  ]) {
    $fids = $options['fids'];
    if (!is_null($fids)) {
      // If a file path is provided, parse it.
      if (is_file($fids)) {
        if (is_readable($fids)) {
          $fids = explode("\n", trim(file_get_contents($fids)));
        }
        else {
          $this->logger->error($this->t('Cannot read file @file', ['@file' => $fids]));
          return;
        }
      }
      else {
        $fids = explode(',', $fids);
      }
    }
    $batch = FixityCheckBatchCheck::build($fids, $options['force']);
    batch_set($batch);
    drush_backend_batch_process();
  }

}
