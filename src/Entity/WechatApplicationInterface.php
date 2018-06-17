<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Wechat application entities.
 */
interface WechatApplicationInterface extends ConfigEntityInterface {

  // Add get/set methods for your configuration properties here.
  /**
   * @param $secret
   * @return WechatApplicationInterface
   */
  public function setSecret($secret);

  /**
   * @return string
   */
  public function getSecret();

  /**
   * @param $type
   * @return WechatApplicationInterface
   */
  public function setType($type);

  /**
   * @return string
   */
  public function getType();
}
