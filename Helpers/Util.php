<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Tymon\JWTAuth\JWTAuth;
use App\Models\CategoryModel;
use Illuminate\Support\Facades\DB;
use App\Helpers\ValidationException as VE;
use App\Helpers\StockException as STE;
use App\Helpers\SystemException as SE;
use App\Helpers\ProductException as PE;
use App\Helpers\DeliveryException as DE;
use App\Helpers\PromotionException as PromotionE;
use App\Models\ExceptionLogsModel;
use Tymon\JWTAuth\Exceptions\JWTException;

class Util
{
    public static function objToArray($obj)
    {
        return json_decode(json_encode($obj), true);
    }

    public static function getValidatorErrorMessage($validator, $firstMessageOnly = false)
    {
        $messages = $validator->errors();
        $msgArr = $messages->all();
        $arr = [];
        foreach ($msgArr as $k => $v) {
            $arr[] = $v;
        }
        $ret = implode(",", $arr);

        return $ret;
    }

    /**
     *  获取一个数组中第X个分页的元素，第一页是1，不是0
     *
     */
    public static function array_page($arrSrc, $pageNumber, $pageSize = 0)
    {
        $arrSrc = self::objToArray($arrSrc);
        if ($pageSize == 0) {
            $pageSize = env('PAGE_SIZE');
        }
        $arr = array_chunk($arrSrc, $pageSize, true);
        $pageNumber = intval($pageNumber) - 1;
        if (array_has($arr, $pageNumber)) {
            $res = self::objToArray($arr[$pageNumber]);
            $res = array_values($res);

            return $res;
        } else {
            return [];
        }
    }

    /*
     * 删除数组元素(一个对象)中的换行符(来自于js调用的时候可能会莫名其妙的被加上换行符)
     * */
    public static function delCrlfInObject($arr)
    {
        $ret = [];
        foreach ($arr as $k => $v) {
            $v = str_replace("\n", '', $v);
            $ret[$k] = $v;
        }

        return $ret;
    }

    /**
     * 使用id 和store_id 生成gid
     *
     */
    public static function makeGid($id, $storeId)
    {
        if (intval($id) <= 0 || intval($storeId) <= 0) {
            throw(new Exception('Error occurs while generating gid, id and storeID must be integer'));
        }
        $idStr = str_pad($id, 14, '0', STR_PAD_LEFT);
        $ret = $storeId . $idStr;

        return $ret;
    }


    public static function getFilteredPages(Request $req, $type = 1)
    {
        $pages = $req->input('pages');
        $pageSize = env('PAGE_SIZE');
        if ($pages) {
            $arr = explode(':', $pages);
            switch ($type) {
                case 0:
                    if (count($arr) > 1) {
                        if (intval($arr[1]) > 0) {
                            $pageSize = $arr[1];
                        }
                    }
                    if (intval($arr[0]) > 0) {
                        $page = intval($arr[0]);
                    }
                    $skip = (intval($page) - 1) * $pageSize;
                    $take = $pageSize;

                    return [$skip, $take];
                default:
                    if (count($arr) > 1) {
                        if (intval($arr[1]) > 0) {
                            $pageSize = $arr[1];
                        }
                    }

                    return [$arr[0], $pageSize];
            }
        }

        return [];
    }


    /*
     *  orders: 用来排序的字段列表	id:asc     id:desc;gid:asc
     * @return      返回一个数组，每一行是一个排序规则
     */
    public static function getOrderField(Request $req)
    {
        $orders = $req->input('orderby');
        $sortType = 'desc';
        if ($orders) {
            $arr = explode(':', $orders);
            if (count($arr) > 1) {
                if (in_array($arr[1], ['1', '0'])) {
                    $sortType = $arr[1];
                }
            }
            $res = [$arr[0], $sortType];
        } else {
            $res = [];
        }

        return $res;
    }

    /**
     * @param Request $req
     * @return array
     */
    public static function getOrderFieldData(Request $req)
    {
        $res = [];
        $orders = $req->input('orderby');
        if ($orders) {
            $arr = explode(':', $orders);
            if (count($arr) > 1) {
                $sortTypeMap = ['0' => 'asc', '1' => 'desc'];
                $arr[1] = $sortTypeMap[$arr[1]];
            }
            $res = $arr;
        }

        return $res;
    }


