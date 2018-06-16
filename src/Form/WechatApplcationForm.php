<?php

namespace Drupal\wechat_connect\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WechatApplcationForm.
 */
class WechatApplcationForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $wechat_applcation = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $wechat_applcation->label(),
      '#description' => $this->t("Label for the Wechat applcation."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $wechat_applcation->id(),
      '#machine_name' => [
        'exists' => '\Drupal\wechat_connect\Entity\WechatApplcation::load',
      ],
      '#disabled' => !$wechat_applcation->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $wechat_applcation = $this->entity;
    $status = $wechat_applcation->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Wechat applcation.', [
          '%label' => $wechat_applcation->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Wechat applcation.', [
          '%label' => $wechat_applcation->label(),
        ]));
    }
    $form_state->setRedirectUrl($wechat_applcation->toUrl('collection'));
  }

}
