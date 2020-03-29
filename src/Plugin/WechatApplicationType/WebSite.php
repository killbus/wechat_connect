<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;

/**
 * Standard Oauth2 code authentication, user authorize by scan qr code with WeChat app.
 *
 * 1. WebApp(SPA) / Drupal instance(SSR) redirect to WeChat authorization endpoint, WeChat user authorize by scan QR code with WeChat app.
 * 2. Redirect back to Drupal instance with the WeChat authorization code.
 * 3. Drupal instance process WeChat Connect and Register logic.
 * 4. Drupal instance redirect to WebApp(SPA) with Oauth2 code / Drupal instance(SSR) process account login session.
 *
 * @WechatApplicationType(
 *   id = "web_site",
 *   label = @Translation("Web Site")
 * )
 */
class WebSite extends WechatApplicationTypeBase {

  public function authorizeRedirect($destination = null)
  {
    return $this->getAuthorizeRedirect(self::WEBSITE_AUTHORIZE_ENDPOINT, $destination);
  }
}
