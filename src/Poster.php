<?php
// +----------------------------------------------------------------------
// | zibi [ WE CAN DO IT MORE SIMPLE]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2020 http://xmzibi.com/ All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: MrYe    <email：55585190@qq.com>
// +----------------------------------------------------------------------

namespace og\poster;

use og\error\ToolException;
use og\helper\Str;

class Poster
{
    /**
     * 上传根目录地址
     * @var string
     */
    protected $rootPath;

    /**
     * 海报文件路径
     * @var string
     */
    protected $filename;

    /**
     * 图片默认配置
     *
     * @var array
     */
    protected $imgDefaultConfig = [
        'stream'    => 0,
        'left'      => 0,
        'top'       => 0,
        'right'     => 0,
        'bottom'    => 0,
        'width'     => 100,
        'height'    => 100,
        'opacity'   => 100,
    ];

    /**
     * 文字默认配置
     *
     * @var array
     */
    protected $textDefaultConfig = [
        'text'      => '',
        'left'      => 0,
        'top'       => 0,
        'fontSize'  => 32,       //字号
        'fontColor' => '255,255,255', //字体颜色
        'fontPath'  => '',
        'angle'     => 0,
    ];


    public function __construct($rootPath = '')
    {
        if(!is_dir($rootPath)) {
            //获取上传默认路径
            $default = env('root_path').'attachment/{type}/{uniacid}/{module_name}/';
            $rootPath = config('app.upload.root_path', $default);
        }

        $this->setRootPath($rootPath);
    }

    /**
     * 设置根路径
     * @param $rootPath
     * @return $this
     */
    public function setRootPath($rootPath)
    {
        $this->rootPath = Str::endsWith($rootPath, '/') ? $rootPath : $rootPath.'/';

        return $this;
    }

    /**
     * 生成海报
     * @param $background
     * @param array $config
     * @param string $fileName
     * @return $this
     * @throws ToolException
     */
    public function createPoster($background, $config = [], $fileName = '')
    {
        //设置海报背景图片
        $backgroundPath = $this->downloadImage($background);
        if (empty($backgroundPath)) {
            //背景路径错误
            throw new ToolException('Background image path error');
        }
        $backgroundInfo = $this->getImgInfo($background);
        if (empty($backgroundInfo)) {
            //获取不到图片信息
            throw new ToolException('Image formatting error or Imagick extension not enabled');
        }
        //资源
        $backgroundFun = 'imagecreatefrom' . image_type_to_extension($backgroundInfo[2], false);
        $backgroundSource = call_user_func($backgroundFun, $backgroundPath);
        //背景宽度
        $backgroundWidth = imagesx($backgroundSource);
        //背景高度
        $backgroundHeight = imagesy($backgroundSource);
        $imageRes = imageCreatetruecolor($backgroundWidth, $backgroundHeight);
        $color = imagecolorallocate($imageRes, 0, 0, 0);
        imagefill($imageRes, 0, 0, $color);
        imagecopyresampled($imageRes, $backgroundSource, 0, 0, 0, 0, imagesx($backgroundSource), imagesy($backgroundSource), imagesx($backgroundSource), imagesy($backgroundSource));

        if(is_array($config['image']) && !empty($config['image'])) {
            //有图片资源

            foreach ($config['image'] as $key => $image)
            {
                //合并
                $image = array_merge($this->imgDefaultConfig, $image);
                if (empty($image['width']) || empty($image['height']))
                {
                    //没有宽和高
                    continue;
                }

                //图片本地化
                $imgPath = $this->downloadImage($image['url']);
                if (empty($imgPath))
                {
                    //图片资源不存在
                    continue;
                }
                //获取图片信息
                $imageInfo = $this->getImgInfo($imgPath);
                if(empty($imageInfo))
                {
                    //无法获取到图片信息
                    continue;
                }
                $imgext = image_type_to_extension($imageInfo[2], false);
                $function = !empty($imgext) ? 'imagecreatefrom' . $imgext : 'imagecreatefrompng';
                if ($image['stream']) {
                    //如果传的是字符串图像流
                    $imageInfo = getimagesizefromstring($imgPath);
                    $function = 'imagecreatefromstring';

                }

                $imageSource = call_user_func($function, $imgPath);
                $resWidth = $imageInfo[0];
                $resHeight = $imageInfo[1];
                //建立画板 ，缩放图片至指定尺寸
                $canvas = imagecreatetruecolor($image['width'], $image['height']);
                imagefill($canvas, 0, 0, $color);
                //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
                imagecopyresampled($canvas, $imageSource, 0, 0, 0, 0, $image['width'], $image['height'], $resWidth, $resHeight);
                $image['left'] = $image['left'] < 0 ? $backgroundWidth - abs($image['left']) - $image['width'] : $image['left'];
                $image['top'] = $image['top'] < 0 ? $backgroundHeight - abs($image['top']) - $image['height'] : $image['top'];
                //放置图像
                imagecopymerge($imageRes, $canvas, $image['left'], $image['top'], $image['right'], $image['bottom'], $image['width'], $image['height'], $image['opacity']);//左，上，右，下，宽度，高度，透明度
                if (!empty($image['is_unlink']))
                {
                    //删除临时文件
                    self::unlink($imgPath);
                }
            }

        }

        if (!empty($config['text']) && is_array($config['text'])) {
            //处理海报文字
            foreach ($config['text'] as $key => $val)
            {
                //合并
                $val = array_merge($this->textDefaultConfig, $val);

                $fontPath = is_file($val['fontPath']) ? $val['fontPath'] : dirname(dirname(dirname(dirname(__DIR__)))).'/imagefont/source/font.ttc';
                if (!is_file($fontPath)) {
                    //字体文件不存在
                    throw new ToolException('fontFile path error');
                }
                $val['fontColor'] = $this->hex2rgba($val['fontColor'], true, true);
                list($R, $G, $B) = $val['fontColor'];
                $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
                $val['left'] = $val['left'] < 0 ? $backgroundWidth - abs($val['left']) : $val['left'];
                $val['top'] = $val['top'] < 0 ? $backgroundHeight - abs($val['top']) : $val['top'];
                imagettftext($imageRes, $val['fontSize'], $val['angle'], $val['left'], $val['top'], $fontColor, $fontPath, $val['text']);
            }

        }

        if(empty($fileName)) {
            //自动获取上传文件名称
            $fileName = md5((string) microtime(true)).'.jpg';

        } elseif(strpos($fileName, '.') === false) {
            //没有后缀，需要拼接
            $fileName = $fileName.'.jpg';
        }

        $directory = $this->rootPath;
        $pathReplace = $this->getPathReplace();
        //替换
        $directory = str_replace(array_keys($pathReplace), array_values($pathReplace), $directory);
        og_mkdirs($directory);
        $this->filename = $directory.$fileName;

        //保存到本地
        imagejpeg($imageRes, $this->filename, 90);
        imagedestroy($imageRes);
        
        return $this;

    }

