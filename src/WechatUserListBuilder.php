<?php

namespace Drupal\wechat_connect;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Wechat user entities.
 *
 * @ingroup wechat_connect
 */
class WechatUserListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Wechat user ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\wechat_connect\Entity\WechatUser */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.wechat_user.edit_form',
      ['wechat_user' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
