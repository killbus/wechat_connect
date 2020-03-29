<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth_code\AuthorizationCodeGeneratorInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\wechat_connect\Entity\WechatUser;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use function GuzzleHttp\Psr7\str;

/**
 * Base class for Wechat application type plugins.
 */
abstract class WechatApplicationTypeBase extends PluginBase implements WechatApplicationTypeInterface {

  const MEDIA_PLATFORM_AUTHORIZE_ENDPOINT = 'https://open.weixin.qq.com/connect/oauth2/authorize';
  const WEBSITE_AUTHORIZE_ENDPOINT = 'https://open.weixin.qq.com/connect/qrconnect';
  const ACCESS_TOKEN_ENDPOINT = 'https://api.weixin.qq.com/sns/oauth2/access_token';
  const REFRESH_TOKEN_ENDPOINT = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';
  const USER_INFO_ENDPOINT = 'https://api.weixin.qq.com/sns/userinfo';

  public function authorizeRedirect($destination = null) {}

  public function getAuthorizeRedirect($endpoint, $destination) {
    $app_id = $this->configuration['appId'];
    $callback = \Drupal::request()->getSchemeAndHttpHost().'/'.Url::fromRoute('wechat_connect.callback')->getInternalPath();
    $scope = self::WEBSITE_AUTHORIZE_ENDPOINT === $endpoint ? 'snsapi_login' : '';
    $scope = self::MEDIA_PLATFORM_AUTHORIZE_ENDPOINT === $endpoint ? 'snsapi_userinfo' : $scope;

    $state = new \stdClass();
    $state->app_id = $app_id;
    if (!empty($destination)) {
      if ($destination instanceof Url) $state->destination = $destination->toString();
      else $state->destination = (string)$destination;
    }
    $state = base64_encode(json_encode($state));

    $query = "?appid=$app_id&redirect_uri=$callback&response_type=code&scope=$scope&state=$state#wechat_redirect";
    return TrustedRedirectResponse::create($endpoint . $query);
  }

  /**
   * @param $code
   * @param array $extend_data
   * @return UserInterface
   * @throws EntityStorageException
   */
  public function connect($code, $extend_data = []) {

    // 获取 access_token
    $open_id = null;
    $access_token = null;
    try {
      $access_token = $this->getAccessToken($code);
      if (isset($access_token->openid)) $open_id = $access_token->openid;
    } catch (Exception $e) {
      throw $e;
    }

    // 获取用户信息
    $user_info = [];
    $union_id = null;
    try {
      $user_info_object = $this->receiveUserInfo($access_token->openid, $access_token->access_token);
      if ($user_info_object->nickname) $user_info['nickname'] = $user_info_object->nickname;
      if ($user_info_object->sex) $user_info['sex'] = $user_info_object->sex;
      if ($user_info_object->headimgurl) $user_info['avatar'] = $user_info_object->headimgurl;

      if (isset($user_info_object->unionid)) $union_id = $user_info_object->unionid;
    } catch (Exception $e) {
      \Drupal::logger('wechat_connect')->notice($e->getMessage());
    }

    $wechat_user = $this->makeConnect($open_id, $union_id, $access_token);
    if ($wechat_user->getOwner()) return $wechat_user->getOwner();
    else return $this->register($wechat_user, $user_info);
  }


