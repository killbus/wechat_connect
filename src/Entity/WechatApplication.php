<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Wechat application entity.
 *
 * @ConfigEntityType(
 *   id = "wechat_application",
 *   label = @Translation("Wechat application"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wechat_connect\WechatApplicationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wechat_connect\Form\WechatApplicationForm",
 *       "edit" = "Drupal\wechat_connect\Form\WechatApplicationForm",
 *       "delete" = "Drupal\wechat_connect\Form\WechatApplicationDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\wechat_connect\WechatApplicationHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wechat_application",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/wechat/wechat_application/{wechat_application}",
 *     "add-form" = "/admin/config/wechat/wechat_application/add",
 *     "edit-form" = "/admin/config/wechat/wechat_application/{wechat_application}/edit",
 *     "delete-form" = "/admin/config/wechat/wechat_application/{wechat_application}/delete",
 *     "collection" = "/admin/config/wechat/wechat_application"
 *   }
 * )
 */
class WechatApplication extends ConfigEntityBase implements WechatApplicationInterface {

  /**
   * The Wechat application ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Wechat application label.
   *
   * @var string
   */
  protected $label;

  /**
   * @var string
   */
  protected $secret;

  /**
   * @var string
   */
  protected $type;

  /**
   * @param $secret
   * @return WechatApplicationInterface
   */
  public function setSecret($secret) {
    $this->secret = $secret;
    return $this;
  }

  /**
   * @return string
   */
  public function getSecret() {
    return $this->secret;
  }

  /**
   * @param $type
   * @return WechatApplicationInterface
   */
  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }
}
