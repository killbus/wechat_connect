<?php

namespace Drupal\wechat_connect\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wechat_connect\Entity\WechatApplication;
use Drupal\wechat_connect\Entity\WechatApplicationInterface;
use Drupal\wechat_connect\Plugin\WechatApplicationTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WechatApplicationForm.
 */
class WechatApplicationForm extends EntityForm {

  /**
   * @var \Drupal\wechat_connect\Plugin\WechatApplicationTypeManager
   */
  protected $pluginManager;


  public function __construct(WechatApplicationTypeManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.wechat_application_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var WechatApplicationInterface $wechat_application */
    $wechat_application = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $wechat_application->label(),
      '#description' => $this->t("Label for the Wechat application."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t("appId of the Wechat application."),
      '#default_value' => $wechat_application->id(),
      '#machine_name' => [
        'exists' => '\Drupal\wechat_connect\Entity\WechatApplication::load',
      ],
      '#disabled' => !$wechat_application->isNew(),
    ];

    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#maxlength' => 255,
      '#default_value' => $wechat_application->getSecret(),
      '#description' => $this->t("Secret of the Wechat application."),
      '#required' => TRUE,
    ];


    $plugins = array_column($this->pluginManager->getDefinitions(), 'label', 'id');
    asort($plugins);

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Wechat application type'),
      '#options' => $plugins,
      '#default_value' => $wechat_application->getType(),
      '#required' => TRUE,
      '#disabled' => !$wechat_application->isNew()
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $wechat_application = $this->entity;
    $status = $wechat_application->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Wechat application.', [
          '%label' => $wechat_application->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Wechat application.', [
          '%label' => $wechat_application->label(),
        ]));
    }
    $form_state->setRedirectUrl($wechat_application->toUrl('collection'));
  }

}
