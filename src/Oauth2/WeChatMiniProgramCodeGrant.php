<?php
namespace Drupal\wechat_connect\Oauth2;


/**
 * Password Improved grant class.
 */
class WeChatMiniProgramCodeGrant extends WeChatNativeAppCodeGrant {

  /**
   * {@inheritdoc}
   */
  public function getIdentifier()
  {
    return 'wechat_mini_program_code';
  }
}
