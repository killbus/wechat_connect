<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Wechat applcation entity.
 *
 * @ConfigEntityType(
 *   id = "wechat_applcation",
 *   label = @Translation("Wechat applcation"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wechat_connect\WechatApplcationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wechat_connect\Form\WechatApplcationForm",
 *       "edit" = "Drupal\wechat_connect\Form\WechatApplcationForm",
 *       "delete" = "Drupal\wechat_connect\Form\WechatApplcationDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\wechat_connect\WechatApplcationHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wechat_applcation",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "app_id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/wechat/wechat_applcation/{wechat_applcation}",
 *     "add-form" = "/admin/config/wechat/wechat_applcation/add",
 *     "edit-form" = "/admin/config/wechat/wechat_applcation/{wechat_applcation}/edit",
 *     "delete-form" = "/admin/config/wechat/wechat_applcation/{wechat_applcation}/delete",
 *     "collection" = "/admin/config/wechat/wechat_applcation"
 *   }
 * )
 */
class WechatApplcation extends ConfigEntityBase implements WechatApplcationInterface {

  /**
   * The Wechat applcation ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Wechat applcation label.
   *
   * @var string
   */
  protected $label;

}
