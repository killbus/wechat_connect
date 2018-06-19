<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Unicode;
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
   * @param $code
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function connect($client_id, $code) {
    // 获取 access_token
    $access_token = $this->getAccessToken($code);
    $user_info = $this->receiveUserInfo($access_token->openid, $access_token->access_token);

    // 检查是否连接过
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('app_id', $this->configuration['appId'])
      ->condition('open_id', $user_info->openid);
    $ids = $query->execute();

    $wechat_user = null;
    if (empty($ids)) {
      // 还没有连接过，创建wechat_user
      $user_data = [
        'app_id' => $this->configuration['appId'],
        'open_id' => $user_info->openid,
        'token' => json_encode($access_token)
      ];
      $wechat_user = WechatUser::create($user_data);
    } else {
      // 连接过，更新wechat_user
      $wechat_user = WechatUser::load(array_pop($ids));
      $wechat_user->setToken(json_encode($access_token));
    }

    // 如果有 union_id，保存它
    if (isset($user_info->union_id)) $wechat_user->setUnionId($user_info->union_id);


    if (!$wechat_user->getOwnerId() && isset($user_info->union_id)) {
      // 未注册，检查union_id，看用户是否在其他应用注册过
      $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
      $query
        ->condition('union_id', $user_info->union_id);
      $ids = $query->execute();
      if (count($ids)) {
        $else_app_wechat_user = WechatUser::load(array_pop($ids));
        if ($else_app_wechat_user->getOwnerId()) {
          $wechat_user->setOwnerId($else_app_wechat_user->getOwnerId());
        }
      }
    }

    $wechat_user->save();

    $authorization = null;
    if ($wechat_user->getOwnerId()) {
      // 已经注册，生成 simple_oauth code
      /** @var AuthorizationCodeGeneratorInterface $generator */
      $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
      $authorization = $generator->generate($client_id, $wechat_user->getOwner());
    }

    return [
      'authorization' => $authorization,
      'user_info' => json_decode(json_encode($user_info), true)
    ];
  }

  /**
   * @param $open_id
   * @param $username
   * @param $password
   * @param $phone
   * @param $avatar
   * @throws \Exception
   */
  public function register($client_id, $open_id, $phone) {
    // 检查是否连接过
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('app_id', $this->configuration['appId'])
      ->condition('open_id', $open_id);
    $ids = $query->execute();

    if (!count($ids)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    $drupal_user = null;
    if ($phone) {
      // 如果提供了手机号，查看手机号是否已经注册
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
        'phone' => $phone
      ]);

      if (count($users)) {
        $drupal_user = array_pop($users);
      }
    }

    $wechat_user = WechatUser::load(array_pop($ids));

    if (!$drupal_user) {
      // 拉取用户信息
      $access_token = json_decode($wechat_user->getToken());
      $user_info = $this->receiveUserInfo($wechat_user->getOpenId(), $access_token->access_token);

      $drupal_user = $this->createUser($user_info->nickname, $open_id.'@wechat.com');

      if ($phone) {
        $drupal_user->set('phone', $phone);
        $drupal_user->save();
      }
    }

    $wechat_user->setOwnerId($drupal_user->id());
    $wechat_user->save();

    // 生成 simple_oauth code
    $authorization = null;
    if ($wechat_user->getOwnerId()) {
      // 已经注册，生成 simple_oauth code
      /** @var AuthorizationCodeGeneratorInterface $generator */
      $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
      $authorization = $generator->generate($client_id, $wechat_user->getOwner());
    }

    return [
      'authorization' => $authorization
    ];
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
   * @param $username
   * @param $email
   * @return User
   */
  protected function createUser($username, $email) {
    return \Drupal::getContainer()->get('user_api.user_creator')->createUser($username, $email);
  }
}
