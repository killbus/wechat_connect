<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\simple_oauth_code\AuthorizationCodeGeneratorInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\wechat_connect\Entity\WechatUser;
use Drupal\wechat_connect\Entity\WechatUserInterface;
use GuzzleHttp\Client;

/**
 * Base class for Wechat application type plugins.
 */
abstract class WechatApplicationTypeBase extends PluginBase implements WechatApplicationTypeInterface {

  const ENDPOINT_ACCESS_TOKEN = 'https://api.weixin.qq.com/sns/oauth2/access_token';
  const ENDPOINT_REFRESH_TOKEN = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';
  const ENDPOINT_USER_INFO = 'https://api.weixin.qq.com/sns/userinfo';

  /**
   * @inheritDoc
   */
  public function connect($drupal_oauth2_client_id, $wechat_app_id, $code) {
    $session_data = $this->getSessionData($code);
    $open_id = $this->getOpenIdFromSessionData($session_data);

    $wechat_user = $this->hadConnected($open_id);
    if (!$wechat_user instanceof WechatUserInterface) {
      $user_data = [
        'app_id' => $this->configuration['appId'],
        'open_id' => $open_id
      ];
      $wechat_user = WechatUser::create($user_data);
    }
    $this->saveWechatUserEntity($wechat_user, $session_data);
    $wechat_user->save();

    return $this->generateDrupalConnectInfo($drupal_oauth2_client_id, $wechat_user);
  }

  protected function getSessionData($code) {
    return $this->getWechatOauth2AccessToken($code);
  }

  protected function getOpenIdFromSessionData($session_data) {
    return $session_data->openid;
  }

  protected function saveWechatUserEntity(WechatUserInterface &$wechat_user, $session_data) {
    $wechat_user->setToken($session_data->access_token);
  }

  /**
   * @inheritDoc
   */
  public function register($drupal_oauth2_client_id, $connect_id, $phone, $extend_data = []) {
    // 检查是否连接过
    $wechat_user = WechatUser::load($connect_id);
    if (!($wechat_user instanceof WechatUser)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    $user_info = $this->getWechatUserInfo($wechat_user, $extend_data);
    $drupal_user = $wechat_user->getOwner();
    if (!$drupal_user instanceof UserInterface) {
      // 1、通过 unionid 查找 drupal_user
      $unionid = $this->getUnionIdFromWechatUserInfo($wechat_user, $user_info);
      if ($unionid) {
        $else_app_wechat_user = $this->hadConnectedByElseWechatApps($unionid);
        if ($else_app_wechat_user instanceof WechatUser && $else_app_wechat_user->getOwnerId()) {
          $drupal_user = $else_app_wechat_user->getOwner();
        }
      }
      // 2、通过手机号查找 drupal_user
      if (!$drupal_user instanceof UserInterface) {
        $drupal_user = empty($phone) ? null : $this->getDrupalUserByPhone($phone);
      }
      // 3、创建新的 drupal_user
      if (!$drupal_user instanceof UserInterface) {
        $username = $this->getNewUsername($user_info);
        $drupal_user = $this->createUser($username);
      }
    }

    // 保存 drupal_user 数据
    if ($phone) $drupal_user->set('phone', $phone);
    $this->saveDrupalUserInfo($drupal_user, $user_info);
    $this->alterUser($drupal_user);
    $drupal_user->save();

    // 关联 wechat_user 与 drupal_user
    $wechat_user->setOwner($drupal_user);
    $wechat_user->save();

    return $this->generateDrupalConnectInfo($drupal_oauth2_client_id, $wechat_user);
  }

  protected function getWechatUserInfo(WechatUserInterface $wechat_user, $extend_data = []) {
    $client = new Client();
    $query_params = [
      'access_token' => $wechat_user->getToken(),
      'openid' => $wechat_user->getOpenId(),
      'lang' => 'zh_CN'
    ];
    $res = $client->request('GET', self::ENDPOINT_USER_INFO . '?' . http_build_query($query_params));
    if ($res->getStatusCode() === 200) {
      $result = json_decode($res->getBody());
      if (isset($result->errcode)) {
        throw new \Exception('fetch wechat user_info fail. ' . $res->getBody());
      } else {
        return $result;
      }
    }
  }

  protected function getUnionIdFromWechatUserInfo(WechatUserInterface $wechat_user, $user_info) {
    return isset($user_info->unionid) ? $user_info->unionid : null;
  }

  protected function getNewUsername($user_info) {
    return isset($user_info->nickname) ? $user_info->nickname : '微信用户';
  }

  /**
   * 保存微信个人信息到 Drupal 用户，
   * 不需要手动调用 ->save()。
   * @param $drupal_user
   * @param $user_info
   */
  protected function saveDrupalUserInfo(&$drupal_user, $user_info) {
    $drupal_user->set('nick_name', $user_info->nickname);
  }

  protected function generateDrupalConnectInfo($drupal_oauth2_client_id, WechatUser $wechat_user, $extend_data = []) {
    $authorization = null;
    $active = false;
    if ($wechat_user->getOwnerId()) {
      // 已经注册，生成 simple_oauth code
      /** @var AuthorizationCodeGeneratorInterface $generator */
      $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
      $authorization = $generator->generate($drupal_oauth2_client_id, $wechat_user->getOwner());
      if ($wechat_user->getOwner()->isActive()) {
        $active = true;
      }
    }
    return [
      'active' => $active,  // 用户账号可用状态
      'authorization' => $authorization,  // 用于创建 Drupal access_token 的 auth_code
      'connect_id' => $wechat_user->id(), // WechatUser entity ID
      'extend_data' => $extend_data       // 需要返回的额外数据
    ];
  }

  protected function getWechatOauth2AccessToken($code) {
    $client = new Client();
    $query_params = [
      'appid' => $this->configuration['appId'],
      'secret' => $this->configuration['appSecret'],
      'code' => $code,
      'grant_type' => 'authorization_code'
    ];
    $res = $client->request('GET', self::ENDPOINT_ACCESS_TOKEN . '?' . http_build_query($query_params));
    if ($res->getStatusCode() === 200) {
      $result = json_decode($res->getBody());
      if (isset($result->access_token)) {
        return $result;
      } else {
        throw new \Exception('fetch wechat access token fail. ' . $res->getBody());
      }
    }
  }

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

  protected function alterUser(&$drupal_user){
    \Drupal::moduleHandler()->alter('wechat_connected_user', $drupal_user);
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
  protected function createUser($username) {
    return \Drupal::getContainer()->get('enhanced_user.user_creator')->createUser($username);
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
