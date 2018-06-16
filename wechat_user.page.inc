<?php

/**
 * @file
 * Contains wechat_user.page.inc.
 *
 * Page callback for Wechat user entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Wechat user templates.
 *
 * Default template: wechat_user.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_wechat_user(array &$variables) {
  // Fetch WechatUser Entity Object.
  $wechat_user = $variables['elements']['#wechat_user'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
