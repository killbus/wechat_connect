<?php
namespace Drupal\wechat_connect\Oauth2;

use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\wechat_connect\Entity\WechatApplication;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * SMS grant class.
 */
class WeChatNativeAppCodeGrant extends PasswordGrant {

  /**
   * @param ServerRequestInterface $request
   * @param ClientEntityInterface $client
   *
   * @return UserEntityInterface
   * @throws OAuthServerException
   */
  protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client)
  {
    $code = $this->getRequestParameter('code', $request);
    if (is_null($code)) {
      throw OAuthServerException::invalidRequest('code');
    }

    $app_id = $this->getRequestParameter('app_id', $request);
    if (is_null($app_id)) {
      throw OAuthServerException::invalidRequest('app_id');
    }

    // 直接创建新用户，如果要绑定到已有用户，后期换绑

    // 用 code 换 AccessToken
    // 用 AccessToken 拉取用户信息

    // 创建/更新微信用户信息，返回会话

    $wechat_application = WechatApplication::load($app_id);
    if (!($wechat_application instanceof WechatApplication)) {
      throw new BadRequestHttpException('app_id is invalid.');
    }

    /** @var WechatApplicationTypeInterface $plugin */
    $plugin = \Drupal::service('plugin.manager.wechat_application_type')->createInstance($wechat_application->getType(), [
      'appId' => $wechat_application->id(),
      'appSecret' => $wechat_application->getSecret()
    ]);

    try {
      $drupal_user = $plugin->connect($code);
      $user = new UserEntity();
      $user->setIdentifier($drupal_user->id());
      return $user;
    } catch (\Exception $exception) {
      $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));
      throw OAuthServerException::invalidCredentials();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier()
  {
    return 'wechat_native_app_code';
  }
}
