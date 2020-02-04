<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\Core\Session\AccountInterface;
use Drupal\wechat_connect\Entity\WechatUser;
use Drupal\wechat_connect\Entity\WechatUserInterface;
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

  protected function getSessionData($code)
  {
    $session = $this->getSDK()->auth->session($code);

    if (isset($session['errcode']) && $session['errcode'] !== 0) {
      $msg = 'Connect fail: '.$session['errmsg'];
      \Drupal::logger('wechat_connect')->notice($msg);
      throw new \Exception($msg);
    }
    return $session;
  }

  protected function getOpenIdFromSessionData($session_data)
  {
    return $session_data['openid'];
  }

  protected function saveWechatUserEntity(WechatUserInterface &$wechat_user, $session_data)
  {
    $wechat_user->setSessionKey($session_data['session_key']);
    if (isset($session_data['unionid'])) $wechat_user->setUnionId($session_data['unionid']);
  }

  public function register($client_id, $connect_id, $phone = null, $extend_data = [])
  {
    // 检查是否连接过
    $wechat_user = WechatUser::load($connect_id);
    if (!($wechat_user instanceof WechatUser)) throw new \Exception('Can not find wechat user, maybe it has not connected yet.');

    // 解密数据，提取手机号
    if (isset($extend_data['phone'])) {
      $phone = $this->decryptPhoneData($wechat_user->getSessionKey(), $extend_data['phone']['iv'], $extend_data['phone']['encryptedData'])['purePhoneNumber'];
    }

    return parent::register($client_id, $connect_id, $phone, $extend_data);
  }

  protected function getWechatUserInfo(WechatUserInterface $wechat_user, $extend_data = [])
  {
    $user_info = null;
    // 解密数据，提取用户个人信息
    if (isset($extend_data['user_info'])) {
      $user_info = $this->decryptPhoneData($wechat_user->getSessionKey(), $extend_data['user_info']['iv'], $extend_data['user_info']['encryptedData']);
    }
    return $user_info;
  }

  protected function saveDrupalUserInfo(&$drupal_user, $user_info)
  {
    $drupal_user->set('nick_name', $user_info['nickname']);
  }

  public function decryptPhoneData($session_key, $iv, $encryptedData) {
    return $this->getSDK()->encryptor->decryptData($session_key, $iv, $encryptedData);
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