    /**
     * 用于比较两个数组元素的值是否完全相等（注意只比较value不比较key，而且数组会被去重，所以重复元素不是不同）
     */
    public static function array_compare_by_value($arrA, $arrB)
    {
        $arrA = array_unique($arrA);
        $arrB = array_unique($arrB);
        if (count($arrA) != count($arrB)) {
            return false;
        }      // 1. 比较元素数量
        asort($arrA);
        asort($arrB);
        $strA = implode(',', $arrA);
        $strB = implode(',', $arrB);
        if ($strA != $strB) {
            return false;
        }

        return true;
    }


    public static function tree($list, $parent = 0, $key = 'parent')
    {
        $ret = [];
        foreach ($list as $k => $v) {
            if ($v[$key] == $parent) {
                $tmp = $list[$k];
                unset($list[$k]);
                $tmp['children'] = self::tree($list, $v['id'], $key);
                $ret[] = $tmp;
            }
        }

        return $ret;
    }

    public static function treeNode($list, $parent = 0, $key = 'parent', $level = 0, $sn = 0)
    {
        $ret = [];
        $init = $level == 0 ? 99 : 9;
        $level++;
        $i = 1;
        foreach ($list as $k => $v) {
            if ($v[$key] == $parent) {
                $tmp = $list[$k];
                $tmp['lv'] = $level;
                $cl = $sn == 0 ? '' : $sn;
                $tmp['parent_cl'] = $cl;
                $tmp['cl'] = $cl.($i + $init);
                unset($list[$k]);
                $t = self::treeNode($list, $v['id'], $key, $level, (int)$tmp['cl']);
                if (!empty($t)) {
                    $tmp['children'] = $t;
                }
                $ret[] = $tmp;
                $i++;
            }
        }

        return $ret;
    }

    /**
     * 随机产生六位数
     *
     * @param int $len
     * @param string $format
     * @return string
     */
    public static function randStr($len = 6, $format = 'ALL')
    {
        switch ($format) {
            case 'ALL':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
                break;
            case 'CHAR':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-@#~';
                break;
            case 'NUMBER':
                $chars = '0123456789';
                break;
            default:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
                break;
        }
        mt_srand((double)microtime() * 1000000 * getmypid());
        $randStr = "";
        while (strlen($randStr) < $len) {
            $randStr .= substr($chars, (mt_rand() % strlen($chars)), 1);
        }

        return $randStr;
    }


    /**
     * 多维数组按某字段进行升序或降序排序(0升序 1降序)
     * @param $multi_array
     * @param $sort_filed
     * @param int $sort_type
     * @return array
     * @internal param $sort_order
     * @auth update by ctt，0:asc,1:desc排序
     */
    public static function multiArraySort($multi_array, $sort_filed, $sort_type = 1)
    {
        if ($multi_array) {
            $filedArr = array_column($multi_array, $sort_filed);
            $sort_type == 0 ? array_multisort($filedArr, SORT_ASC, $multi_array) : array_multisort($filedArr, SORT_DESC, $multi_array);
        }

        return $multi_array;
    }


    /**
     * 多维数组按多字段字段排序
     * @return array $result = multiArraySortByMultiFile($result,'branch_id',SORT_ASC,'third_party_id',SORT_DESC...);
     * $result = multiArraySortByMultiFile($result,'branch_id',SORT_ASC,'third_party_id',SORT_DESC...);
     * @throws Exception
     * @internal param $array
     * @internal param $array
     * @internal param $array
     * @internal param $array 参数说明：* 参数说明：
     *  1、第一个参数、第二个参数必选，第三个参数、第四个参数可选
     *  2、第一个参数为要进行排序的数组,其他参数为排序规则
     *  3、排序规则参数格式为 [$a,$b]($a为排序字段,$b为排序规则:升序SORT_ASC/降序SORT_DESC)
     */
    public static function multiArraySortByMultiFile()
    {
        $args = func_get_args();
        if (empty($args)) {
            return null;
        }
        $arr = array_shift($args);
        if (!is_array($arr)) {
            throw new SystemException("第一个参数不为数组");
        }
        foreach ($args as $key => $field) {
            if (is_string($field)) {
                $temp = [];
                foreach ($arr as $index => $val) {
                    $temp[$index] = $val[$field];
                }
                $args[$key] = $temp;
            }
        }
        $args[] = &$arr;//引用值
        call_user_func_array('array_multisort', $args);
        $arr = array_pop($args);
        return $arr;
    }