  protected function makeConnect($open_id, $union_id = null, $access_token = null, $session_key = null) {
    $wechat_user = $this->hadConnected($open_id);
    if ($wechat_user instanceof WechatUser) {
      // 连接过，更新wechat_user
      $wechat_user->setToken(json_encode($access_token));
      if ($session_key) $wechat_user->setSessionKey($session_key);
    } else {
      // 还没有连接过，创建wechat_user
      $user_data = [
        'app_id' => $this->configuration['appId'],
        'open_id' => $open_id
      ];
      if ($access_token) $user_data['token'] = json_encode($access_token);
      if ($session_key) $user_data['session_key'] = $session_key;
      $wechat_user = WechatUser::create($user_data);
    }

    // 如果有 union_id，保存它
    if ($union_id) $wechat_user->setUnionId($union_id);

    // 如果有 union_id，并且还没创建 Drupal 用户，常试在其他微信应用中查找已关联 Drupal 用户并关联
    if (!$wechat_user->getOwnerId() && !empty($union_id)) {
      $else_app_wechat_user = $this->hadConnectedByElseWechatApps($union_id);
      if ($else_app_wechat_user instanceof WechatUser) {
        if ($else_app_wechat_user->getOwnerId()) {
          $wechat_user->setOwnerId($else_app_wechat_user->getOwnerId());
        }
      }
    }

    $wechat_user->save();
    return $wechat_user;
  }

