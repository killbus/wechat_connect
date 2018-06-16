<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Wechat user entities.
 */
class WechatUserViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
