<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for fixity_check entity storage classes.
 */
interface FixityCheckStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the number of files that have no related fixity_check entity.
   *
   * @return int
   *   The number of files that have no related fixity_check entity.
   */
  public function countMissing(): int;

  /**
   * Gets a list of files which have no related fixity_check entity.
   *
   * @param int $offset
   *   The offset into the list of files.
   * @param int $limit
   *   The maximum number of files to return.
   *
   * @return \Drupal\file\FileInterface[]
   *   The files selected by the given parameters.
   */
  public function getMissing(int $offset, int $limit): array;

  /**
   * Gets the number of files that have enabled periodic checking.
   *
   * @return int
   *   The number of files that have enabled periodic checking.
   */
  public function countPeriodic(): int;

  /**
   * Gets a list of files which have enabled periodic checking.
   *
   * @param int $offset
   *   The offset into the list files.
   * @param int $limit
   *   The maximum number of files to return.
   *
   * @return \Drupal\file\FileInterface[]
   *   The file selected by the given parameters.
   */
  public function getPeriodic(int $offset, int $limit): array;

  /**
   * Sets the periodic check flag on all files to FALSE.
   */
  public function clearPeriodic();

  /**
   * Queues checks to be performed during cron up to at most the given limit.
   *
   * @param int $queued
   *   The timestamp newly queued items will be recorded under.
   * @param int $threshold
   *   Only queue checks which were performed before the given threshold.
   * @param int $limit
   *   The number of items to queue at most.
   */
  public function queue(int $queued, int $threshold, int $limit);

  /**
   * Dequeues checks which have not been performed before the given timestamp.
   *
   * @param int $queued
   *   Items which were queued before this timestamp will be dequeued.
   */
  public function dequeue(int $queued);

}
