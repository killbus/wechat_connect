<?php

namespace Drupal\wechat_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\wechat_connect\Entity\WechatApplication;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeInterface;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Manages requests to WeChat API.
 *
 * Most of the code here is specific to implement a WeChat login process. Social
 * Networking services might require different approaches.
 */
class WeChatAuthController extends ControllerBase {


  /**
   * @var WechatApplicationTypeManager
   */
  protected $applicationTypePluginManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * WeChatLoginController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_google network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   */
  public function __construct(WechatApplicationTypeManager $application_type_manager,
                              MessengerInterface $messenger) {
    $this->applicationTypePluginManager = $application_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
                $container->get('plugin.manager.wechat_application_type'),
                $container->get('messenger')
                );
  }

  /**
   * Redirects to WeChat Services Authentication page.
   *
   * Most of the Social Networks' API require you to redirect users to a
   * authentication page. This method is not a mandatory one, instead you must
   * adapt to the requirements of the module you are implementing.
   *
   * This method is called in 'social_auth_wechat.redirect_to_wechat' route.
   *
   * @see social_auth_wechat.routing.yml
   *
   * This method is triggered when the user loads user/login/wechat. It creates
   * an instance of the Network Plugin 'social auth wechat' and returns an
   * instance of the \Overtrue\Socialite\Providers\WeChatProvider object.
   *
   * It later sets the permissions that should be asked for, and redirects the
   * user to WeChat Accounts to allow him to grant those permissions.
   *
   * After the user grants permission, WeChat redirects him to a url specified
   * in the WeChat project settings. In this case, it should redirects to
   * 'user/login/wechat/callback', which calls the callback method.
   *
   * @return \Zend\Diactoros\Response\RedirectResponse
   *   Redirection to WeChat Accounts.
   */
  public function redirectToWeChat($app_id) {
    /** @var \Drupal\Core\Session\AccountInterface $current_user */
    $current_user = $this->currentUser();
    if ($current_user->isAnonymous()) {
      // 查找 WechatApplication
      $wechat_application = WechatApplication::load($app_id);
      if (!($wechat_application instanceof WechatApplication)) {
        throw new BadRequestHttpException('app_id is invalid.');
      }

      /** @var WechatApplicationTypeInterface $plugin */
      $plugin = $this->applicationTypePluginManager->createInstance($wechat_application->getType(), [
        'appId' => $wechat_application->id(),
        'appSecret' => $wechat_application->getSecret()
      ]);
      $destination = \Drupal::request()->get('wechat_connect_authorize_destination');
      if (empty($destination)) $destination = null;
      return $plugin->authorizeRedirect($destination);
    }
    else {
      return $this->postLogin();
    }
  }

  /**
   * Callback function to login user.
   *
   * Most of the Social Networks' API redirects to callback url. This method is
   * not a mandatory one, instead you must adapt to the requirements of the
   * module you are implementing.
   *
   * This method is called in 'social_auth_wechat.callback' route.
   *
   * @see social_auth_wechat.routing.yml
   *
   * This method is triggered when the path user/login/wechat/callback is
   * loaded. It creates an instance of the Network Plugin 'social auth wechat'.
   *
   * It later authenticates the user and creates the service to obtain data
   * about the user.
   *
   * After the user is authenticated, it checks if a user with the same email
   * has already registered. If so, it logins that user; if not, it creates
   * a new user with the information provided by the social network and logins
   * the new user.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function callback() {
    //$postReq = \Drupal::request()->request->all();
    $getQuery = \Drupal::request()->query->all();
    try {
      $state = json_decode(base64_decode($getQuery['state']));
      $wechat_application = WechatApplication::load($state->app_id);
      if (!($wechat_application instanceof WechatApplication)) {
        throw new BadRequestHttpException('state is invalid.');
      }
    } catch (\Exception $exception) {
      \Drupal::logger('wechat_connect')->error($exception->getMessage());
      throw new BadRequestHttpException('state is invalid.');
    }

    /** @var WechatApplicationTypeInterface $plugin */
    $plugin = $this->applicationTypePluginManager->createInstance($wechat_application->getType(), [
      'appId' => $wechat_application->id(),
      'appSecret' => $wechat_application->getSecret()
    ]);

    try {
      // 用 code 换 AccessToken
      // 用 AccessToken 拉取用户信息
      // 创建/更新微信用户信息
      $extend_data = json_decode($getQuery['extendData'], True) ?? [];
      $user = $plugin->connect($getQuery['code'], $extend_data);

      // 登录用户，引导到手机号绑定表单
      user_login_finalize($user);
      if (empty($state->destination)) return $this->postLogin();
      else return new \Symfony\Component\HttpFoundation\RedirectResponse($state->destination);
    } catch (\Exception $exception) {
      \Drupal::logger('wechat_connect')->error($exception->getMessage());
      $this->messenger->addError($this->t('You could not be authenticated, please contact the administrator'));
      return $this->redirect('user.login');
    }

  }

  private function postLogin() {
    $post_login = \Drupal::configFactory()->get('social_auth.settings')->get('post_login');
    if (empty($post_login)) return $this->redirect('user.login');
    else return new \Symfony\Component\HttpFoundation\RedirectResponse(Url::fromUserInput($post_login)->toString());
  }

}
