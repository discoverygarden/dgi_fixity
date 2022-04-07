<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\views\ViewExecutable;

/**
 * Interface for FixityCheckService.
 */
interface FixityCheckServiceInterface {

  /**
   * A list of entity types which be converted into a fixity_check entity.
   *
   * @return string[]
   *   A list of entity types which be converted into a fixity_check entity.
   */
  public function fromEntityTypes(): array;

  /**
   * Fetches or creates a fixity_check entity from the given media entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A media entity.
   *
   * @return \Drupal\dgi_fixity\FixityCheckInterface
   *   The fixity_check entity for the given entity if possible NULL otherwise.
   */
  public function fromEntity(EntityInterface $entity): ?FixityCheckInterface;

  /**
   * Fetches or creates a fixity_check entity from the given file entity.
   *
   * @param \Drupal\file\FileInterface|int $file
   *   A file entity or file entity identifier.
   *
   * @return \Drupal\dgi_fixity\FixityCheckInterface
   *   The fixity_check entity for the given file.
   */
  public function fromFile($file): ?FixityCheckInterface;

  /**
   * Fetches or creates a fixity_check entity from the given media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   A media entity.
   *
   * @return \Drupal\dgi_fixity\FixityCheckInterface
   *   The fixity_check entity for the given media.
   */
  public function fromMedia(MediaInterface $media): ?FixityCheckInterface;

  /**
   * Gets the threshold for determining if checks should be performed.
   *
   * @return int
   *   The timestamp for the threshold relative to the current request time.
   */
  public function threshold(): int;

  /**
   * Gets when the given check should be performed again.
   *
   * Only periodic checks can be scheduled.
   *
   * @return int|null
   *   The timestamp when the check should be performed again if scheduled to,
   *   NULL otherwise.
   */
  public function scheduled(FixityCheckInterface $check): ?int;

  /**
   * Gets the view for the given source, filtered to non-periodic files only.
   *
   * The source must comply with checks performed by this modules settings form.
   * This function does not validate it.
   *
   * @param string $source
   *   The view display identifier as selected in this modules settings form.
   * @param int $limit
   *   The maximum results the view should return.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The filtered view.
   */
  public function source(string $source, int $limit): ?ViewExecutable;

  /**
   * Generates a fixity_check entity from the given file.
   *
   * Either adds a new revision or creates a new fixity_check.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file to perform the fixity check against.
   * @param bool $force
   *   A flag to indicate if the check should be performed even if the time
   *   elapsed since the last check has not exceed the required threshold.
   *
   * @return \Drupal\dgi_fixity\Entity\FixityCheckInterface|null
   *   The resulting fixity_check if successful.
   *   NULL if the check was not performed because the time elapsed since the
   *   last check has not exceed the required threshold.
   */
  public function check(File $file, bool $force = FALSE);

  /**
   * Get an associative array of statistics relating to FixityChecks.
   *
   * @return array
   *   An associative array with the following fields:
   *   - total: The number of active fixity checks.
   *   - revisions: The total number of fixity checks ever performed.
   *   - states: An associative array of states and their active counts.
   *   - current: The number of checks that are up to date.
   *   - expired: The number of checks that are out of date.
   *   - failed: The number of checks in a failed state.
   */
  public function stats(): array;

  /**
   * Given stats provided by this service generate a summary.
   *
   * @param array $stats
   *   The stats as returned by this service.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A list of messages that describe the current state of the system.
   */
  public function summary(array $stats): array;

}
