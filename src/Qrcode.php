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

use og\helper\Str;
use og\error\ToolException;

class Qrcode
{

    /**
     * 文件完整路径
     * @var string
     */
    protected $filename;

    /**
     * 上传根路径
     * @var string
     */
     protected $rootPath;

    /**
     * 二维码容错级别
     * @var string
     */
    protected $level = "L";

    /**
     * 二维码大小
     * @var int
     */
    protected $size = 6;

    /**
     * 二维码边距
     * @var int
     */
    protected $margin = 1;

    /**
     * 是否直接输出二维码
     * @var bool
     */
    protected $saveandprint = false;


    /**
     * 初始化
     * Qrcode constructor.
     * @param string $sdkPath
     * @param string $rootPath
     * @throws ToolException
     */
    public function __construct($sdkPath = '', $rootPath = '')
    {
        $this->setSdkPath($sdkPath);

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
     * 设置skd路径
     * @param $sdkPath
     * @return $this
     */
    public function setSdkPath($sdkPath)
    {
        if($sdkPath) {
            //加载自定义sdk
            if(!is_file($sdkPath)) {
                //sdk文件不存在，抛出异常

                throw new ToolException('qrcode sdk file does not exist:'.$sdkPath);
            }

            include $sdkPath;

        } else {
            //加载微擎内置二维码类库
            load()->library('qrcode');
        }

        if(!class_exists('QRcode')) {
            //类不存在，抛出异常

            throw new ToolException('QRcode class does not exist');
        }

        return $this;
    }

    /**
     * 设置二维码生成的路径
     * @param null $file
     * @return $this
     */
    public function setOutfile($file = null)
    {
        if($file !== null) {
            $this->outfile = $file;
        }

        return $this;
    }

    /**
     * 设置二维码的容错级别
     * @param null $level
     * @return $this
     */
    public function setLevel($level = null)
    {
        if($level !== null) {
            $this->level = $level;
        }

        return $this;
    }

    /**
     * 设置二维码的大小
     * @param int $size
     * @return $this
     */
    public function setSize($size = 0)
    {
        if($size !== 0) {
            $this->size = $size;
        }

        return $this;
    }

    /**
     * 设置二维码的边距
     * @param int $margin
     * @return $this
     */
    public function setMargin($margin = 0)
    {
        if($margin !== 0) {
            $this->margin = $margin;
        }

        return $this;
    }

    /**
     * 是否直接输出二维码
     * @param boolean $saveandprint
     * @return $this
     */
    public function saveandprint($saveandprint = false)
    {
        $this->saveandprint = $saveandprint;
        header("Content-Type:image/png");

        return $this;
    }

    /**
     * 生成二维码
     * @param $value
     * @param string $fileName
     * @return $this
     * @throws ToolException
     */
    public function create($value, $fileName = '')
    {
        if(empty($value)) {
            //二维码值不能为空

            throw new ToolException('The value of generating QR code cannot be empty');
        }

        if(empty($fileName)) {
            //自动获取上传文件名称
            $fileName = md5($value).'.png';

        } elseif(strpos($fileName, '.') === false) {
            //没有后缀，需要拼接
            $fileName = $fileName.'.png';
        }

        $pathReplace = $this->getPathReplace();
        $directory = $this->rootPath;
        //替换
        $directory = str_replace(array_keys($pathReplace), array_values($pathReplace), $directory);
        og_mkdirs($directory);
        //完整路径
        $this->filename = $directory.$fileName;
       \QRcode::png($value, $this->filename, $this->level, $this->size, $this->margin, $this->saveandprint);
       if($this->saveandprint == true) {
           //直接输出图片，并截断
           die();
       }

       if(!is_file($this->filename)) {
           //生成二维码失败时，说明没有权限
           throw new ToolException('qrcode Generation failure!');
       }

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
     * 获取目录替换
     * @param $name
     * @return array
     */
    protected function getPathReplace()
    {

        return [
            '{type}'        => 'qrcodes',
            '{time}'        => time(),
            '{md5_time}'    => md5(time()),
            '{date}'        => date('Y-m-d', time()),
            '{uniacid}'     => W('uniacid'),
            '{module_name}' => env('module_name'),
        ];
    }

}


