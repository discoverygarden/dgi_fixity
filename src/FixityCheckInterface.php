<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\file\Entity\File;

/**
 * Provides an interface defining a fixity_check entity.
 */
interface FixityCheckInterface extends ContentEntityInterface, RevisionableInterface {

  /**
   * The check has not been performed or is some other undefined state.
   */
  const STATE_UNDEFINED = 0;

  /**
   * The generated checksums match the recorded values.
   */
  const STATE_MATCHES = 1;

  /**
   * The generated checksums do not match the recorded values.
   */
  const STATE_MISMATCHES = 2;

  /**
   * The file is missing.
   */
  const STATE_MISSING = 3;

  /**
   * One or more checksum(s) are missing from the files recorded checksums.
   */
  const STATE_NO_CHECKSUM = 4;

  /**
   * One or more checksum(s) could not be generated.
   */
  const STATE_GENERATION_FAILED = 5;

  /**
   * Properties of each state.
   *
   * @var array
   *  An associative array with the following properties.
   *   - label: The label to use when displaying a single check.
   *   - singular: The singular label to use when aggregating checks.
   *   - plural: The plural label to use when aggregating checks.
   *   - passed: TRUE if this state indicates the check passed FALSE otherwise.
   */
  const STATES = [
    self::STATE_UNDEFINED => [
      'label' => 'Undefined',
      'singular' => '@count check is undefined',
      'plural' => '@count checks are undefined',
      'passed' => FALSE,
    ],
    self::STATE_MATCHES => [
      'label' => 'Matched recorded values',
      'singular' => '@count check match the recorded checksum(s)',
      'plural' => '@count checks matched the recorded checksum(s)',
      'passed' => TRUE,
    ],
    self::STATE_MISMATCHES => [
      'label' => 'Did not match recorded values',
      'singular' => '@count check did not match the recorded checksum(s)',
      'plural' => '@count checks did not match the recorded checksum(s)',
      'passed' => FALSE,
    ],
    self::STATE_MISSING => [
      'label' => 'Could not be performed: File missing',
      'singular' => '@count file is missing and could not be checked',
      'plural' => '@count files are missing and could not be checked',
      'passed' => FALSE,
    ],
    self::STATE_NO_CHECKSUM => [
      'label' => 'Could not be performed: Missing recorded checksum(s)',
      'singular' => '@count file is missing a recorded checksum',
      'plural' => '@count files are missing recorded checksums',
      'passed' => FALSE,
    ],
    self::STATE_GENERATION_FAILED => [
      'label' => 'Could not be performed: Could not generate checksum(s)',
      'singular' => '@count check could not generate a checksum',
      'plural' => '@count checks could not generate checksums',
      'passed' => FALSE,
    ],
  ];

  /**
   * Gets the file this check was performed against.
   *
   * @return \Drupal\file\Entity\File
   *   The file associated with this check or NULL if not set.
   */
  public function getFile(): ?File;

  /**
   * Sets the state of the check.
   *
   * @param \Drupal\file\Entity\File $file
   *   The state of the check.
   *
   * @return $this
   */
  public function setFile(File $file): FixityCheckInterface;

  /**
   * Gets the state of the check.
   *
   * @return int
   *   The state of the check.
   */
  public function getState(): int;

  /**
   * Sets the state of the check.
   *
   * @param int $state
   *   The state of the check.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If $state is not valid.
   */
  public function setState(int $state): FixityCheckInterface;

  /**
   * Gets the human readable representation of the state.
   *
   * @return string
   *   The state.
   */
  public function getStateLabel(): string;

  /**
   * Gets the given property of the given state if defined.
   *
   * @param int $state
   *   The state of whose properties are fetched.
   * @param string $property
   *   The property to get.
   *
   * @return mixed|null
   *   The property if defined otherwise NULL.
   *
   * @see \Drupal\dgi_fixity\FixityCheckInterface::STATES
   */
  public static function getStateProperty(int $state, string $property);

  /**
   * Checks if the check passed.
   *
   * @see \Drupal\dgi_fixity\FixityCheckInterface::STATES
   *
   * @return bool
   *   TRUE if the generated checksums match the recorded values, FALSE
   *   otherwise.
   */
  public function passed(): bool;

  /**
   * Gets the timestamp of when the check was performed.
   *
   * @return int
   *   The timestamp of the check. 0 indicates the check was not performed.
   */
  public function getPerformed(): int;

  /**
   * Sets the timestamp of when the check was performed.
   *
   * @param int $performed
   *   The timestamp when the check was performed.
   *
   * @return $this
   */
  public function setPerformed(int $performed): FixityCheckInterface;

  /**
   * TRUE if this check was performed, FALSE otherwise.
   *
   * @return bool
   *   TRUE if this check was performed, FALSE otherwise.
   */
  public function wasPerformed(): bool;

  /**
   * Checks if periodic checks are enabled.
   *
   * @return int
   *   TRUE if periodic checks are enabled, FALSE otherwise.
   */
  public function getPeriodic(): bool;

  /**
   * Enable or disable periodic checks.
   *
   * @param bool $periodic
   *   TRUE to enable periodic checks, FALSE otherwise.
   *
   * @return $this
   */
  public function setPeriodic(bool $periodic): FixityCheckInterface;

  /**
   * Gets the timestamp of when the check was queued.
   *
   * @return int
   *   The timestamp when the check was queued.
   *   0 indicates the check is not queued.
   */
  public function getQueued(): int;

  /**
   * Sets the timestamp of when the check was queued.
   *
   * @param int $queued
   *   The timestamp when queued.
   *
   * @return $this
   */
  public function setQueued(int $queued): FixityCheckInterface;

  /**
   * The cache tags associated with the audit display of this entity.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getAuditCacheTags();

}
