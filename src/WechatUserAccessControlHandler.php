<?php

namespace Drupal\wechat_connect;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Wechat user entity.
 *
 * @see \Drupal\wechat_connect\Entity\WechatUser.
 */
class WechatUserAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\wechat_connect\Entity\WechatUserInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view wechat user entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit wechat user entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete wechat user entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add wechat user entities');
  }

}
