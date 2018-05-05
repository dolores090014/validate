<?php
/**
 * Created by PhpStorm.
 * User: k
 * Author: tanze
 * Date: 2018/5/2
 * Time: 16:24
 */

namespace lib\validate;

class validate {
    const DEFAULT_VALUE = "default-value";
    const SOURCE_GET = 1 << 0;
    const SOURCE_POST = 1 << 1;
    const SOURCE_REQUEST = self::SOURCE_GET | self::SOURCE_POST;
    const SOURCE_PUT_AND_DELETE = 1 << 3; //parse the http body
    const SOURCE_JSON = 1 << 4; //decode the http body if it is a json
    const SOURCE_HEADER = 1 << 5;
    const SOURCE_SESSION = 1 << 6;

    private $_paramName = false;
    private $_paramValue = "";
    private $_failed;

    public $subName;
    public $err;

    public function __construct($name = "") {
        $this->_name($name);
    }

    //****************************************** export **********************************************

    /** 通过返回数据，不通过返回false，调用error方法可以输出错误信息
     * @return bool|string
     */
    public function value() {
        if (isset($this->err) && !empty($this->err)) {
            return false;
        }
        if ($this->_paramValue === self::DEFAULT_VALUE) {
            $this->_error("data not found and default value not set");
            return false;
        }
        return $this->_paramValue;
    }

    /*
     *
     */
    public function failed() {
        return $this->_failed;
    }

    /**返回错误信息
     * @return mixed
     */
    public function error() {
        return $this->err;
    }

    /**设置参数别名
     * @param $param
     * @return $this
     */
    public function subName($param) {
        if (!isset($this->subName) && !empty($param) && is_string($param)) {
            $this->subName = $param;
        }
        return $this;
    }

    /**
     * @param int $source （可选，常量枚举，如果使用自定义的值则等于直接赋值）
     * @param string $default 默认值
     * @return $this
     */
    public function source($source = self::SOURCE_REQUEST, $default = self::DEFAULT_VALUE) {
        $this->_getParam($source);
        if ($this->_paramValue === self::DEFAULT_VALUE && $this->_paramValue !== $default) {
            $this->_paramValue = $default;
        }
        return $this;
    }