    public static function checkInputData($obj, $rules, $errMsg = [])
    {
        $validator = app('validator')->make($obj, $rules, $errMsg);
        if ($validator->fails()) {
            throw new VE(self::getValidatorErrorMessage($validator));
        }
    }

    public static function getExceptionMessage($e, $force = false)
    {
        if (($e instanceof VE || $e instanceof SE || $e instanceof STE || $e instanceof JWTException)
            && $force === false || $e instanceof PE || $e instanceof DE || $e instanceof PromotionE) {
            return $e->getMessage();
        }
        try {
            $code = SerialNumberUtils::ex();
            $exp = [
                'code' => $code,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            ExceptionLogsModel::create($exp);
            $msg = "服务器错误,错误编号:{$code}";
            return $msg;
        } catch (Exception $ex) {
            $code = microtime(1);
            Log::error("[{$code}] File:" . $e->getFile());
            Log::error("[{$code}] Line:" . $e->getLine());
            Log::error("[{$code}] Message:" . $e->getMessage());
            Log::error("[{$code}] Trace:" . $e->getTraceAsString());
            $msg = "服务器错误,错误编号:{$code}";
            return $msg;
        }
        return "服务器错误";
    }

    /**
     * 判断某个值是否设置，如果没设置则返回默认值
     * @param $objData
     * @param $field
     * @param string $result
     * @author zxl
     * @return string
     */
    public static function issetValue($objData, $field, $result = '')
    {
        return isset($objData[$field]) ? $objData[$field] : $result;
    }

    /**
     * 判断变量是否存在，不存在默认返回0
     * @param $variable
     * @param $field
     * @param $field_children
     * @return int
     */
    public static function initValue($variable, $field, $field_children)
    {
        $field = (string)$field;

        return isset($variable[$field]) ? $variable[$field][$field_children] : 0;
    }


    /**
     * @param $date_time
     * @return static
     * @throws Exception
     */
    public static function dateTimeToObject($date_time)
    {
        if (!is_object($date_time)) {
            if (!$date_time) {
                $date_time = Carbon::today();
            } elseif (strpos($date_time, '-') !== false) {
                $date_time = Carbon::parse($date_time);
            } else {
                $date_time = Carbon::createFromTimestamp($date_time);
            }
        }

        return $date_time;
    }

    /**
     * 封装的除法运算　
     * @param $top
     * @param $bottom
     * @param $default
     * @return float|int
     * @internal param $top　分子　必填
     * @internal param $bottom　分母　必填
     * @internal param $default　默认值　必填
     */
    public static function divide($top, $bottom, $default = 0)
    {
        return $bottom == 0 ? $default : $top / $bottom;
    }

    public static function parseCoordinate($coordinate)
    {
        if (!$coordinate || trim($coordinate) == '') {
            return false;
        }
        $coordinate = explode(",", $coordinate);
        if (count($coordinate) != 2) {
            return false;
        }
        if ($coordinate[0] > $coordinate[1]) {
            $coordinate = array_reverse($coordinate);
        }

        return $coordinate;
    }


    /**
     * 数组顺时针旋转90度
     * @param $origin
     * @return array
     **/

    public static function rotate($origin)
    {
        $cols = count($origin);
        $rows = count(reset($origin));
        $keys = array_keys(reset($origin));
        $trans = [];
        for ($i = 0; $i < $cols; $i++) {
            for ($j = 0; $j < $rows; $j++) {
                $key = $keys[$j];
                $trans[$key][$i] = $origin[$i][$key];
            }
        }
        return $trans;
    }




    /**
     * 获取中文字符拼音首字母
     * @param $character
     * @return null|string
     * @internal param 中文字符 $str
     */
    public static function generateProductMemoryCode($character)
    {
        $res = '';
        $length = mb_strlen($character);
        for ($i = 0; $i < $length; $i++) {
            $str = mb_substr($character, $i, 1);
            if (preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str) > 0 && !empty($str)) { //判断是否是中文
                $res .= strtoupper(substr(app('pinyin')->sentence($str), 0, 1));
            }
        }

        return $res;
    }

