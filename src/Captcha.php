<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace xuezhitech\captcha;

use think\Config;
use think\facade\Cache;
use think\Response;

class Captcha
{
    private $im    = null; // 验证码图片实例
    private $color = null; // 验证码字体颜色
    protected $config = [ //配置文件
        // 验证码字符集合
        'codeSet' => '123456789',
        // 验证码过期时间（s）
        'expire' => 1800,
        // 验证码字体大小(px)
        'fontSize' => 25,
        // 是否画混淆曲线
        'useCurve' => true,
        // 是否添加杂点
        'useNoise' => true,
        // 验证码图片高度
        'imageH' => 0,
        // 验证码图片宽度
        'imageW' => 0,
        // 验证码位数
        'length' => 4,
        // 验证码字体，不设置随机获取
        'fontttf' => '',
        // 背景颜色
        'bg' => [243, 251, 254],
        // 是否使用背景
        'useImgBg'=> true,
    ];

    /**
     * 架构方法 设置参数
     * @access public
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 使用 $this->name 获取配置
     * @access public
     * @param  string $name 配置名称
     * @return mixed    配置值
     */
    public function __get(string $name){
        return $this->config[$name];
    }

    /**
     * 设置验证码配置
     * @access public
     * @param  string $name  配置名称
     * @param  string $value 配置值
     * @return void
     */
    public function __set(string $name, string $value){
        if (isset($this->config[$name])) {
            $this->config[$name] = $value;
        }
    }

    /**
     * 检查配置
     * @access public
     * @param  string $name 配置名称
     * @return bool
     */
    public function __isset(string $name){
        return isset($this->config[$name]);
    }

    /**
     * 验证验证码是否正确
     * @access public
     * @param string $key key
     * @param string $code code
     * @return bool 用户验证码是否正确
     */
    public function check(string $key,string $code): bool
    {
        // 验证码不能为空
        if ( empty($key) || empty($code) ){
            return false;
        }else{
            $captcha_info = Cache::get($key);
            if ( empty($captcha_info)) {
                return false;
            }
        }
        // session 过期
        if ( (time() - $captcha_info['verify_time']) > $this->expire ) {
            Cache::delete($key);
            return false;
        }
        // code是否相等
        if ( $code==$captcha_info['verify_code'] ) {
            Cache::delete($key);
            return true;
        }

        return false;
    }

    /**
     * 输出验证码并把验证码的值保存的session中
     * @access public
     * @param null|string $config
     * @param bool        $api
     * @return Response
     */
    public function create(bool $debug=false): array
    {
        //创建key及验证码
        $generator = $this->generate();

        // 图片宽(px)
        $this->imageW || $this->imageW = $this->length * $this->fontSize * 1.5 + $this->length * $this->fontSize / 2;
        // 图片高(px)
        $this->imageH || $this->imageH = $this->fontSize * 2.5;

        $this->imageW = intval($this->imageW);
        $this->imageH = intval($this->imageH);

        // 建立一幅 $this->imageW x $this->imageH 的图像
        $this->im = imagecreate((int) $this->imageW, (int) $this->imageH);
        // 设置背景
        imagecolorallocate($this->im, $this->bg[0], $this->bg[1], $this->bg[2]);

        // 验证码字体随机颜色
        $this->color = imagecolorallocate($this->im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/../assets/ttfs/';

        if (empty($this->fontttf)) {
            $dir  = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if (substr($file, -4) == '.ttf' || substr($file, -4) == '.otf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $this->fontttf = $ttfs[array_rand($ttfs)];
        }

        $fontttf = $ttfPath . $this->fontttf;

        if ($this->useImgBg) {
            $this->background();
        }

        if ($this->useNoise) {
            // 绘杂点
            $this->writeNoise();
        }
        if ($this->useCurve) {
            // 绘干扰线
            $this->writeCurve();
        }

        // 绘验证码
        $text = str_split($generator['verify']['verify_code']); // 验证码

        foreach ($text as $index => $char) {

            $x     = $this->fontSize * ($index + 1) * 1.5;
            $y     = $this->fontSize + mt_rand(10, 20);
            $angle = mt_rand(-40, 40);

            imagettftext($this->im, intval($this->fontSize), intval($this->fontSize), intval($x), intval($y), $this->color, $fontttf, $char);
        }

        ob_start();
        // 输出图像
        imagepng($this->im);
        $content = ob_get_clean();
        imagedestroy($this->im);
        $content = 'data:image/png;base64,'.base64_encode($content);
        if ( $debug===true ){
            return [
                'captcha_key'=>$generator['key'],
                'src'=>$content,
                'verify_code'=>$generator['verify']['verify_code'],
                'verify_time'=>$generator['verify']['verify_time']
            ];
        }else{
            return ['captcha_key'=>$generator['key'],'src'=>$content];
        }

        return response($content, 200, ['Content-Length' => strlen($content)])->contentType('image/png');
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     *      高中的数学公式咋都忘了涅，写出来
     *        正弦型函数解析式：y=Asin(ωx+φ)+b
     *      各常数值对函数图像的影响：
     *        A：决定峰值（即纵向拉伸压缩的倍数）
     *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *        ω：决定周期（最小正周期T=2π/∣ω∣）
     *
     */
    protected function writeCurve(): void
    {
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, $this->imageH / 2); // 振幅
        $b = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // Y轴方向偏移量
        $f = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // X轴方向偏移量
        $T = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand($this->imageW / 2, $this->imageW * 0.8); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $this->color); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A   = mt_rand(1, $this->imageH / 2); // 振幅
        $f   = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // X轴方向偏移量
        $T   = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $this->imageH / 2;
        $px1 = $px2;
        $px2 = $this->imageW;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $this->color);
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    protected function writeNoise(): void
    {
        $codeSet = 'abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($this->im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($this->im, 5, mt_rand(-10, $this->imageW), mt_rand(-10, $this->imageH), $codeSet[mt_rand(0,22)], $noiseColor);
            }
        }
    }

    /**
     * 绘制背景图片
     * 注：如果验证码输出图片比较大，将占用比较多的系统资源
     */
    protected function background(): void
    {
        $path = __DIR__ . '/../assets/bgs/';
        $dir  = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        $gb = $bgs[array_rand($bgs)];

        list($width, $height) = @getimagesize($gb);
        // Resample
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($this->im, $bgImage, 0, 0, 0, 0, $this->imageW, $this->imageH, $width, $height);
        @imagedestroy($bgImage);
    }

    /**
     * 创建验证码
     * @return array
     * @throws Exception
     */
    protected function generate(): array
    {
        $key  = md5(uniqid()); //生成key
        $verify_code = ''; //生成codes
        //随机生成code
        $characters = str_split($this->codeSet);
        for ($i = 0; $i < $this->length; $i++) {
            $verify_code .= $characters[rand(0, count($characters) - 1)];
        }
        $verify_code = mb_strtolower($verify_code, 'UTF-8');
        //生成verify信息
        $verify = [
            'verify_code'=>$verify_code, //生成code
            'verify_time'=>time(), //生成code
        ];
        //保存到cache中
        Cache::set($key,$verify,$this->expire);

        return [
            'verify' => $verify,
            'key'   => $key,
        ];
    }
}
