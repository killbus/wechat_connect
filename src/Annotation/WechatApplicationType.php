<?php

namespace Drupal\wechat_connect\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Wechat application type item annotation object.
 *
 * @see \Drupal\wechat_connect\Plugin\WechatApplicationTypeManager
 * @see plugin_api
 *
 * @Annotation
 */
class WechatApplicationType extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
