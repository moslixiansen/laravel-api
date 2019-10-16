<?php

namespace App\Helpers;

use App\Models\SysConfigModel;
use App\Helpers\SystemException;

class SerialNumberUtils
{

    // 流水号的长度
    const SERIAL_LENGTH = 8;

    public static function generateSerialNumber($type, $increment = 1, $prefix = true)
    {
        $increment = $increment < 1 ? 1 : $increment;
        $type = strtoupper(trim($type));
        $cfg = SysConfigModel::lockForUpdate()->where(['e_name' => $type])->first();
        if (!$cfg) {
            throw new SystemException("没有指定的生成单号的方法");
        }
        $current = $cfg->value;
        $cfg->increment('value', $increment);
        $next = $current + $increment;
        if (!$prefix) {
            $type = '';
        }
        if ($increment > 1) {
            for ($i = $current + 1 ; $i <= $next; $i++) {
                $sn[] = self::generateSerial($type, $i);
            }
        } else {
            $sn = self::generateSerial($type, $next);
        }
        return $sn;
    }

    protected static function generateSerial($type, $number)
    {
        return $type . str_pad($number, self::SERIAL_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function __callStatic($method, $args)
    {
        $argc = count($args);
        $increment = $argc > 0 ? $args[0] : 1;
        $prefix = $argc > 1 ? $args[1] : true;
        $method = strtoupper($method) == 'SDO' ? 'DO' : $method;
        $arguments = [$method, $increment, $prefix];
        return call_user_func_array([__CLASS__, 'generateSerialNumber'], $arguments);
    }
}
