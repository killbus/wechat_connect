<?php

namespace Drupal\wechat_connect\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Wechat application type plugins.
 */
interface WechatApplicationTypeInterface extends PluginInspectionInterface {

  /**
   * 连接微信
   *
   * 首次连接：
   * 1、前端获取到 auth_code 后，小程序调用 code2session 接口，
   * 网站/APP/公众号网页应用调用获取 access_token 接口。小程序会拿到 session_key，
   * 网站/APP/公众号网页应用会拿到 access_token。同时二者都会拿到 openid。
   * 2、创建 WechatUser entity，把 openid， access_token 或 session_key 保存到 WechatUser。
   *
   * 二次连接：
   * 1、同上
   * 2、查到已有的 WechatUser entity，把 access_token 或 session_key 更新到 WechatUser。
   *
   *
   * 如果连接的用户已经注册过，返回 Drupal Oauth2 code，前端可以用它换取 Drupal access_token。
   *
   * @param $drupal_oauth2_client_id string Drupal Oauth2 client_id
   * @param $wechat_app_id string WechatApplication entity id
   * @param $code string 微信 oauth2 code
   * @return mixed 如果连接的用户已经注册过，返回 Drupal Oauth2 code，前端可以用它换取 Drupal access_token。
   */
  public function connect($drupal_oauth2_client_id, $wechat_app_id, $code);

  /**
   * 注册用户
   *
   * 1、获取微信用户的个人信息。小程序由前端传入加密的数据，用 session_key 解密。
   * 网站/APP/公众号网页应用以 access_token 调用 user_info 服务端接口获取。
   *
   * 2、创建Drupal user，保存用户个人信息。
   * 如果应用存在 unionid，会在上一步中同时返回，这时需要用 unionid 去查是否已经存在对应的 Drupal user，
   * 如果已存在，则直接使用同一个 Drupal user，不必再重新创建。
   *
   * 3、前端可以传入额外的用户信息数据，此方法应该把它们正确保存到相应的 Drupal user.
   *
   * @param $drupal_oauth2_client_id string  Drupal Oauth2 client_id
   * @param $connect_id int  wechat_user id
   * @param $phone  string     绑定到drupal用户的手机号码
   * @param $extend_data array 额外数据
   * @return mixed 注册成功后，返回 Drupal Oauth2 code，前端可以用它换取 Drupal access_token。
   */
  public function register($drupal_oauth2_client_id, $connect_id, $phone, $extend_data = []);
}
