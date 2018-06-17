<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Wechat application type plugins.
 */
interface WechatApplicationTypeInterface extends PluginInspectionInterface {

  // Add get/set methods for your plugin type here.
  public function connect($client_id, $code);
}