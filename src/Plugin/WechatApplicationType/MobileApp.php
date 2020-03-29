<?php
namespace Drupal\wechat_connect\Plugin\WechatApplicationType;

use Drupal\wechat_connect\Plugin\WechatApplicationTypeBase;

/**
 * Standard Oauth2 code authentication, user authorize by pull up WeChat app.
 *
 * Solution One:
 * 1. Native app pull up WeChat app by SDK, WeChat user authorize on WeChat app.
 * 2. WeChat redirect to Drupal instance, get the authorization code.
 * 3. Drupal instance process WeChat Connect and Register logic.
 * 4. Drupal instance redirect back to Native app with the Drupal instance authorization code.
 *
 * Solution Two:
 * 1. Native app pull up WeChat app by SDK, WeChat user authorize on WeChat app.
 * 2. Native app get the authorization code.
 * 3. Native app call WeChat:Connect API.
 * 4. Native app call WeChat:Register API (if not register yet).
 * 5. Native app get the Drupal instance authorization code.
 *
 * @WechatApplicationType(
 *   id = "mobile_app",
 *   label = @Translation("MobileApp")
 * )
 */
class MobileApp extends WechatApplicationTypeBase {
}
