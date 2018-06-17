<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Wechat user entities.
 *
 * @ingroup wechat_connect
 */
interface WechatUserInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Wechat user creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Wechat user.
   */
  public function getCreatedTime();

  /**
   * Sets the Wechat user creation timestamp.
   *
   * @param int $timestamp
   *   The Wechat user creation timestamp.
   *
   * @return \Drupal\wechat_connect\Entity\WechatUserInterface
   *   The called Wechat user entity.
   */
  public function setCreatedTime($timestamp);


  /**
   * @return string
   */
  public function getAppId();

  /**
   * @param $app_id
   * @return $this
   */
  public function setAppId($app_id);

  /**
   * @return string
   */
  public function getOpenId();

  /**
   * @param $open_id
   * @return $this
   */
  public function setOpenId($open_id);

  /**
   * @return string
   */
  public function getUnionId();

  /**
   * @param $union_id
   * @return $this
   */
  public function setUnionId($union_id);

  /**
   * @return string
   */
  public function getToken();

  /**
   * @param $data
   * @return $this
   */
  public function setToken($data);
}
