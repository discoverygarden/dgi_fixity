<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\dgi_fixity\Form\SettingsForm;

/**
 * Performs fixity checks.
 */
class FixityCheckBatchCheck {

  /**
   * Creates a batch for performing fixity checks.
   *
   * @param int[] $fids
   *   A list of file identifiers, if not specified files with periodic checks
   *   enabled will be selected.
   * @param bool $force
   *   A flag to indicate if the check should be performed even if the time
   *   elapsed since the last check has not exceed the required threshold.
   * @param int $batch_size
   *   The number of of files to process at a time.
   *   If not specified it will default to the modules configuration.
   */
  public static function build(array $fids = NULL, bool $force = FALSE, int $batch_size = NULL) {
    $batch_size = is_null($batch_size) ? \Drupal::config(SettingsForm::CONFIG_NAME)->get(SettingsForm::BATCH_SIZE) : $batch_size;
    return is_null($fids) ?
      static::buildPeriodic($force, $batch_size) :
      static::buildFixed($fids, $force, $batch_size);
  }

  /**
   * Creates a batch for processing a fixed list of file identifiers.
   */
  protected static function buildFixed(array $fids, bool $force, int $batch_size) {
    $builder = new BatchBuilder();
    return $builder
      ->setTitle(\t('Performing checks on @count file(s)', ['@count' => count($fids)]))
      ->setInitMessage(\t('Starting'))
      ->setErrorMessage(\t('Batch has encountered an error'))
      ->addOperation([static::class, 'processFixedList'], [
        $fids,
        $force,
        $batch_size,
      ])
      ->setFinishCallback([static::class, 'finished'])
      ->toArray();
  }

  /**
   * Creates a batch for processing files that have periodic checks enabled.
   */
  public static function buildPeriodic(bool $force, int $batch_size) {
    $sources = \Drupal::config(SettingsForm::CONFIG_NAME)->get(SettingsForm::SOURCES);
    if (empty($sources)) {
      throw new \InvalidArgumentException("No sources specified, check the modules configuration.");
    }
    $builder = new BatchBuilder();
    foreach ($sources as $source) {
      $builder->addOperation(
        [static::class, 'processSource'],
        [$source, $batch_size],
      );
    }
    return $builder
      ->setTitle(\t('Enumerating periodic checks from @count Source(s)', ['@count' => count($sources)]))
      ->setInitMessage(\t('Starting'))
      ->setErrorMessage(\t('Batch has encountered an error'))
      ->addOperation([static::class, 'processPeriodic'], [$force, $batch_size])
      ->setFinishCallback([static::class, 'finished'])
      ->toArray();
  }

  /**
   * Check the given files.
   *
   * @param int[] $fids
   *   A list of file identifiers.
   * @param bool $force
   *   A flag to indicate if the check should be performed even if the time
   *   elapsed since the last check has not exceed the required threshold.
   * @param int $batch_size
   *   The amount of files each time this process runs.
   * @param array|object $context
   *   Context for operations.
   */
  public static function processFixedList(array $fids, bool $force, int $batch_size, &$context) {
    $sandbox = &$context['sandbox'];
    $results = &$context['results'];
    if (!isset($sandbox['total'])) {
      $sandbox['offset'] = 0;
      $sandbox['total'] = count($fids);
      $results['successful'] = 0;
      $results['ignored'] = 0;
      $results['skipped'] = 0;
      $results['failed'] = 0;
      $results['errors'] = [];
    }
    $chunk = array_slice($fids, $sandbox['offset'], $batch_size);
    $end = min($sandbox['total'], $sandbox['offset'] + count($chunk));
    $context['message'] = \t('Processing @start to @end of @total', [
      '@start' => $sandbox['offset'],
      '@end' => $end,
      '@total' => $sandbox['total'],
    ]);
    $files = \Drupal::service('entity_type.manager')->getStorage('file')->loadMultiple($chunk);
    // It is possible for non existing fids to be listed in $chunk, in such
    // cases this is ignored.
    $results['ignored'] += count(array_diff($chunk, array_keys($files)));
    static::check($files, $force, $results);
    $sandbox['offset'] = $end;
    $context['finished'] = $sandbox['offset'] / $sandbox['total'];
  }

