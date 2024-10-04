<?php

namespace Drupal\ffw_dashboard_auth\Controller;

use \Drupal\user\Controller\UserAuthenticationController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller handler authentication for Poc dashboard.
 */
class PocAuthenticationController extends UserAuthenticationController {

  /**
   * Handle reset password.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function sendEmailResetPassword(Request $request) {
    $format = $this->getRequestFormat($request);

    $content = $request->getContent();
    $credentials = $this->serializer->decode($content, $format);

    // Check if a name or mail is provided.
    if (!isset($credentials['name']) && !isset($credentials['mail'])) {
      throw new BadRequestHttpException('Missing credentials.name or credentials.mail');
    }

    // Load by name if provided.
    if (isset($credentials['name'])) {
      $users = $this->userStorage->loadByProperties(['name' => trim($credentials['name'])]);
    }
    elseif (isset($credentials['mail'])) {
      $users = $this->userStorage->loadByProperties(['mail' => trim($credentials['mail'])]);
    }

    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = reset($users);
    if ($account && $account->id()) {
      if ($this->userIsBlocked($account->getAccountName())) {
        throw new BadRequestHttpException('The user has not been activated or is blocked.');
      }

      $site_mail = \Drupal::config('system.site')->get('mail_notification');
      // If the custom site notification email has not been set, we use the site
      // default for this.
      if (empty($site_mail)) {
        $site_mail = \Drupal::config('system.site')->get('mail');
      }
      if (empty($site_mail)) {
        $site_mail = ini_get('sendmail_from');
      }

      $params['account'] = $account;

      $mail = \Drupal::service('plugin.manager.mail')
        ->mail('ffw_dashboard_auth', 'api_password_reset', $account->getEmail(), $account->getPreferredLangcode(), $params, $site_mail);

      if (empty($mail)) {
        throw new BadRequestHttpException('Unable to send email. Contact the site administrator if the problem persists.');
      }
      else {
        $this->logger->notice('Password reset instructions mailed to %name at %email.', [
          '%name' => $account->getAccountName(),
          '%email' => $account->getEmail(),
        ]);
        return new Response();
      }
    }

    // Error if no users found with provided name or mail.
    throw new BadRequestHttpException('Unrecognized username or email address.');
  }
}
