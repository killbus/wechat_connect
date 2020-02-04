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


## 微信登录
登录，本质上是取得当前用户的唯一标识以及相关的用户数据。

微信登录，则是取得当前用户的 `openid` 以及 `unionid`，并获取其基本个人信息。

### 小程序登录要点

在微信小程序中，`openid` 和 `unionid` 是通过 `wx.login -> code2session` 前后端两个接口获取的。

用户基本个人信息，则是使用 `open-type: getUserInfo` 按钮弹窗询问用户获取，并传到后台使用 `session-key` 解密。

微信的授权机制，一般的权限可以直接弹窗要求用户授权，但是 scope.userInfo 是个例外。
获取用户信息(scope.userInfo)这个权限无法弹出授权窗口，见官方文档：
[微信的授权机制](https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/authorize.html)
[wx.getUserInfo接口](https://developers.weixin.qq.com/miniprogram/dev/api/open-api/user-info/wx.getUserInfo.html)
[调整原因公告](https://developers.weixin.qq.com/community/develop/doc/0000a26e1aca6012e896a517556c01)

总结是，要弹窗要求用户授权获取用户信息，只能通过 button open-type 的方式，
也就是必须由用户点击按钮，主动发起授权。

事实上，经微信架构调整后，开放数据分为两种，即 `授权` 和 `open-type`。

`授权`，是使用 `wx.authorize` 进行管理的一列表接口权限列表，
每一个权限对应一个或多个微信接口，只有用户授权后，小程序才能成功调用对应的对口。

`open-type`，是使用小程序 `button` 组件进行调用的开放数据类型，
必须由用户主动点击一个页面中显示的按钮，才能发起弹窗请求获取数据或操作。
而上面说的 `授权` 则可以通过代码直接调出弹窗请求数据或操作。

### 微信公众号网页授权登录
微信公众号是网页应用，登录使用的是 `Oauth2.0 auth_code` 规范，所有的流程都是在服务端完成的。

- 第一步：用户同意授权，获取code
- 第二步：通过code换取网页授权access_token
- 第三步：刷新access_token（如果需要）
- 第四步：拉取用户信息(同时获取openid unionid 用户个人信息)

详情查阅[官方文档：微信公众号网页授权](https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html)

### 网站微信扫码登录

网站应用的微信登录本质上也是 `Oauth2.0 auth_code` 规范，与微信公众号的网页授权是一样的，
只有一个小区别，就是第一步获取 `auth_code`，微信公众号是显示一个弹窗，因为它有微信APP的上下文，
所以可以直接由用户点击弹窗确认即可以，但网站没有微信APP上下文，而且如果做成微信帐号密码登录，
体验会很差，所以官方改成在网站上显示一个二维码，用户用手机扫二维码即可完成授权，获得 `auth_code`。

后面的步骤所调用接口地址，和微信公众号也是一致的。

### 原生APP调用微信登录

原生APP接入微信登录，情况与网站应用接入微信登录也是差不多的情况，仅仅是第一步获取 `auth_code`
不同，后面步骤以及调用的接口都是一样的。

原生APP获取 `auth_code` 的方式是，使用微信提供的原生SDK调起微信APP进得用户授权。

### Unionid 要点

PC网站/公众号网页/原生APP，`unionid` 都是与用户个人信息一起返回的，只有小程序是特殊的，
在 code2session 接口中与 `openid` 和 `session-key` 一起返回。

### 服务端的 WechatConnect 模块

无论是小程序/公众号网站/PC网站/原生APP，都可使用 `WechatConnect` 服务端模块来实现微信登录功能。
该模块具体表现为 2 个接口：
- connect 连接
- register 注册


