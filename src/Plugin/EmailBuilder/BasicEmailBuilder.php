<?php

namespace Drupal\iform\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Email Builder plug-in for the iform module.
 *
 * @EmailBuilder(
 *   id = "iform_basic_email",
 *   label = @Translation("Iform emails"),
 *   sub_types = {
 *     "empty" = @Translation("Basic empty"),
 *   },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class BasicEmailBuilder extends EmailBuilderBase {


  // @todo Add the mail policy config, or at least document.

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param string $to
   *   Email to.
   * @param string $subject
   *   Email subject.
   * @param string $body
   *   Email body, supports HTML.
   * @param string $options
   *   Array of extra options for headers (key/value pairs). Currently supports
   *   * from (defaults to the site email)
   *   * fromName (defaults to the site name)
   *   * replyTo (defaults to the user's email).
   */
  public function createParams(EmailInterface $email, $to = NULL, $subject = NULL, $body = NULL, $options = NULL) {
    assert($to != NULL);
    assert($subject != NULL);
    assert($body != NULL);
    $email->setParam('to', $to);
    $email->setParam('subject', $subject);
    $email->setParam('body', $body);
    $email->setParam('options', $options ?? []);
  }

  /**
   * Builds an email to send.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   */
  public function build(EmailInterface $email) {
    $to = $email->getParam('to');
    $subject = $email->getParam('subject');
    $body = $email->getParam('body');
    $siteName = hostsite_get_config_value('site', 'name', '');
    $siteEmail = hostsite_get_config_value('site', 'mail', '');
    $options = array_merge([
      'from' => $siteEmail,
      'fromName' => $siteName,
      'replyTo' => hostsite_get_user_field('mail'),
    ], $email->getParam('options'));
    $email->setTo($to);
    $email->setSubject($subject);
    $email->setBody(['#markup' => $body]);
    $email->setFrom(new Address($options['from'], $options['fromName']));
    $email->setReplyTo($options['replyTo']);
  }

}
