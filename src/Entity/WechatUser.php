<?php

namespace Drupal\wechat_connect\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Wechat user entity.
 *
 * @ingroup wechat_connect
 *
 * @ContentEntityType(
 *   id = "wechat_user",
 *   label = @Translation("Wechat user"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wechat_connect\WechatUserListBuilder",
 *     "views_data" = "Drupal\wechat_connect\Entity\WechatUserViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\wechat_connect\Form\WechatUserForm",
 *       "add" = "Drupal\wechat_connect\Form\WechatUserForm",
 *       "edit" = "Drupal\wechat_connect\Form\WechatUserForm",
 *       "delete" = "Drupal\wechat_connect\Form\WechatUserDeleteForm",
 *     },
 *     "access" = "Drupal\wechat_connect\WechatUserAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\wechat_connect\WechatUserHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "wechat_user",
 *   admin_permission = "administer wechat user entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "open_id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "canonical" = "/admin/people/wechat_user/{wechat_user}",
 *     "add-form" = "/admin/people/wechat_user/add",
 *     "edit-form" = "/admin/people/wechat_user/{wechat_user}/edit",
 *     "delete-form" = "/admin/people/wechat_user/{wechat_user}/delete",
 *     "collection" = "/admin/people/wechat_user",
 *   },
 *   field_ui_base_route = "wechat_user.settings"
 * )
 */
class WechatUser extends ContentEntityBase implements WechatUserInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppId() {
    return $this->get('app_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAppId($app_id) {
    $this->set('app_id', $app_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenId() {
    return $this->get('open_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setOpenId($open_id) {
    $this->set('open_id', $open_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnionId() {
    return $this->get('union_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnionId($union_id) {
    $this->set('union_id', $union_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken() {
    return $this->get('token')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setToken($data) {
    $this->set('token', $data);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // The ID of user account associated.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The Drupal uid associated with social network.'))
      ->setSetting('target_type', 'user');

    $fields['app_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plugin ID'))
      ->setDescription(t('Identifier for Social Auth implementer.'));

    $fields['open_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider user ID'))
      ->setDescription(t('The unique user ID in the provider.'));

    $fields['union_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider user ID'))
      ->setDescription(t('The unique user ID in the provider.'));

    $fields['token'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Token received after user authentication'))
      ->setDescription(t('Used to make API calls.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
