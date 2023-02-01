# think-captcha

thinkphp6 验证码类库

## 安装
> composer require xuezhitech/tp6-captcha



## 使用

### 在控制器中输出验证码

在控制器的操作方法中使用

声明 
~~~
use xuezhitech/tp6-captcha;
~~~

使用
~~~
public function captcha()
{
	$config = [
        'useCurve'=>false,
        'codeSet'=>'123456789',
    ];
    $captcha = new Captcha($config);
    return $captcha->create();
}
~~~
然后注册对应的路由来输出验证码

### 控制器里验证

验证
~~~
//验证码是否正确
$captcha = new Captcha();
if ( !$captcha->check($key,$code) ){
    return json('CAPTCHA_CODE_ERROR');
}
~~~