    /**
     * @param $fun
     * @param string $err 匿名或字符串
     * @return $this
     */
    public function func($fun, $err = "") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        if ($fun($this->_paramValue) === false) {
            $this->_error($err);
        }
        return $this;
    }

    /** 传入一个匿名函数
     * @param $fun
     * @return $this
     */
    public function callBack($fun) {
        if (is_callable($fun)) {
            $fun($this->_paramValue, $this);
        }
        return $this;
    }


    //*************************************************** private **************************************************
    private function _panic($exp) {
        throw new \Exception("=>{$exp}<=");
    }

    private function _error($err) {
        $this->_failed = true;
        if (is_callable($err)) {
            $err($this->error());
        } else if (!isset($this->err) && !is_string($err) && !empty($err)) {
            $this->_panic("err must be function or string which not empty");
        } else {
            $this->err = [($this->subName ? $this->subName : $this->_paramName) => $err];
        }
    }

    private function _name($param) {
        if (isset($param) && !empty($param) && is_string($param)) {
            $this->_paramName = $param;
        } else {
            $this->_panic("param's name must be string and not empty");
        }
        return $this;
    }

    private function _getParam($source) {
        if (($source | self::SOURCE_GET) == $source) {
            if (isset($_GET[$this->_paramName]) && !empty($_GET[$this->_paramName])) {
                $this->_paramValue = $_GET[$this->_paramName];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (($source | self::SOURCE_POST) == $source) {
            if (isset($_POST[$this->_paramName]) && !empty($_POST[$this->_paramName])) {
                $this->_paramValue = $_POST[$this->_paramName];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (($source | self::SOURCE_HEADER) == $source) {
            $k = "HTTP" . strtoupper($this->_paramName);
            if (isset($_SERVER[$k]) && !empty($_SERVER[$k])) {
                $this->_paramValue = $_SERVER[$k];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (($source | self::SOURCE_SESSION) == $source) {
            if (isset($_SESSION[$this->_paramName]) && !empty($_SESSION[$this->_paramName])) {
                $this->_paramValue = $_SESSION[$this->_paramName];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (($source | self::SOURCE_PUT_AND_DELETE) == $source) {
            @parse_str(file_get_contents('php://input'), $p);
            if (isset($p) && empty($p) && isset($p[$this->_paramName]) && !empty($p[$this->_paramName])) {
                $this->_paramValue = $p[$this->_paramName];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (($source | self::SOURCE_JSON) == $source) {
            $jsonStr = @file_get_contents('php://input');
            $this->_paramValue = self::DEFAULT_VALUE;
            $json = json_decode($jsonStr, true);
            if (isset($json) && $jsonStr != $json && isset($json[$this->_paramName]) && !empty($json[$this->_paramName])) {
                $this->_paramValue = $json[$this->_paramName];
                return $this;
            } else {
                $this->_paramValue = self::DEFAULT_VALUE;
            }
        }
        if (empty($this->_paramValue)) {
            $this->_paramValue = $source;
        }
        return $this;
    }


    //*******************************************  预定义方法  ****************************************************

    /*
     * not empty
     */
    public function notEmpty($err = "is empty") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        if (empty($this->_paramValue) && $err !== 0) {
            $this->_error($err);
        }
        return $this;
    }

    /**必须且非空
     * @param $p
     * @param string $err
     * @return $this
     */
    public function required($err = "not found") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        if (!isset($this->_paramValue) || empty($this->_paramValue) || $this->_paramValue === self::DEFAULT_VALUE) {
            $this->_error($err);
        }
        return $this;
    }

    public function bool() {

        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }
        $this->_paramValue = (boolean)$this->_paramValue;
        return $this;
    }

    /**会强制转化
     * @param $p
     * @param int $min
     * @param int $max
     * @param string $err
     * @return $this
     */
    public function number($min = 0, $max = 0, $err = "number out of range") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }
        $this->_paramValue = (int)$this->_paramValue;

        if ($max != 0 || $min != 0) {
            if ($min >= $max) {
                $this->_panic("min must less than max");
            }
            if ($this->_paramValue < $min || $this->_paramValue > $max) {
                $this->_error($err);
            }
        }

        return $this;
    }

    public function len($min = 0, $max = 0, $err = "string out of range") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        if ($max >= $min) {
            $this->_panic("min must less than max");
        }
        $this->_paramValue = (string)$this->_paramValue;
        if (preg_match("/[\u4e00-\u9fa5]/", $this->_paramValue)) {
            if (mb_strlen($this->_paramValue) < $min || mb_strlen($this->_paramValue) > $max) {
                $this->_error($err);
            }
        } else {
            if (strlen($this->_paramValue) < $min || strlen($this->_paramValue) > $max) {
                $this->_error($err);
            }
        }
        return $this;
    }

    /** 会强制转化
     * @param $this ->_paramValue
     * @param string $err
     * @return $this
     */
    public function string($err = "not a string") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        $this->_paramValue = (string)$this->_paramValue;
        if (empty($this->_paramValue)) {
            $this->_error($err);
        }
        return $this;
    }

    //todo
    public function float($rate, $err = "") {

    }

    public function email($err = "not a mail") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        $re = preg_match("/[\w!#$%&'*+/=?^_`{|}~-]+(?:\.[\w!#$%&'*+/=?^_`{|}~-]+)*@(?:[\w](?:[\w-]*[\w])?\.)+[\w](?:[\w-]*[\w])?/", $this->_paramValue);
        if ($re) {
            $this->_error($err);
        }
        return $this;
    }

    public function phone($err = "not a phone") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        $this->_paramValue = (int)$this->_paramValue;
        if (preg_match("/^1([35478][0-9])\d{8}$/", $this->_paramValue)) {
            $this->_error($err);
        }
        return $this;
    }

    /**枚举
     * @param $arr
     * @param string $err
     * @return $this
     */
    public function enumeration($arr, $err = "not found") {
        if (isset($this->_failed) && $this->_failed === true) {
            return $this;
        }

        if (!is_array($arr) || empty($arr)) {
            $this->_panic("the range must be array and not empty");
        }
        if (!in_array($this->_paramValue, $arr)) {
            $this->_error($err);
        }
        return $this;
    }
}