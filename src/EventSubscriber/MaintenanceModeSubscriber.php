<?php

namespace Drupal\iform\EventSubscriber;

use Drupal\Core\Site\MaintenanceModeEvents;
use Drupal\Core\Site\MaintenanceModeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Subscribe to maintenance mode requests.
 *
 * Intercept JSON requests in maintenance mode and ensure that a JSON message
 * response is returned, rather than plaintext as in default Drupal behaviour.
 */
class MaintenanceModeSubscriber implements EventSubscriberInterface {

  /**
   * Maintenance mode service.
   *
   * @var \Drupal\Core\Site\MaintenanceModeInterface
   */
  protected $maintenanceMode;

  /**
   * Constructor persists the maintenance mode service.
   *
   * @param \Drupal\Core\Site\MaintenanceModeInterface $maintenance_mode
   *   The maintenance mode service.
   */
  public function __construct(MaintenanceModeInterface $maintenance_mode) {
    $this->maintenanceMode = $maintenance_mode;
  }

  /**
   * Handle requests in maintenance mode.
   *
   * JSON requests to the API receive a JSON 503 message response. Other
   * requests are deferred to the normal Drupal maintenance mode code.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   A RequestEvent instance.
   */
  public function onMaintenanceModeRequest(RequestEvent $event) {
    $request = $event->getRequest();

    $expects_json = $request->query->get('_format') === 'json'
      || $request->attributes->get('_format') === 'json';

    if (!$expects_json) {
      foreach ($request->getAcceptableContentTypes() as $mime_type) {
        if ($mime_type === 'application/json' || str_ends_with($mime_type, '+json')) {
          $expects_json = TRUE;
          break;
        }
      }
    }
    if (!$expects_json) {
      return;
    }

    $response = new JsonResponse([
      'message' => (string) $this->maintenanceMode->getSiteMaintenanceMessage(),
    ], 503);

    $event->setResponse($response);
  }

  /**
   * Define that event subscriber wants to listen to maintenance mode requests.
   *
   * @return array<int|string>[][]
   *   Events and their corresponding methods to call.
   */
  public static function getSubscribedEvents(): array {
    $events[MaintenanceModeEvents::MAINTENANCE_MODE_REQUEST][] = ['onMaintenanceModeRequest', -750];
    return $events;
  }

}
