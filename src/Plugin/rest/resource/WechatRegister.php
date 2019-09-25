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
 *   id = "wechat_register",
 *   label = @Translation("Wechat register"),
 *   uri_paths = {
 *     "create" = "/api/rest/wechat_connect/register"
 *   }
 * )
 */
class WechatRegister extends ResourceBase {

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
   * Constructs a new WechatRegister object.
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
   * @param $data
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function post($data) {

    $client_id = $app_id = $connect_id = $phone = null;
    $extend_data = [];
    extract($data);
    if (empty($client_id) || empty($app_id) || empty($connect_id)) {
      throw new BadRequestHttpException('client_id, app_id and connect_id is require.');
    }

    // 查找 WechatApplication
    $wechat_application = WechatApplication::load($app_id);
    if (!($wechat_application instanceof WechatApplication)) {
      throw new BadRequestHttpException('app_id is invalid.');
    }

    try {
      /** @var WechatApplicationTypeInterface $plugin */
      $plugin = $this->applicationTypePluginManager->createInstance($wechat_application->getType(), [
        'appId' => $wechat_application->id(),
        'appSecret' => $wechat_application->getSecret()
      ]);
      $result = $plugin->register($client_id, $connect_id, $phone, $extend_data);
    } catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    return new ModifiedResourceResponse($result, 200);
  }

  public function permissions() {
    return [];
  }
}