    /**
     * 获取文件名称
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * 获取访问路径
     * @return string
     */
    public function getSrcname()
    {
        $pathName = $this->getFilename();
        if(empty($pathName)) {
            return false;
        }
        //解析path
        $pathArr = explode(env('root_path'), $pathName);
        list(, $srcName) = $pathArr;

        return '/'.$srcName;
    }

    /**
     * 图片本地化
     * @param $url
     * @param string $rootPath
     * @param string $fileName
     * @return bool
     */
    public function downloadImage($url, $fileName = '')
    {

        if (strpos($url, env('root_path')) !== false) {

            //本地绝对路径文件
            return is_file($url) ? $url : false;

        } elseif (!preg_match('/^http(s)?:\/\/.+/', $url)) {

            //本地相对路径文件
            return env('root_path').$url;

        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        $file = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        //获取文件后缀
        $ext = 'png';
        if (!empty($info)) {

            //获取到文件info
            $contentType = $info['content_type'];
            $contentTypeArr = explode('/', $contentType);
            $ext = isset($contentTypeArr[1]) ? $contentTypeArr[1] : $ext;
        }
        $imagedata = base64_encode($file);
        $size = getimagesize('data://image/jpeg;base64,'. $imagedata);
        if (empty($size[0]) || empty($size[1])) {

            //不是图片
            throw new ToolException('Not a picture resource:'.$url);
        }

        if(empty($fileName)) {
            //自动获取上传文件名称
            $fileName = md5($file).'.'.$ext;

        } elseif(strpos($fileName, '.') === false) {
            //没有后缀，需要拼接
            $fileName = $fileName.'.'.$ext;
        }

        $directory = $this->rootPath;
        $pathReplace = $this->getPathReplace();
        //替换
        $directory = str_replace(array_keys($pathReplace), array_values($pathReplace), $directory);
        og_mkdirs($directory);
        $filename = $directory.'temp/'.$fileName;
        //开始保存文件
        $resource = fopen($filename, 'a');
        fwrite($resource, $file);
        fclose($resource);

        return $filename;
    }


    /**
     *  获取图片信息
     * @param $filePath
     * @return array|bool|false
     */
    protected function getImgInfo($filePath)
    {
        $imageSize = getimagesize($filePath);

        return !empty($imageSize) ? $imageSize : false;
    }

    /**
     * 删除文件
     * @param $filePath
     * @return bool
     */
    protected static function unlink($filePath)
    {
        return @unlink($filePath);
    }

    /**
     *  处理文字颜色
     * @param $color
     * @param bool $opacity
     * @param bool $raw
     * @return array|string
     */
    protected function hex2rgba($color, $opacity = false, $raw = false)
    {
        $default = 'rgb(0,0,0)';
        //Return default if no color provided
        if (empty($color))
            return $default;
        //Sanitize $color if "#" is provided
        if ($color[0] == '#') {
            $color = substr($color, 1);
        }
        //Check if color has 6 or 3 characters and get values
        if (strlen($color) == 6) {
            $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
        } elseif (strlen($color) == 3) {
            $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        } else {
            return $default;
        }

        //Convert hexadec to rgb
        $rgb = array_map('hexdec', $hex);

        if ($raw) {
            if ($opacity) {
                if (abs($opacity) > 1) $opacity = 1.0;
                array_push($rgb, $opacity);
            }
            $output = $rgb;
        } else {
            //Check if opacity is set(rgba or rgb)
            if ($opacity) {
                if (abs($opacity) > 1)
                    $opacity = 1.0;
                $output = 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
            } else {
                $output = 'rgb(' . implode(",", $rgb) . ')';
            }
        }

        //Return rgb(a) color string
        return $output;
    }

    /**
     * 获取目录替换
     * @param $name
     * @return array
     */
    protected function getPathReplace()
    {

        return [
            '{type}'        => 'poster',
            '{time}'        => time(),
            '{md5_time}'    => md5(time()),
            '{date}'        => date('Y-m-d', time()),
            '{uniacid}'     => W('uniacid'),
            '{module_name}' => env('module_name'),
        ];
    }
}
