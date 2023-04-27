<?php

namespace Drupal\iform\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Redirect middleware for hostsite_goto_page.
 *
 * @see https://drupal.stackexchange.com/questions/138697/what-function-method-can-i-use-to-redirect-users-to-a-different-page
 */
class Redirect implements HttpKernelInterface {
  protected $httpKernel;
  protected $redirectResponse;

  /**
   * Messenger messages that existed before the redirect.
   *
   * @var array
   */
  protected array $messages;

  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $response = $this->httpKernel->handle($request, $type, $catch);
    if (isset($this->messages)) {
      // Reset Drupal messenger messages that have been lost.
      foreach ($this->messages['status'] as $message) {
        \Drupal::messenger()->addStatus($message);
      }
      foreach ($this->messages['warning'] as $message) {
        \Drupal::messenger()->addWarning($message);
      }
      foreach ($this->messages['error'] as $message) {
        \Drupal::messenger()->addError($message);
      }
    }
    return $this->redirectResponse ?: $response;
  }

  public function setRedirectResponse(?RedirectResponse $redirectResponse) {
    // Capture the Drupal messages, as they get lost when the middleware
    // handles the request.
    $this->messages = [
      'status' => \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_STATUS),
      'warning' => \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING),
      'error' => \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR),
    ];
    $this->redirectResponse = $redirectResponse;
  }

}
