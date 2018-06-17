# wechat_connect

当移动应用或H5 SPA这种客户端以 drupal 8 为服务端时，它们是通过RESTful接口与Drupal通信的。
rest 模块是 Drupal 8的核心模块，提供了实现 RESTful接口的支持，它支持多种认证方式：
- cookies
- http basic
- oauth2.0

在与第三方进行用户整合的情况下，需要使用 oauth2.0认证，前俩种认证方式都不能满足场景要求，
而 rest 的 oauth2.0 认证是由 simple_oauth模块 提供的。

### 支持的微信登录类型

- 网站登录
  
  Drupal 8 原生网站登录，只能配置一个。
  
- 微信公众号登录

  微信公众号内H5 SPA应用登录。H5 SPA客户端发起微信授权，得到Authorization Code，
  通过接口换取 drupal/simple_oauth 的 Authorization Code，从而获取 simple_oauth
  的 access_token。
  
- 移动应用微信登录

  Android\ios原生APP微信登录。原理与微信公众号H5 SPA登录是一样的，
  不同的是在客户端发起登录时，使用的不是Web跳转，而是调起原生微信APP进行授权。
  
 ### 客户端登录过程
 - 发起微信授权，得到 Authorization Code。
 - 调用 wechat/connect 接口，drupal获取 access_token和 open_id，并保存。
   - 如果用户已注册，直接返回 drupal/simple_oauth 的 Authorization Code。
   - 如果没有注册，返回用户信息
 - 如果没有注册，客户端调用 wechat/register 接口进行注册，drupal将创建用户账号，
   并绑定微信用户到此账号。 此接口可以提供一个手机号码，如果已有用户账号使用此手机号，
   那么 drupal将不会创建新的用户，而是直接把微信用户绑定到此账号。
   - 注册成功后，返回 drupal/simple_oauth 的 Authorization Code。
 - 客户端使用simple_oauth 的 Authorization Code 获取 simple_oauth 的 access_token。
 
 
 ### union_id的处理
 如果微信应用开通了union_id，模块会自动处理。
 如果发现拉取的微信用户信息中存在 union_id，把它和 open_id 一同记录到数据库。
 在 wechat/connect 接口中，会同时检查 union_id，如果已经存在，那么说明用户已经注册。
 这时接口会创建 wechat_user 记录，但不会创建新的Drupal user。
  