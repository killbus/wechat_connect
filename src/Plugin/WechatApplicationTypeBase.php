<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Session\AccountInterface;
use Drupal\simple_oauth_code\AuthorizationCodeGeneratorInterface;
use Drupal\user\Entity\User;
use Drupal\wechat_connect\Entity\WechatUser;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Base class for Wechat application type plugins.
 */
abstract class WechatApplicationTypeBase extends PluginBase implements WechatApplicationTypeInterface {

  const ACCESS_TOKEN_ENDPOINT = 'https://api.weixin.qq.com/sns/oauth2/access_token';
  const REFRESH_TOKEN_ENDPOINT = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';
  const USER_INFO_ENDPOINT = 'https://api.weixin.qq.com/sns/userinfo';

  /**
   * 检查一个 open_id 是否在当前应用连接过
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @return bool|\Drupal\Core\Entity\EntityInterface|WechatUser|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
      if ($access_token) $user_data['token'] = $access_token;
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

  protected function makeConnectResult($client_id, WechatUser $wechat_user, $extend_data = []) {
    $authorization = null;
    $active = false;
    if ($wechat_user->getOwnerId()) {
      if ($wechat_user->getOwner()->isActive()) {
        // 已经注册，生成 simple_oauth code
        /** @var AuthorizationCodeGeneratorInterface $generator */
        $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
        $authorization = $generator->generate($client_id, $wechat_user->getOwner());
        $active = true;
      }
    }

    $rs = [
      'active' => $active,
      'authorization' => $authorization,
      'connect_id' => $wechat_user->id(),
      'extend_data' => $extend_data
    ];

    return $rs;
  }

  /**
   * @param $client_id
   * @param $code
   * @return array
   * @throws \Exception
   */
  public function connect($client_id, $code) {

    // 获取 access_token
    $open_id = null;
    $access_token = null;
    try {
      $access_token = $this->getAccessToken($code);
      if (isset($access_token->openid)) $open_id = $access_token->openid;
    } catch (\Exception $e) {
      throw $e;
    }

    // 获取用户信息
    $user_info = null;
    $union_id = null;
    try {
      $user_info = $this->receiveUserInfo($access_token->openid, $access_token->access_token);
      if (isset($user_info->unionid)) $union_id = $user_info->unionid;
    } catch (\Exception $e) {
      \Drupal::logger('wechat_connect')->notice($e->getMessage());
    }

    $wechat_user = $this->makeConnect($open_id, $union_id, $access_token);

    return $this->makeConnectResult($client_id, $wechat_user, $user_info ? json_decode(json_encode($user_info), true) : []);
  }

  /**
   * @param $client_id
   * @param $connect_id
   * @param $phone
   * @param array $extend_data
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function register($client_id, $connect_id, $phone, $extend_data = []) {
    // 检查是否连接过
    $wechat_user = WechatUser::load($connect_id);
    if (!($wechat_user instanceof WechatUser)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    $drupal_user = empty($phone) ? null : $this->getDrupalUserByPhone($phone);

    // 如果还没有关联 Drupal 账号，也没有通过手机号码查到已有 Drupal 账号
    // 那么创建一个新的 Drupal 账号
    if (!($wechat_user->getOwner() instanceof AccountInterface) && !$drupal_user) {

      // 拉取用户信息
      $access_token = json_decode($wechat_user->getToken());
      $user_info = null;
      $username = $phone ? '手机用户'.$phone : '微信用户';
      try {
        $user_info = $this->receiveUserInfo($wechat_user->getOpenId(), $access_token->access_token);
        $username = $user_info->nickname;
      } catch (\Exception $exception) {
        \Drupal::logger('wechat_connect')->notice($exception->getMessage());
      }

      $drupal_user = $this->createUser($username, $wechat_user->getOpenId().'@weixin.qq.com');

      $need_save = false;
      if ($phone) {
        $drupal_user->set('phone', $phone);
        $need_save = true;
      }
      if ($user_info && $drupal_user->hasField('nick_name')) {
        $drupal_user->set('nick_name', $user_info->nickname);
        // TODO:: 保存性别，头像
        $need_save = true;
      }
      if ($need_save) $drupal_user->save();
    }

    $wechat_user->setOwnerId($drupal_user->id());
    $wechat_user->save();

    // 生成 simple_oauth code
    return $this->makeConnectResult($client_id, $wechat_user);
  }

  protected function alterUser(&$drupal_user){
    \Drupal::moduleHandler()->alter('wechat_connected_user', $drupal_user);
  }


  /**
   * @param $code
   * @return mixed
   * @throws \Exception
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
        throw new \Exception('fetch wechat access token fail. ' . $res->getBody());
      }
    }
  }

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
        throw new \Exception('fetch wechat user_info fail. ' . $res->getBody());
      } else {
        return $result;
      }
    }
  }

  /**
   * @param $uid
   * @return \Drupal\Core\Entity\EntityInterface|WechatUser|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @param $username
   * @param $email
   * @return User
   */
  protected function createUser($username, $email) {
    return \Drupal::getContainer()->get('enhanced_user.user_creator')->createUser($username, $email);
  }


  protected function getDrupalUserByPhone($phone) {
    // 如果提供了手机号，查看手机号是否已经注册
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
      'phone' => $phone
    ]);

    if (count($users)) {
      $drupal_user = array_pop($users);
      return $drupal_user;
    } else return false;
  }
}