  /**
   * @param $wechat_user
   * @param $user_info
   * @param null $phone
   * @return UserInterface
   * @throws EntityStorageException
   */
  public function register($wechat_user, $user_info, $phone = null) {

    // 如果还没有关联 Drupal 账号，创建一个新的 Drupal 账号
    if (!($wechat_user->getOwner() instanceof AccountInterface)) {

      /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
      $util = \Drupal::service('mobile_number.util');
      $phone = $util->getMobileNumber($phone['countryCode'].$phone['purePhoneNumber']);

      // 如果提供了手机号，尝试查找已有 Drupal 账号
      if (!empty($phone)) {
        $drupal_user = $this->getDrupalUserByPhone($util->getCallableNumber($phone));
        if ($drupal_user) {
          $wechat_user->setOwnerId($drupal_user->id());
          $wechat_user->save();
          return $drupal_user;
        }
      }

      $username = !empty($user_info) ? $user_info['nickname'] : '微信用户';
      $drupal_user = $this->createUser($username, null);

      $need_save = false;
      if (!empty($user_info)) {
        if (isset($user_info['nickname']) && $drupal_user->hasField('nick_name')) {
          $drupal_user->set('nick_name', $user_info['nickname']);
          $need_save = true;
        }
        if (isset($user_info['sex']) && $drupal_user->hasField('sex')) {
          $drupal_user->set('sex', $user_info['sex'] === 1 ? 'male' : 'female');
          $need_save = true;
        }
        if (isset($user_info['avatar']) && $drupal_user->hasField('user_picture')) {
          // Determine image type
          $fileData = file_get_contents($user_info['avatar']);
          $f = finfo_open();
          $mimeType = finfo_buffer($f, $fileData, FILEINFO_MIME_TYPE);
          // Generate fileName
          $ext = $this->getMimeTypeExtension($mimeType);
          $avatar_dir = \Drupal::config('system.file')->get('default_scheme') . '://wechat_avatar';
          \Drupal::service('file_system')->prepareDirectory($avatar_dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
          $file = file_save_data($fileData, $avatar_dir . '/'.$drupal_user->id().$ext, FileSystemInterface::EXISTS_RENAME);
          $drupal_user->set('user_picture', $file);
          $need_save = true;
        }
      }

      // 前面已检查过手机号 Drupal 账号，说明手机号还不存在系统中，绑定手机号到新建的账号
      if ($drupal_user->hasField('phone') && $drupal_user->get('phone')->isEmpty() && !empty($phone)) {
        $drupal_user->set('phone', $util->getCallableNumber($phone));
        $need_save = true;
      }

      if ($need_save) $drupal_user->save();

      $this->alterUser($drupal_user);

      $wechat_user->setOwnerId($drupal_user->id());
      $wechat_user->save();
    }

    return $wechat_user->getOwner();
  }

  /**
   * 检查一个 open_id 是否在当前应用连接过
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @return false|WechatUser
   */
  protected function hadConnected($open_id) {
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('app_id', $this->configuration['appId'])
      ->condition('open_id', $open_id);
    $ids = $query->execute();

    if (!empty($ids)) {
      return WechatUser::load(array_pop($ids));
    } else return false;
  }

  /**
   * 检查一个 union_id 是否在其他微信应用连接过
   * @param $union_id
   * @return bool|EntityInterface|WechatUser|null
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  protected function hadConnectedByElseWechatApps($union_id) {
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('union_id', $union_id);
    $ids = $query->execute();
    if (count($ids)) {
      return WechatUser::load(array_pop($ids));
    } else return false;
  }

  /**
   * 提供一个 HOOK，使得其他模块可以对新用户的信息进行修改
   * @param $drupal_user
   */
  protected function alterUser(&$drupal_user){
    \Drupal::moduleHandler()->alter('wechat_connected_user', $drupal_user);
  }


  /**
   * 通过微信Oauth2接口，以 code 换取 AccessToken
   *
   * @param $code
   * @return mixed
   * @throws Exception
   */
  public function getAccessToken($code) {

    $client = new \GuzzleHttp\Client();

    $query_params = [
      'appid' => $this->configuration['appId'],
      'secret' => $this->configuration['appSecret'],
      'code' => $code,
      'grant_type' => 'authorization_code'
    ];
    $res = $client->request('GET', self::ACCESS_TOKEN_ENDPOINT . '?' . http_build_query($query_params));
    if ($res->getStatusCode() === 200) {
      $result = json_decode($res->getBody());
      if (isset($result->access_token)) {
        return $result;
      } else {
        throw new Exception('fetch wechat access token fail. ' . $res->getBody());
      }
    }
  }

  /**
   * 通过微信服务端 UserInfo 接口获取用户信息
   *
   * @param $open_id
   * @param $access_token
   * @return mixed
   * @throws Exception
   */
  public function receiveUserInfo($open_id, $access_token) {

    $client = new \GuzzleHttp\Client();
    $query_params = [
      'access_token' => $access_token,
      'openid' => $open_id,
      'lang' => 'zh_CN'
    ];
    $res = $client->request('GET', self::USER_INFO_ENDPOINT . '?' . http_build_query($query_params));
    if ($res->getStatusCode() === 200) {
      $result = json_decode($res->getBody());
      if (isset($result->errcode)) {
        throw new Exception('fetch wechat user_info fail. ' . $res->getBody());
      } else {
        return $result;
      }
    }
  }

  /**
   * 查找 uid 在当前微信应用下的 WechatUser 对象
   * @param $uid
   * @return EntityInterface|WechatUser|null
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getWechatUserByDrupalUser($uid) {
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('app_id', $this->configuration['appId'])
      ->condition('user_id', $uid);
    $ids = $query->execute();

    $wechat_user = null;
    if (count($ids)) {
      $wechat_user = WechatUser::load(array_pop($ids));
    }
    return $wechat_user;
  }

  /**
   * 创建 Drupal 账号
   * @param $username
   * @param $email
   * @return User
   */
  protected function createUser($username, $email) {
    return \Drupal::getContainer()->get('enhanced_user.user_creator')->createUser($username, $email);
  }

  /**
   * 据手机号查找 Drupal 账号
   *
   * @param $phone
   * @return bool|EntityInterface|mixed
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  protected function getDrupalUserByPhone($phone) {
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
      'phone' => $phone
    ]);

    if (count($users)) {
      return array_pop($users);
    } else return false;
  }

  protected function getMimeTypeExtension($mimeType) {
    $mimeTypes = [
      'image/png' => 'png',
      'image/jpeg' => 'jpg',
      'image/gif' => 'gif',
      'image/bmp' => 'bmp',
      'image/vnd.microsoft.icon' => 'ico',
      'image/tiff' => 'tiff',
      'image/svg+xml' => 'svg',
    ];
    if (isset($mimeTypes[$mimeType])) {
      return '.' . $mimeTypes[$mimeType];
    }
    else {
      $split = explode('/', $mimeType);
      return '.' . $split[1];
    }
  }
}
