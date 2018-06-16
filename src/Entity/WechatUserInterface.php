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
   * Gets the Wechat user name.
   *
   * @return string
   *   Name of the Wechat user.
   */
  public function getName();

  /**
   * Sets the Wechat user name.
   *
   * @param string $name
   *   The Wechat user name.
   *
   * @return \Drupal\wechat_connect\Entity\WechatUserInterface
   *   The called Wechat user entity.
   */
  public function setName($name);

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
   * Returns the Wechat user published status indicator.
   *
   * Unpublished Wechat user are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Wechat user is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Wechat user.
   *
   * @param bool $published
   *   TRUE to set this Wechat user to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\wechat_connect\Entity\WechatUserInterface
   *   The called Wechat user entity.
   */
  public function setPublished($published);

}
