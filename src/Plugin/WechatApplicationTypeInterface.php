<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for Wechat application type plugins.
 */
interface WechatApplicationTypeInterface extends PluginInspectionInterface {

  /**
   * @param $code string WeChat authorize code
   * @param array $extend_data
   * @return UserInterface
   */
  public function connect($code, $extend_data = []);

  public function register($wechat_user, $user_info, $phone = null);
}
