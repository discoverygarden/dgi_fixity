<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * File storage for files.
 */
class FixityCheckStorage extends SqlContentEntityStorage implements FixityCheckStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function countMissing(): int {
    /** @var \Drupal\file\FileStorage $storage */
    $storage = $this->entityTypeManager->getStorage('file');
    $query = $this->database->select($storage->getBaseTable(), 'file_managed');
    $query->leftJoin($this->baseTable, 'fixity_check', '[file_managed].[fid] = [fixity_check].[file]');
    return $query
      ->isNull('fixity_check.file')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getMissing(int $offset, int $limit): array {
    /** @var \Drupal\file\FileStorage $storage */
    $storage = $this->entityTypeManager->getStorage('file');
    $query = $this->database->select($storage->getBaseTable(), 'file_managed');
    $query->fields('file_managed', ['fid']);
    $query->leftJoin($this->baseTable, 'fixity_check', '[file_managed].[fid] = [fixity_check].[file]');
    $ids = $query
      ->isNull('fixity_check.file')
      ->orderBy('file_managed.fid')
      ->range($offset, $limit)
      ->execute()
      ->fetchCol();
    return $storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function clearPeriodic() {
    $this->database
      ->update($this->baseTable)
      ->fields([
        'periodic' => 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function countPeriodic(): int {
    return $this->database
      ->select($this->baseTable, 'c')
      ->condition('c.periodic', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getPeriodic(int $offset, int $limit): array {
    /** @var \Drupal\file\FileStorage $storage */
    $storage = $this->entityTypeManager->getStorage('file');
    $ids = $this->database
      ->select($this->baseTable, 'c')
      ->condition('c.periodic', 1)
      ->fields('c', ['file'])
      ->range($offset, $limit)
      ->orderBy('id')
      ->execute()
      ->fetchCol();
    return $storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function queue(int $queued, int $threshold, int $limit) {
    // Do not over-saturate the queue.
    // 10x the limit is the max we allow to be queued at a time.
    $queue = \Drupal::queue('dgi_fixity.fixity_check');
    if ($queue->numberOfItems() > (10 * $limit)) {
      return;
    }

    $query = $this->database->select($this->baseTable, 'c');
    // Either never performed or out of date.
    $performed = $query->orConditionGroup()
      ->condition('c.performed', 0, '=')
      ->condition('c.performed', $threshold, '<=');

    // Only those which have enabled periodic checking, and are not already
    // queued.
    $ids = $query
      ->fields('c', ['id'])
      ->condition('c.periodic', 1)
      ->condition('c.queued', 0)
      ->condition($performed)
      ->range(0, $limit)
      ->execute()
      ->fetchCol();

    /** @var \Drupal\dgi_fixity\FixityCheckInterface $check */
    foreach ($this->doLoadMultiple($ids) as $check) {
      // Queue checks for processing.
      if ($queue->createItem($check)) {
        // Add timestamp to avoid queueing item more than once.
        $check->setQueued($queued);
        $check->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dequeue(int $queued) {
    $this->database
      ->update($this->baseTable)
      ->fields([
        'queued' => 0,
      ])
      ->condition('queued', 0, '<>')
      ->condition('queued', $queued, '<')
      ->execute();
  }

}
