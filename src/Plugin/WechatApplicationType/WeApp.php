<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\wechat_connect\Entity\WechatUser;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;

/**
 * Mini Program like Native App, but more.
 *
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
   * @param $code
   * @param array $extend_data
   * @return UserInterface
   * @throws EntityStorageException
   * @throws InvalidConfigException
   */
  public function connect($code, $extend_data = [])
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

    $user_info = [];
    if (isset($extend_data['user_info'])) {
      // 解密用户资料
      $user_info_raw = $this->decryptMiniProgramData($wechat_user->getSessionKey(), $extend_data['user_info']['iv'], $extend_data['user_info']['encryptedData']);
      if (isset($user_info_raw['nickName'])) $user_info['nickname'] = $user_info_raw['nickName'];
      if (isset($user_info_raw['gender'])) $user_info['sex'] = $user_info_raw['gender'];
      if (isset($user_info_raw['avatarUrl'])) $user_info['avatar'] = $user_info_raw['avatarUrl'];
    }

    $phone = null;
    if (isset($extend_data['phone'])) {
      // 解密数据，提取手机号
      $phone = $this->decryptMiniProgramData($wechat_user->getSessionKey(), $extend_data['phone']['iv'], $extend_data['phone']['encryptedData']);
      $phone = $phone['purePhoneNumber'] ?? null;
    }

    if ($wechat_user->getOwner()) return $wechat_user->getOwner();
    else return $this->register($wechat_user, $user_info, $phone);
  }

  public function decryptMiniProgramData($session_key, $iv, $encryptedData) {
    $decryptedData = $this->getSDK()->encryptor->decryptData($session_key, $iv, $encryptedData);
    return $decryptedData;
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

  /**
   * 获取小程序二维码
   * @param $path
   * @param $scene
   * @param array $options
   */
  public function getQRCode($path, $scene, $options = []) {
    return $this->getSDK()->app_code->getUnlimit($scene, [
      'page'  => $path
    ] + $options);
  }
}
