<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;
use EasyWeChat\Factory;

/**
 * Standard Oauth2 code authentication, user authorize by a modal windows in WeChat built-in browser.
 *
 * 1. WebApp(SPA) / Drupal instance(SSR) redirect to WeChat authorization endpoint, WeChat user authorize by a modal windows in WeChat built-in browser.
 * 2. Redirect back to Drupal instance with the WeChat authorization code.
 * 3. Drupal instance process WeChat Connect and Register logic.
 * 4. Drupal instance redirect to WebApp(SPA) with Oauth2 code / Drupal instance(SSR) process account login session.
 *
 * @WechatApplicationType(
 *   id = "media_platform",
 *   label = @Translation("Media Platform")
 * )
 */
class MediaPlatform extends WechatApplicationTypeBase {

  /**
   * @param $url
   * @param array $js_apis
   * @return array|string
   * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
   * @throws \Psr\SimpleCache\InvalidArgumentException
   */
  public function JsSdkConfig($url, $js_apis = []) {
    $js_sdk = $this->getEasyWechatSdk()->jssdk;
    $js_sdk->setUrl($url);

    return $js_sdk->buildConfig($js_apis, false, false, false);
  }

  /**
   * @param $uid
   * @param $template_id
   * @param array $arguments
   * @param $url
   * @return array|bool|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
   */
  public function sendTemplateMessage($uid, $template_id, array $arguments, $url) {
    $wechat_user = $this->getWechatUserByDrupalUser($uid);
    if (!$wechat_user) return false;

    $notice_sdk = $this->getEasyWechatSdk()->template_message;
    return $notice_sdk->send([
      'touser' => $wechat_user->getOpenId(),
      'template_id' => $template_id,
      'url' => $url,
      'data' => $arguments
    ]);
  }

  /**
   * @return \EasyWeChat\OfficialAccount\Application
   */
  private function getEasyWechatSdk() {
    $app = Factory::officialAccount([
      // 前面的appid什么的也得保留哦
      'app_id' => $this->configuration['appId'],
      'secret'  => $this->configuration['appSecret']
    ]);

    return $app;
  }

  public function authorizeRedirect($destination = null)
  {
    return $this->getAuthorizeRedirect(self::MEDIA_PLATFORM_AUTHORIZE_ENDPOINT, $destination);
  }
}
