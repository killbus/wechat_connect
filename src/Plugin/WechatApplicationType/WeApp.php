<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\Core\Session\AccountInterface;
use Drupal\wechat_connect\Entity\WechatUser;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;
use EasyWeChat\Factory;

/**
 * @WechatApplicationType(
 *   id = "we_app",
 *   label = @Translation("Wechat mini App")
 * )
 */
class WeApp extends WechatApplicationTypeBase {

  private $SDK = null;

  /**
   * 小程序有自己的登录方案，使用 code2session接口，
   * 除了保存 openid/union_id，还要保存 session_key，
   * 用于解密小程序端获取的加密开放数据。
   * @param $client_id
   * @param $code
   * @return array|void
   * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
   */
  public function connect($client_id, $code)
  {
    $session = $this->getSDK()->auth->session($code);

    if (isset($session['errcode']) && $session['errcode'] !== 0) {
      $msg = 'Connect fail: '.$session['errmsg'];
      \Drupal::logger('wechat_connect')->notice($msg);
      throw new \Exception($msg);
    }

    $open_id = $session['openid'];
    $union_id = isset($session['unionid']) ? $session['unionid'] : null;
    $session_key = $session['session_key'];
    $wechat_user = $this->makeConnect($open_id, $union_id, null, $session_key);

    return $this->makeConnectResult($client_id, $wechat_user);
  }

  public function register($client_id, $connect_id, $phone = null, $extend_data = [])
  {
    // 检查是否连接过
    $wechat_user = WechatUser::load($connect_id);
    if (!($wechat_user instanceof WechatUser)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    if (isset($extend_data['phone'])) {
      // 解密数据，提取手机号
      $phone = $this->decryptPhoneData($wechat_user->getSessionKey(), $extend_data['phone']['iv'], $extend_data['phone']['encryptedData']);
    }

    $drupal_user = $wechat_user->getOwner();
    if (!($drupal_user instanceof AccountInterface)) {
      if (!empty($phone)) {
        $drupal_user = $this->getDrupalUserByPhone($phone);
      } else {
        $drupal_user = $this->createUser('小程序用户' . $phone, $wechat_user->getOpenId().'@weixin.qq.com');
      }
      $this->alterUser($drupal_user);
      $wechat_user->setOwnerId($drupal_user->id());
      $wechat_user->save();
    }

    if ($drupal_user->get('phone')->isEmpty() && !empty($phone)) {
      $drupal_user->set('phone', $phone);
      $drupal_user->save();
    }

    // 生成 simple_oauth code
    return $this->makeConnectResult($client_id, $wechat_user);
  }

  public function decryptPhoneData($session_key, $iv, $encryptedData) {
    $decryptedData = $this->getSDK()->encryptor->decryptData($session_key, $iv, $encryptedData);
    return $decryptedData['purePhoneNumber'];
  }

  private function getSDK() {
    if ($this->SDK === null) {
      $config = [
        'app_id' => $this->configuration['appId'],
        'secret' => $this->configuration['appSecret'],

        // 下面为可选项
        // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
        'response_type' => 'array',

        'log' => [
          'level' => 'debug',
          'file' => DRUPAL_ROOT.'/../data/wechat_weapp.log',
        ],
      ];

      $this->SDK = Factory::miniProgram($config);
    }
    return $this->SDK;
  }
}
