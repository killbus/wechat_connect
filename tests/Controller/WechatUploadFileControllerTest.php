<?php

namespace Drupal\wechat_connect\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides automated tests for the wechat_connect module.
 */
class WechatUploadFileControllerTest extends WebTestBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "wechat_connect WechatUploadFileController's controller functionality",
      'description' => 'Test Unit for module wechat_connect and controller WechatUploadFileController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests wechat_connect functionality.
   */
  public function testWechatUploadFileController() {
    // Check that the basic functions of module wechat_connect.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
