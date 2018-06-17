<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Unicode;
use Drupal\simple_oauth_code\AuthorizationCodeGeneratorInterface;
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
      'user_info' => json_encode($user_info)
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
  public function register($open_id, $username, $password, $phone, $avatar) {
    // 检查是否连接过
    $query = \Drupal::entityTypeManager()->getStorage('wechat_user')->getQuery();
    $query
      ->condition('app_id', $this->configuration['appId'])
      ->condition('open_id', $open_id);
    $ids = $query->execute();

    if (!count($ids)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    $wechat_user = WechatUser::load(array_pop($ids));
    $drupal_user = $this->createUser($username, $open_id.'@wechat.com');
    $wechat_user->setOwnerId($drupal_user->id());
    $wechat_user->save();

    // 生成 simple_oauth code
    $authorization = null;
    if ($wechat_user->getOwnerId()) {
      // 已经注册，生成 simple_oauth code
      /** @var AuthorizationCodeGeneratorInterface $generator */
      $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
      $authorization = $generator->generate($clent_id, $wechat_user->getOwner());
    }

    return [
      'authorization' => $authorization
    ];
  }


  public function getAccessToken($code) {

    $client = new \GuzzleHttp\Client();
    try {
      $query_params = [
        'appid' => $this->configuration['appId'],
        'secret' => $this->configuration['appSecret'],
        'code' => $code,
        'grant_type' => 'authorization_code'
      ];
      $res = $client->request('GET', self::ACCESS_TOKEN_ENDPOINT . '?' . http_build_query($query_params));
      if ($res->getStatusCode() === 200) {
        return json_decode($res->getBody());
      }

    } catch (GuzzleException $e) {

    }
  }

  public function receiveUserInfo($open_id, $access_token) {

    $client = new \GuzzleHttp\Client();
    try {
      $query_params = [
        'access_token' => $access_token,
        'openid' => $open_id,
        'lang' => 'zh_CN'
      ];
      $res = $client->request('GET', self::USER_INFO_ENDPOINT . '?' . http_build_query($query_params));
      if ($res->getStatusCode() === 200) {
        return json_decode($res->getBody());
      }

    } catch (GuzzleException $e) {

    }
  }

  protected function createUser($username, $email) {
    return \Drupal::getContainer()->get('user_api.user_creator')->createUser($username, $email);
  }
}