  /**
   * Enable periodic checks on files as returned by the give source view.
   */
  public static function processSource(string $source, $batch_size, &$context) {
    $results = &$context['results'];
    // Do not track success/failure on processing source, as that is done for
    // checks only. Errors however do get passed though.
    if (!isset($results['errors'])) {
      $results['errors'] = [];
    }

    /** @var \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity */
    $fixity = \Drupal::service('dgi_fixity.fixity_check');
    $view = $fixity->source($source, $batch_size);
    $view->execute();
    // Only processes those which have not already enabled periodic checks.
    foreach ($view->result as $row) {
      try {
        /** @var \Drupal\dgi_fixity\FixityCheckInterface $check */
        $check = $view->field['periodic']->getEntity($row);
        $check->setPeriodic(TRUE);
        $check->save();
      }
      catch (\Exception $e) {
        $results['errors'][] = \t('Encountered an exception: @exception', [
          '@exception' => $e,
        ]);
        // In practice exceptions in this case shouldn't arise, but if they do
        // exit to prevent an infinite loop by exiting the operation.
        $context['finished'] = 1;
        return;
      }
    }
    // End when we have exhausted all inputs.
    $context['finished'] = count($view->result) == 0;
  }

  /**
   * Checks all files which have enabled periodic fixity checks.
   *
   * @param bool $force
   *   A flag to indicate if the check should be performed even if the time
   *   elapsed since the last check has not exceed the required threshold.
   * @param int $batch_size
   *   The amount of files each time this process runs.
   * @param array|object $context
   *   Context for operations.
   */
  public static function processPeriodic(bool $force, int $batch_size, &$context) {
    /** @var \Drupal\dgi_fixity\FixityCheckStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('fixity_check');

    $sandbox = &$context['sandbox'];
    $results = &$context['results'];
    if (!isset($sandbox['offset'])) {
      $sandbox['offset'] = 0;
      $sandbox['remaining'] = $storage->countPeriodic();
      $results['successful'] = 0;
      $results['ignored'] = 0;
      $results['skipped'] = 0;
      $results['failed'] = 0;
      $results['errors'] = $results['errors'] ?? [];
    }

    $files = $storage->getPeriodic($sandbox['offset'], $batch_size);
    $end = min($sandbox['remaining'], $sandbox['offset'] + count($files));
    $context['message'] = \t('Processing @start to @end', [
      '@start' => $sandbox['offset'],
      '@end' => $end,
    ]);
    static::check($files, $force, $results);
    $sandbox['offset'] = $end;

    $remaining = $storage->countPeriodic();
    $progress_halted = $sandbox['remaining'] == $remaining;
    $sandbox['remaining'] = $remaining;

    // End when we have exhausted all inputs or progress has halted.
    $context['finished'] = empty($files) || $progress_halted;
  }

  /**
   * Performs a fixity check on the given list of files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   A list of file identifiers.
   * @param bool $force
   *   A flag to indicate if the check should be performed even if the time
   *   elapsed since the last check has not exceed the required threshold.
   * @param array &$results
   *   An associative array with the results of the fixity checks
   *   - missing: The number of file identifiers for which no files exist.
   *   - successful: The number of files that were successfully checked.
   *   - errors: A list of error messages if any occurred.
   */
  protected static function check(array $files, bool $force, array &$results) {
    /** @var \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity */
    $fixity = \Drupal::service('dgi_fixity.fixity_check');
    foreach ($files as $file) {
      try {
        $result = $fixity->check($file, $force);
        if ($result instanceof FixityCheckInterface) {
          if ($result->passed()) {
            $results['successful']++;
          }
          else {
            $results['failed']++;
          }
        }
        else {
          // The check was not performed as the time elapsed since the last
          // check did not exceed the required threshold.
          $results['skipped']++;
        }
      }
      catch (\Exception $e) {
        $results['failed']++;
        $results['errors'][] = \t('Encountered an exception: @exception', [
          '@exception' => $e,
        ]);
      }
    }
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
      $results['successful'] + $results['ignored'] + $results['skipped'] + $results['failed'],
      'Processed @count item in total.',
      'Processed @count items in total.'
    ));
    $messenger->addStatus(new PluralTranslatableMarkup(
      $results['successful'],
      '@count was successful.',
      '@count were successful.',
    ));
    $messenger->addStatus(new PluralTranslatableMarkup(
      $results['ignored'],
      '@count was ignored.',
      '@count were ignored.',
    ));
    $messenger->addStatus(new PluralTranslatableMarkup(
      $results['skipped'],
      '@count was skipped.',
      '@count were skipped.',
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
