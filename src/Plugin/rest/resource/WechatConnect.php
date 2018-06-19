<?php

namespace Drupal\wechat_connect\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\wechat_connect\Entity\WechatApplication;
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
 *   id = "wechat_connect",
 *   label = @Translation("Wechat connect"),
 *   uri_paths = {
 *     "create" = "/api/rest/wechat_connect/connect"
 *   }
 * )
 */
class WechatConnect extends ResourceBase {

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
   * Constructs a new WechatConnect object.
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function post($data) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $client_id = $app_id = $code = null;
    extract($data);
    if (empty($client_id) || empty($app_id) || empty($code)) {
      throw new BadRequestHttpException('client_id, app_id and code is require.');
    }

    // 查找 WechatApplication
    $wechat_application = WechatApplication::load($app_id);
    if (!($wechat_application instanceof WechatApplication)) {
      throw new BadRequestHttpException('app_id is invalid.');
    }

    /** @var WechatApplicationTypeInterface $plugin */
    $plugin = $this->applicationTypePluginManager->createInstance($wechat_application->getType(), [
      'appId' => $wechat_application->id(),
      'appSecret' => $wechat_application->getSecret()
    ]);
    $result = $plugin->connect($client_id, $code);

    return new ModifiedResourceResponse($result, 200);
  }

  public function permissions() {
    return [];
  }
}
