<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\dgi_fixity\Form\SettingsForm;

/**
 * Generates a fixity_check for all previously created files.
 */
class FixityCheckBatchGenerate {

  /**
   * Creates a batch for this service.
   *
   * @param int $batch_size
   *   The number of of files to process at a time.
   *   If not specified it will default to the modules configuration.
   */
  public static function build($batch_size = NULL) {
    if (is_null($batch_size)) {
      $batch_size = \Drupal::config(SettingsForm::CONFIG_NAME)->get(SettingsForm::BATCH_SIZE);
    }
    $builder = new BatchBuilder();
    return $builder
      ->setTitle(\t('Generating Fixity Checks for previously created files'))
      ->setInitMessage(\t('Starting'))
      ->setErrorMessage(\t('Batch has encountered an error'))
      ->addOperation([static::class, 'generate'], [$batch_size])
      ->setFinishCallback([static::class, 'finished'])
      ->toArray();
  }

  /**
   * Generates fixity_check entity for previously created files.
   *
   * @param int $batch_size
   *   The number of of files to process at a time.
   * @param array|object $context
   *   Context for operations.
   */
  public static function generate($batch_size, &$context) {
    /** @var \Drupal\dgi_fixity\FixityCheckStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('fixity_check');

    $sandbox = &$context['sandbox'];
    $results = &$context['results'];
    if (!isset($results['successful'])) {
      $results['successful'] = 0;
      $results['failed'] = 0;
      $results['errors'] = [];
      $sandbox['remaining'] = $storage->countMissing();
    }

    $files = $storage->getMissing(0, $batch_size);
    foreach ($files as $file) {
      $check = $storage->create(['file' => $file->id()]);
      try {
        $check->save();
        $results['successful']++;
      }
      catch (\Exception $e) {
        $results['failed']++;
        $results['errors'][] = \t('Encountered an exception: @exception', [
          '@exception' => $e,
        ]);
      }
    }

    $remaining = $storage->countMissing();
    $progress_halted = $sandbox['remaining'] == $remaining;
    $sandbox['remaining'] = $remaining;

    // End when we have exhausted all inputs or progress has halted.
    $context['finished'] = empty($files) || $progress_halted;
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function finished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    $messenger->addStatus(new PluralTranslatableMarkup(
      $results['successful'] + $results['failed'],
      'Processed @count item in total.',
      'Processed @count items in total.'
    ));
    $messenger->addStatus(new PluralTranslatableMarkup(
      $results['successful'],
      '@count was successful.',
      '@count were successful.',
    ));
    $messenger->addStatus(\t(
      '@count failed.', ['@count' => $results['failed']]
    ));
    $error_count = count($results['errors']);
    if ($error_count > 0) {
      $messenger->addMessage(new PluralTranslatableMarkup(
        $error_count,
        '@count error occurred.',
        '@count errors occurred.',
      ));
      foreach ($results['errors'] as $error) {
        $messenger->addError($error);
      }
    }
  }

}
