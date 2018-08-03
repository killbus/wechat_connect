<?php

namespace Drupal\wechat_connect\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\wechat_connect\Entity\WechatApplication;
use Drupal\wechat_connect\Plugin\WechatApplicationType\MediaPlatform;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeInterface;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "wechat_js_sdk_config",
 *   label = @Translation("Wechat js sdk config"),
 *   uri_paths = {
 *     "create" = "/api/rest/wechat_connect/js-sdk-config"
 *   }
 * )
 */
class WechatJsSdkConfig extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var WechatApplicationTypeManager
   */
  protected $applicationTypePluginManager;

  /**
   * Constructs a new WechatJsSdkConfig object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param WechatApplicationTypeManager $application_type_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user, WechatApplicationTypeManager $application_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->applicationTypePluginManager = $application_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('wechat_connect'),
      $container->get('current_user'),
      $container->get('plugin.manager.wechat_application_type')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param $data
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function post($data) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $app_id = $url = null;
    $apis = [];
    extract($data);
    if (empty($app_id) || empty($url) || empty($apis)) {
      throw new BadRequestHttpException('app_id, url and apis is require.');
    }

    // 查找 WechatApplication
    $wechat_application = WechatApplication::load($app_id);
    if (!($wechat_application instanceof WechatApplication)) {
      throw new BadRequestHttpException('app_id is invalid.');
    } else {
      if ($wechat_application->getType() !== 'media_platform') {
        throw new BadRequestHttpException('Application type of app_id must be media_platform.');
      }
    }

    try {
      /** @var MediaPlatform $plugin */
      $plugin = $this->applicationTypePluginManager->createInstance($wechat_application->getType(), [
        'appId' => $wechat_application->id(),
        'appSecret' => $wechat_application->getSecret()
      ]);
      $result = $plugin->JsSdkConfig($url, $apis);

      return new ModifiedResourceResponse($result, 200);
    } catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }
  }

  public function permissions() {
    return [];
  }
}
