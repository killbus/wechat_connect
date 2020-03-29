<?php

namespace Drupal\wechat_connect\Plugin\Oauth2Grant;

use Drupal\simple_oauth\Plugin\Oauth2Grant\Password;
use Drupal\wechat_connect\Oauth2\WeChatNativeAppCodeGrant;

/**
 * @Oauth2Grant(
 *   id = "wechat_native_app_code",
 *   label = @Translation("WeChat Native App Code")
 * )
 */
class WeChatNativeAppCode extends Password {

  /**
   * {@inheritdoc}
   */
  public function getGrantType() {
    $grant = new WeChatNativeAppCodeGrant($this->userRepository, $this->refreshTokenRepository);
    $settings = $this->configFactory->get('simple_oauth.settings');
    $grant->setRefreshTokenTTL(new \DateInterval(sprintf('PT%dS', $settings->get('refresh_token_expiration'))));
    return $grant;
  }

}
