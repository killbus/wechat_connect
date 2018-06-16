<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Wechat application type plugin manager.
 */
class WechatApplicationTypeManager extends DefaultPluginManager {


  /**
   * Constructs a new WechatApplicationTypeManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/WechatApplicationType', $namespaces, $module_handler, 'Drupal\wechat_connect\Plugin\WechatApplicationTypeInterface', 'Drupal\wechat_connect\Annotation\WechatApplicationType');

    $this->alterInfo('wechat_connect_wechat_application_type_info');
    $this->setCacheBackend($cache_backend, 'wechat_connect_wechat_application_type_plugins');
  }

}
