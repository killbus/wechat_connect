<?php

namespace Drupal\wechat_connect\Plugin\Oauth2Grant;

use Drupal\simple_oauth\Plugin\Oauth2Grant\Password;
use Drupal\wechat_connect\Oauth2\WeChatMiniProgramCodeGrant;

/**
 * @Oauth2Grant(
 *   id = "wechat_mini_program_code",
 *   label = @Translation("WeChat Mini Program Code")
 * )
 */
class WeChatMiniProgramCode extends Password {

  /**
   * {@inheritdoc}
   */
  public function getGrantType() {
    $grant = new WeChatMiniProgramCodeGrant($this->userRepository, $this->refreshTokenRepository);
    $settings = $this->configFactory->get('simple_oauth.settings');
    $grant->setRefreshTokenTTL(new \DateInterval(sprintf('PT%dS', $settings->get('refresh_token_expiration'))));
    return $grant;
  }

}
