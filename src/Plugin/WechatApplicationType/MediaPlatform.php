<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;
use EasyWeChat\Foundation\Application;

/**
 * @WechatApplicationType(
 *   id = "media_platform",
 *   label = @Translation("Media Platform")
 * )
 */
class MediaPlatform extends WechatApplicationTypeBase {

  public function JsSdkConfig($url, $js_apis = []) {
    $js_sdk = $this->getEasyWechatSdk()->js;
    $js_sdk->setUrl($url);

    return $js_sdk->config($js_apis, false, false, false);
  }

  public function sendTemplateMessage($uid, $template_id, array $arguments, $url) {
    $wechat_user = $this->getWechatUserByDrupalUser($uid);
    if (!$wechat_user) return false;

    $notice_sdk = $this->getEasyWechatSdk()->notice;
    return $notice_sdk->send([
      'touser' => $wechat_user->getOpenId(),
      'template_id' => $template_id,
      'url' => $url,
      'data' => $arguments
    ]);
  }

  /**
   * @return Application
   */
  private function getEasyWechatSdk() {
    $app = new Application([
      // 前面的appid什么的也得保留哦
      'app_id' => $this->configuration['appId'],
      'secret'  => $this->configuration['appSecret']
    ]);

    return $app;
  }
}