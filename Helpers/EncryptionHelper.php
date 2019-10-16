<?php

namespace App\Helpers;

use Illuminate\Hashing\BcryptHasher;

class EncryptionHelper
{


    /**
     * 加密设备身份，使用设备的mac 和deviceId 生成appKey 和password
     * @param $mac [string]        [mac地址，必须唯一]
     * @param $deviceId [string]        [设备id，必须唯一]
     * @return array  [$app_key: 生成的appKey, $passwordToInsert： 要插入数据库的加密后密码, $password： 明文密码]
     */
    public static function encryptDeviceIdentity($mac, $deviceId, $appKey = null)
    {
        if (!$appKey) {
            $appKey = Util::randStr(10);
        }
        $password = sha1(sha1($deviceId . $mac) . $appKey);     // 密码加密方式
        $bcryptHasher = new BcryptHasher();
        $passwordToInsert = $bcryptHasher->make($password);

        return [$appKey, $passwordToInsert, $password];
    }

    public static function encryptJwtPassword($mac, $deviceId, $appKey=null)
    {
        if (!$appKey) {
            $appKey =  Util::randStr(10);
        }

        return sha1(sha1($deviceId.$mac).$appKey);     // 密码加密方式
    }

    /**
     * 加密用户密码，返回密码和salt
     * @param $name [string]        用户编号
     * @param $password [string]        md5一次后的密码
     * @return array [  加密后的password, salt 数组  ]
     */
    public static function encryptUserPassword($name, $password)
    {
        $salt = EncryptionHelper::makeSalt();
        return EncryptionHelper::encryptPasswordWithSalt($name, $password, $salt);
    }

    public static function encryptPasswordWithSalt($name, $password, $salt)
    {
        $password = md5($name . $salt . $password);
        return [$password, $salt];
    }

    public static function encryptClientCard($password)
    {
        $salt = self::makeSalt();
        $password = md5($password . $salt);
        return [$password, $salt];
    }

    public static function encryptClientCardWithSalt($password, $salt)
    {
        $password = md5($password . $salt);
        return [$password, $salt];
    }

    /**
     * 检验用户密码的合法性
     * @param $name         string      用户名
     * @param $password     string      md5一次以后的密码
     * @param $salt
     * @return bool
     */
    public static function checkUserPassword($name, $password, $salt)
    {
        $encryptedPassword = EncryptionHelper::encryptPasswordWithSalt($name, $password, $salt);
        return $encryptedPassword == $password;
    }

    /**
     * 制造盐
     * @return string
     */
    public static function makeSalt()
    {
        $chars = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
        shuffle($chars);
        return implode(array_slice($chars, 0, 8));
    }
}
