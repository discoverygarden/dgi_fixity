<?php

namespace Drupal\dgi_fixity;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manipulates entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class EntityTypeInfo implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The redirect destination helper.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirect;

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect
   *   The redirect destination helper.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(TranslationInterface $string_translation, AccountInterface $current_user, RedirectDestinationInterface $redirect, FixityCheckServiceInterface $fixity) {
    $this->stringTranslation = $string_translation;
    $this->currentUser = $current_user;
    $this->redirect = $redirect;
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('current_user'),
      $container->get('redirect.destination'),
      $container->get('dgi_fixity.fixity_check'),
    );
  }

  /**
   * Gets fixity check links to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types) {
    $supported_entity_types = $this->fixity->fromEntityTypes();
    foreach ($supported_entity_types as $entity_type_id) {
      $entity_type = &$entity_types[$entity_type_id];
      $entity_type->setLinkTemplate('fixity-audit', "/fixity/$entity_type_id/{{$entity_type_id}}");
      $entity_type->setLinkTemplate('fixity-check', "/fixity/$entity_type_id/{{$entity_type_id}}/check");
      $entity_type->setFormClass('fixity-check', 'Drupal\dgi_fixity\Form\CheckForm');
    }
  }

  /**
   * Gets fixity operations on entities that support it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which to define an operation.
   *
   * @return array
   *   An array of operation definitions.
   *
   * @see hook_entity_operation()
   */
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($entity->hasLinkTemplate('fixity-audit') && $this->currentUser->hasPermission('view fixity checks')) {
      $operations['fixity-audit'] = [
        'title' => $this->t('Audit'),
        'weight' => 10,
        'url' => $entity->toUrl('fixity-audit'),
      ];
      if ($entity->hasLinkTemplate('fixity-check') && $this->currentUser->hasPermission('administer fixity checks')) {
        $operations['fixity-check'] = [
          'title' => $this->t('Check'),
          'weight' => 13,
          'url' => $entity->toUrl('fixity-check', ['query' => $this->redirect->getAsArray()]),
        ];
      }
    }
    return $operations;
  }

}
