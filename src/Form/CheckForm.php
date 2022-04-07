<?php

namespace Drupal\dgi_fixity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dgi_fixity\FixityCheckInterface;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform a fixity check on the given fixity_check or related entity.
 *
 * @internal
 */
class CheckForm extends ContentEntityConfirmFormBase {

  /**
   * The fixity service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * The entity used to derive the fixity_check entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $sourceEntity;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, FixityCheckServiceInterface $fixity) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('dgi_fixity.fixity_check')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    // Support checking entity types from which we can determine the associated
    // fixity_check.
    $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    // Allow the original to affect the redirect url. For example return to the
    // media's audit page rather than the fixity_check's audit page.
    $this->sourceEntity = $entity;
    return ($entity instanceof FixityCheckInterface) ?
      $entity :
      $this->fixity->fromEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /** @var \Drupal\dgi_fixity\FixityCheckInterface $entity */
    $entity = $this->getEntity();
    if ($entity->wasPerformed()) {
      $scheduled = $this->fixity->scheduled($entity);
      if ($scheduled) {
        return $this->t('
          <strong>Latest Result:</strong> %state<br/>
          <strong>Last Performed:</strong> %performed<br/>
          <strong>Next Scheduled:</strong> %scheduled
        ', [
          '%state' => $entity->getStateLabel(),
          '%performed' => date(DATE_RFC7231, $entity->getPerformed()),
          '%scheduled' => date(DATE_RFC7231, $scheduled),
        ]);
      }
      return $this->t('
        <strong>Latest Result:</strong> %state<br/>
        <strong>Last Performed:</strong> %performed
      ', [
        '%state' => $entity->getStateLabel(),
        '%performed' => date(DATE_RFC7231, $entity->getPerformed()),
        '%scheduled' => date(DATE_RFC7231, $scheduled),
      ]);
    }
    return $this->t('No prior check has been performed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /** @var \Drupal\dgi_fixity\FixityCheckInterface $entity */
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to perform a check on %label?', [
      '%label' => $this->getEntity()->label(),
      '%performed' => date(DATE_RFC7231, $entity->getPerformed()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->sourceEntity->toUrl('fixity-audit');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\dgi_fixity\FixityCheckInterface $entity */
    $entity = $this->getEntity();
    /** @var \Drupal\dgi_fixity\FixityCheckInterface $check */
    $check = $this->fixity->check($entity->getFile(), TRUE);
    $this->setEntity($check);
    unset($entity);

    $message = $this->t('The @entity-type %label: %state.', [
      '@entity-type' => $check->getEntityType()->getSingularLabel(),
      '%label' => $check->toLink()->toString(),
      '%state' => $check->getStateLabel(),
    ]);

    if ($check->passed()) {
      $this->messenger()->addStatus($message);
    }
    else {
      $this->messenger()->addError($message);
    }

    // If no destination set return to the fixity check audit page.
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
