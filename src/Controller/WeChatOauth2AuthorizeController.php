<?php

namespace Drupal\wechat_connect\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\simple_oauth\Controller\Oauth2AuthorizeController;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * WeChatOauth2AuthorizeController.
 */
class WeChatOauth2AuthorizeController extends Oauth2AuthorizeController {

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function authorize(Request $request) {
    $client_uuid = $request->get('client_id');
    if (empty($client_uuid)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }
    $consumer_storage = $this->entityTypeManager()->getStorage('consumer');
    $client_drupal_entities = $consumer_storage
      ->loadByProperties([
        'uuid' => $client_uuid,
      ]);
    if (empty($client_drupal_entities)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }

    $client_drupal_entity = reset($client_drupal_entities);
    $is_third_party = $client_drupal_entity->get('third_party')->value;

    $scopes = [];
    if ($request->query->get('scope')) {
      $scopes = explode(' ', $request->query->get('scope'));
    }


    // 检查 WeChat Application id
    $app_id = $request->get('app_id');
    if (empty($app_id)) {
      return OAuthServerException::invalidCredentials()
        ->generateHttpResponse(new Response());
    }

    if ($this->currentUser()->isAnonymous()) {
      // 跳转到微信服务端授权
      $destination = Url::fromRoute('wechat_connect.authorize', [], [
        'query' => UrlHelper::parse('/?' . $request->getQueryString())['query'],
      ]);
      $url = Url::fromRoute('wechat_connect.redirect_to_wechat', ['app_id' => $app_id], [
        'query' => ['wechat_connect_authorize_destination' => $destination->toString()],
      ]);
      // Client ID and secret may be passed as Basic Auth. Copy the headers.
      return RedirectResponse::create($url->toString());
    }
    elseif (!$is_third_party || $this->isKnownClient($client_uuid, $scopes)) {
      // Login user may skip the grant step if the client is not third party or
      // known.
      if ($request->get('response_type') == 'code') {
        $grant_type = 'code';
      }
      elseif ($request->get('response_type') == 'token') {
        $grant_type = 'implicit';
      }
      else {
        $grant_type = NULL;
      }
      try {
        $server = $this->grantManager->getAuthorizationServer($grant_type, $client_drupal_entity);
        $ps7_request = $this->messageFactory->createRequest($request);
        $auth_request = $server->validateAuthorizationRequest($ps7_request);
      }
      catch (OAuthServerException $exception) {
        $this->messenger()->addError($this->t('Fatal error. Unable to get the authorization server.'));
        watchdog_exception('simple_oauth', $exception);
        return RedirectResponse::create(Url::fromRoute('<front>')->toString());
      }
      if ($auth_request) {
        $can_grant_codes = $this->currentUser()
          ->hasPermission('grant simple_oauth codes');
        return static::redirectToCallback(
          $auth_request,
          $server,
          $this->currentUser,
          $can_grant_codes
        );
      }
    } else {
      $msg = $this->t('client can not be third party.');
      $this->messenger()->addError($msg);
      watchdog_exception('wechat_connect', new \Exception($msg));
      return RedirectResponse::create(Url::fromRoute('<front>')->toString());
    }
  }

}
