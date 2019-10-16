<?php

namespace Vita\Helpers;

class StringHelper
{
    /**
     * 把下划线分隔的字符串转换成首字母大写的驼峰命名法
     * some_table_name  =>   SomeTableName
     * @param $val
     * @return string
     */
    public static function convertUnderscoreToUcFirst($val)
    {
        $arr = explode('_', $val);
        foreach ($arr as $idx => $ele) {
            $arr[$idx] = ucfirst($ele);
        }
        $res = implode('', $arr);
        return $res;
    }

    /**
     * 把下划线分隔的字符串转换成首字母大写的驼峰命名法
     * SomeTableName  =>  some_table_name
     * @param $val
     * @return array|string
     */
    public static function convertUcFirstToUnderscore($val)
    {
        $sLen = mb_strlen($val);
        $part = '';
        $res = [];
        for ($i = 0; $i < $sLen; $i++) {
            $char = substr($val, $i, 1);
            if (strtolower($char) == $char) {
                $part = $part . strtolower($char);
            } else {
                $res[] = $part;
                $part = strtolower($char);
            }
        }
        $res[] = $part;
        $res = implode('_', $res);
//        echo '  underscore seperated value = ',print_r($res,true),"\n";
        return $res;
    }
}