    /**
     * 创建树形结构
     * @param $list
     * @param int $parentValue
     * @param string $rootKey
     * @param string $parentKey
     * @param string $subKey
     * @return array
     */
    public static function createTree($list, $parentValue = 0, $rootKey = 'id', $parentKey = 'parent_id', $subKey = 'sub')
    {
        $res = [];
        foreach ($list as $k => $v) {
            if ($v[$parentKey] == $parentValue) {
                $tmp = $list[$k];
                unset($list[$k]);
                $sub = self::createTree($list, $v[$rootKey]);
                if ($sub) {
                    $tmp[$subKey] = $sub;
                }
                $res[] = $tmp;
            }
        }

        return $res;
    }

    /**
     * 获取树形结构指定根节点下所有子节点编号
     * @param $modelClass 模型类
     * @param $rootKey 根节点编号
     * @param string $filedName 树形结构编号字段名
     * @param string $parentKey 树形结构父级编号字段名
     * @return array
     * @internal param 模型类 $modelObj
     */
    public static function getSubCategoryIds($modelClass, $rootKey, $filedName = 'id', $parentKey = 'parent_id')
    {
        $res = [];
        if ($rootKey) {
            $category_gid_arr = $modelClass::where($parentKey, $rootKey)->lists($filedName)->toArray();
            $res = [intval($rootKey)];
            if ($category_gid_arr) {
                foreach ($category_gid_arr as $key => $val) {
                    $res = array_merge($res, self::getSubCategoryIds($modelClass, $val, $filedName, $parentKey));
                }
            }
        }

        return $res;
    }

    /**
     * 生成编码(固定位数编码自增,默认不足左补零)
     * @param $modelClass
     * @param $snKey
     * @param $fixedNum
     * @param string $fixedStr
     * @param string $prefix
     * @return string
     * @throws Exception
     */
    public static function createAutoIncrementSn($modelClass, $snKey, $fixedNum, $fixedStr = '0', $prefix = '')
    {
        $obj = $modelClass::orderBy($snKey, 'desc')->first();
        $current_max_sn = $obj->$snKey ?? 0;
        $prefix_length = strlen($prefix);
        if ($prefix != '') {
            $sn = substr($current_max_sn, $prefix_length) + 1;
        } else {
            $sn = $current_max_sn + 1;
        }
        if (strlen($sn) > $fixedNum) {
            throw new Exception('已存编码位数已经溢出');
        }

        return $prefix . str_pad($sn, $fixedNum, $fixedStr, STR_PAD_LEFT);
    }

    /**
     * 无限极分类获取某根节点下所有子节点(包括根节点)
     * @param $sourceArr
     * @param int $pid
     * @param string $parentKey
     * @return array
     */
    public static function getTree($sourceArr, $pid = 1, $parentKey = 'parent_id')
    {
        $sub_ids = [intval($pid)]; //初始化结果
        $pid_arr = [$pid]; //根节点
        do {
            $sub_pid_arr = [];
            $end_loop = false;
            foreach ($pid_arr as $pid) {
                foreach ($sourceArr as $key => $value) {
                    if ($value[$parentKey] == $pid) {
                        $sub_ids[] = $value['id']; //将子级添加到最终结果中
                        $sub_pid_arr[] = $value['id']; //将子级id保存起来用来下轮循环他的子级
                        unset($sourceArr[$key]); //剔除已经添加的子级
                        $end_loop = true;
                    }
                }
            }
            $pid_arr = $sub_pid_arr; //继续循环找到子级的子级
        } while ($end_loop == true);

        return $sub_ids;
    }

    public static function imagePrefixHandle($image)
    {
        if ($image) {
            $arr = explode(',', $image);
            foreach ($arr as $key => $value) {
                if (strpos($value, env('QINIU_DOMAIN')) === false && $value != '') {
                    $arr[$key] = env('QINIU_DOMAIN') . '/' . $value;
                } else {
                    $arr[$key] = $value;
                }
            }
            $image = implode(',', $arr);
        }
        return $image;
    }

