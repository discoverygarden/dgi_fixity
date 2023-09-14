<?php

namespace Drupal\dgi_fixity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\dgi_fixity\FixityCheckInterface;
use Drupal\dgi_fixity\FixityCheckServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for fixity_check tasks.
 */
class FixityCheckController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The fixity check service.
   *
   * @var \Drupal\dgi_fixity\FixityCheckServiceInterface
   */
  protected $fixity;

  /**
   * Constructs a controller for displaying fixity_check related tasks.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\dgi_fixity\FixityCheckServiceInterface $fixity
   *   The fixity service.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, FixityCheckServiceInterface $fixity) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->fixity = $fixity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('dgi_fixity.fixity_check'),
    );
  }

  /**
   * Returns the audit display for the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function entityAudit(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->audit($entity);
  }

  /**
   * Generates an overview table of all revisions of a given fixity_check.
   *
   * @param \Drupal\dgi_fixity\FixityCheckInterface $fixity_check
   *   A fixity_check entity.
   *
   * @return array
   *   An array expected by \Drupal\Core\Render\RendererInterface::render().
   */
  public function audit(FixityCheckInterface $fixity_check) {
    $account = $this->currentUser();
    $storage = $this->entityTypeManager()->getStorage('fixity_check');

    $build['#title'] = $this->t('Audit for %title', [
      '%title' => $fixity_check->label(),
    ]);

    $header = [
      $this->t('Performed'),
      $this->t('State'),
      $this->t('Operations'),
    ];

    $canDelete = $account->hasPermission('administer fixity checks') && $fixity_check->access('delete');

    $rows = [];
    $defaultRevision = $fixity_check->getRevisionId();
    $currentRevisionDisplayed = FALSE;

    foreach ($this->getRevisionIds($fixity_check, $storage) as $revision_id) {
      /** @var \Drupal\dgi_fixity\FixityCheckInterface $revision */
      $revision = $storage->loadRevision($revision_id);

      // Use timestamp rather than timestamp_ago to allow for caching.
      $date = ($revision->wasPerformed()) ?
       $revision->performed->view([
         'label' => 'hidden',
         'type' => 'timestamp',
       ]) :
       $this->t('never');

      $isCurrentRevision = $revision_id == $defaultRevision || (!$currentRevisionDisplayed && $revision->wasDefaultRevision());
      if (!$isCurrentRevision) {
        $link = Link::fromTextAndUrl($date, new Url(
          'entity.fixity_check.revision',
          [
            'fixity_check' => $fixity_check->id(),
            'fixity_check_revision' => $revision_id,
          ]
        ));
      }
      else {
        $link = $fixity_check->toLink($date);
        $currentRevisionDisplayed = TRUE;
      }

      $state = $revision->state->view([
        'label' => 'hidden',
        'type' => 'dgi_fixity_state',
      ]);

      $row = [
        [
          'data' => $link->toRenderable(),
        ],
        [
          'data' => $state,
        ],
      ];

      if ($isCurrentRevision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];

        $rows[] = [
          'data' => $row,
          'class' => ['revision-current'],
        ];
      }
      else {
        $links = [];

        if ($canDelete) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute(
              'entity.fixity_check.revision_delete_confirm',
              [
                'fixity_check' => $fixity_check->id(),
                'fixity_check_revision' => $revision_id,
              ]
            ),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];

        $rows[] = $row;
      }
    }

    $build['fixity_check_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#attributes' => [
        'class' => 'fixity_check-revision-table',
      ],
    ];
    $build['pager'] = [
      '#type' => 'pager',
    ];

    $build['#cache'] = [
      'keys' => [
        'entity_view', 'fixity_check', $fixity_check->id(), 'revisions',
      ],
      'contexts' => [
        // Date displayed varies by timezone.
        'timezone',
      ],
      'tags' => $fixity_check->getAuditCacheTags(),
      'bin' => 'render',
    ];
    $this->renderer->addCacheableDependency($build, $fixity_check);
    return $build;
  }

  /**
   * Gets a list of fixity_check revision IDs for a given fixity_check.
   *
   * @param \Drupal\dgi_fixity\FixityCheckInterface $fixity_check
   *   Media entity to search for revisions.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Media storage to load revisions from.
   *
   * @return int[]
   *   fixity_check revision IDs in descending order.
   */
  protected function getRevisionIds(FixityCheckInterface $fixity_check, EntityStorageInterface $storage) {
    $result = $storage->getQuery()
      ->allRevisions()
      ->condition('id', $fixity_check->id())
      ->sort('performed', 'DESC')
      ->pager(50)
      ->accessCheck()
      ->execute();
    return array_keys($result);
  }

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\dgi_fixity\FixityCheckInterface|null
   *   The fixity check entity from the passed-in route match.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match): ?FixityCheckInterface {
    // Option added by Route Subscriber.
    $parameter_name = $route_match->getRouteObject()->getOption('_fixity_entity_type_id');
    return ($parameter_name == 'fixity_check') ?
       $route_match->getParameter($parameter_name) :
       $this->fixity->fromEntity($route_match->getParameter($parameter_name));
  }

}
