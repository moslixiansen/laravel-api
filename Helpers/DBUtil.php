<?php

namespace Vita\Helpers;

use Log;
use Exception;
use App\Helpers\Util;
use Illuminate\Support\Facades\DB;

class DBUtil
{
    /**
     * 返回刚刚执行的一条SQL语句
     * @param string $type 默认值是str，    值为 str 的时候返回SQL完整语句，  值非 str 的时候返回query数组，
     *                                      其中包含查询字符串，和绑定数值的数组
     * @param bool $withEagerLoading false的时候只返回最后一条，true的时候返回所有的历史记录
     * @return mixed|string
     */
    public static function getLastSQL($type = 'str', $withEagerLoading = false)
    {
        $queries = DB::getQueryLog();
        $ret = [' -------- last queries --------'];
        if ($withEagerLoading) {
            foreach ($queries as $query) {
                if ($type == 'str') {
                    $queryString = DBUtil::bindDataToQuery($query);
                    $ret[] = $queryString;
                } else {
                    $ret[] = $query;
                }
            }
        } else {
            $last_query = end($queries);
            if ($type == 'str') {
                $last_query = DBUtil::bindDataToQuery($last_query);
            }
            $ret[] = $last_query;
        }
        $ret = implode("\n\t\t", $ret);
        $ret = "\n" . $ret;
        return $ret;
    }

    /**
     * @param $queryItem
     * @return string
     */
    protected static function bindDataToQuery($queryItem)
    {
        $query = $queryItem['query'];
        $bindings = $queryItem['bindings'];
        $arr = explode('?', $query);
        $res = '';
        foreach ($arr as $idx => $ele) {
            if ($idx < count($arr) - 1) {
                $res = $res . $ele . "'" . $bindings[$idx] . "'";
            }
        }
        $res = $res . $arr[count($arr) - 1];
        return $res;
    }

    /**
     * 检查数组里面的值列表是否在表的某个字段中都存在
     *      $throwException = true 的时候会抛出异常，$exceptionMessage 为 异常消息
     * */
    public static function checkFieldExistInTable($valueArray, $tableName, $fieldName = 'gid', $throwException = false, $exceptionMessage = '')
    {
        $res = DB::table($tableName)->select($fieldName)->whereIn($fieldName, $valueArray)->get();
        $res = Util::objToArray($res);
        $res = array_pluck($res, $fieldName);
        $ret = Util::array_compare_by_value($valueArray, $res);
        if (!$ret && $throwException) {
            throw(new Exception($exceptionMessage));
        }
        return $ret;
    }
}