    public static function prefixHandle($url)
    {
        if (strpos($url, env('QINIU_DOMAIN')) === false && $url != '') {
            $url = env('QINIU_DOMAIN') . '/' . $url;
        }
        return $url;
    }

    public static function imageSlim($url)
    {
        if ($url) {
            $url = $url . '?imageslim';
        }
        return $url;
    }

    public static function thumb($url)
    {
        if ($url) {
            $url = $url . '?imageView2/2/w/200/h/200/q/100|imageslim';
        }
        return $url;
    }

    public static function addWaterImage($url, $waterImageUrl, $gravity)
    {
        if ($url) {
            $waterImageBaseUrl = strtr(base64_encode($waterImageUrl), '+/', '-_');
            $url = $url . "?watermark/1/image/$waterImageBaseUrl/gravity/$gravity";
        }
        return $url;
    }

    public static function videoCover($url){
        if ($url) {
            $url = $url . '?vframe/jpg/offset/1';
        }
        return $url;
    }

    public static function maxWidth($url, $maxWidth = 500)
    {
        if($url){
            $url = $url . '?imageView2/2/w/'.$maxWidth.'/q/100|imageslim';
        }
        return $url;
    }


    /**
     * 获取距离指定经纬度指定距离的四点位置坐标
     *
     * @param $lng
     * @param $lat
     * @param $distance
     *
     * @return array
     */
    public static function calculateFourPoint($lng, $lat, $distance)
    {
        $half = 6371;
        $dlng = 2 * asin(sin($distance / (2 * $half)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);
        $dlat = $distance / $half;
        $dlat = rad2deg($dlat);
        $fourPoint = [
            'left-top' => ['lat' => $lat + $dlat, 'lng' => $lng - $dlng],
            'right-top' => ['lat' => $lat + $dlat, 'lng' => $lng + $dlng],
            'left-bottom' => ['lat' => $lat - $dlat, 'lng' => $lng - $dlng],
            'right-bottom' => ['lat' => $lat - $dlat, 'lng' => $lng + $dlng]
        ];
        return $fourPoint;
    }

    /**
     * 求两个已知经纬度之间的距离,单位为米
     *
     * @param $lng1
     * @param $lat1
     * @param $lng2
     * @param $lat2
     *
     * @return float 距离，单位米
     * @author zxl
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2)
    {
        // 将角度转为狐度
        $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }

    /**
     *      把秒数转换为时分秒的格式
     * @param Int $times 时间，单位 秒
     * @return String
     */
    public static function secToTime($times)
    {
        $result = '00:00:00';
        if ($times > 0) {
            $hour = floor($times / 3600);
            $minute = floor(($times - 3600 * $hour) / 60);
            $second = floor((($times - 3600 * $hour) - 60 * $minute) / 60);
            $result = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' .
                str_pad($minute, 2, '0', STR_PAD_LEFT) . ':' .
                str_pad($second, 2, '0', STR_PAD_LEFT);
        }
        return $result;
    }

    public static function tokenCreate()
    {
        return substr(md5(microtime()), 0, 8);
    }

    public static function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        return $msectime;
    }

    /**
     * 将xml转为array
     * @param $xml
     * @return mixed
     */
    public static function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        $string = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $result = json_decode(json_encode($string), true);
        return $result;
    }

    public static function autowrap($fontsize, $angle, $fontface, $string, $width)
    {
        // 参数分别是 字体大小, 角度, 字体名称, 字符串, 预设宽度
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        preg_match_all("/./u", $string, $arr);
        $letter = $arr[0];
        $height = 0;
        foreach ($letter as $l) {
            $teststr = $content . $l;
            $testbox = imagettfbbox($fontsize, $angle, $fontface, $teststr);
            if (($testbox[2] > $width) && ($content !== "")) {
                $content .= PHP_EOL;
            }
            $content .= $l;
        }
        return $content;
    }

    public static function getVersion()
    {
        $config = \App\Models\SysConfigModel::where(['e_name' => 'version'])->first();
        $data = json_decode($config->value, true);
        return $data;
    }

    public static function unlink(string $path) {
        try {
            unlink($path);
            return true;
        } catch (\Exception $e) {
            return true;
        }
    }
}
