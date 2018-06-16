<?php

namespace Drupal\wechat_connect\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Wechat user edit forms.
 *
 * @ingroup wechat_connect
 */
class WechatUserForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\wechat_connect\Entity\WechatUser */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Wechat user.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Wechat user.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.wechat_user.canonical', ['wechat_user' => $entity->id()]);
  }

}
