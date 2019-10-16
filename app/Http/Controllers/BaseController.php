<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Composer\Semver\Comparator;
use Illuminate\Http\Request;
use DB;
use Dingo\Api\Routing\Helpers;
use App\Helpers\Util as Util;
use App\Helpers\Err as Err;
use App\Helpers\DBUtil;          // 数据库操作相关工具    eg. 获取上一条执行的SQL语句


use Log;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use App\Models\CustomerModel;
use App\Models\OrganizationStructureModel;
use App\Models\StaffModel;

class BaseController extends Controller
{
    use Helpers;
    protected $_enableCache = false;                    // 默认关闭缓存，各个实体类库可以自己在构造函数中开启
    protected static $ENABLE_CACHE = false;             // 给静态方法用的缓存开关
    protected static $CACHE_KEY_DELIMINATOR = '&';      // 缓存键的分隔符
    // 缓存键中，不同条件之间用这个静态变量(&)做分隔符,
    protected static $CACHE_KEY_INNER_DELIMINATOR = '#';
    // 条件内部用上面这个静态变量(#)做分隔符
    //          缓存用法   http://laravelacademy.org/post/176.html
    protected $_cacheExpireTime = 60;           // 默认的缓存过期时间
    protected $_paginationRule = '';            // 分页规则    http输入参数字段  pages = X:Y        X：页码   Y：每页行数  Y可以留空，默认20
    protected $request = '';
    protected $log_fmt = "%s@%.2f %s";
    protected $user = false;
    protected $start;
    protected $end;

    public function __construct()
    {
        // 每次执行的时候清空日志
        $this->start = microtime(true);
        DB::enableQueryLog();       // 启用数据库查询 log
        $req = Request::capture();
        $this->request = $req;
        $this->_paginationRule = Util::getFilteredPages($req);
        $this->_enableCache = self::$ENABLE_CACHE;
        $exec_log_name = '/logs/exec-time-' . date('Y-m-d') . '.log';
        $this->logger = new Logger('logs');
        $this->logger->pushHandler(
            new StreamHandler(
                storage_path($exec_log_name),
                Logger::DEBUG
            )
        );
        $this->user = $this->getCurrentUser();
    }

    /**
     * 返回值格式
     * "pos_identity": {
     *    "id": 21,
     *    "gid": 0,
     *    "created_at": "2015-11-27 09:50:31",
     *    "updated_at": "2015-11-27 09:50:31",
     *    "name": "",
     *    "FK_store_id": 0,
     *    "device_id": "efe937780e95574250dabe07151bdc23",
     *    "mac": "7c:8f:2c:2d",
     *    "password"  : 'aaaaaaaaaaaaaaaaaaaa',         // 仅用于展示数据结果，最终不会返回明文密码
     *    "in_service": 0,
     *    "province": 0,
     *    "city": 0,
     *    "district": 0,
     *    "type": null
     *  }
     *
     * {
     *    "pos_identity": {
     *        "name": "",
     *        "pos_id": 0,
     *        "FK_store_id": 88888,
     *        "FK_region_province_id": 0,
     *        "FK_region_city_id": 0,
     *        "FK_region_district_id": 0
     *    }
     * }
     *
     * @return mixed
     */
    protected function getUserInfoFromToken()
    {
        $ret = $this->auth->user();
        if ($ret) {
            unset($ret->password);
        }
        return $ret;
    }

    protected function getStoreId()
    {
        $tokenOwnerIdentity = self::getUserInfoFromToken();
        $storeId = $tokenOwnerIdentity['FK_store_id'];
        if (empty($storeId)) {
            $this->ApiResponse((object)[], Err::FAILED, 0, '未能从令牌获取门店信息，请检查令牌是否失效');
        }
        return $storeId;
    }

    protected function getCustomerId()
    {
        $tokenOwnerIdentity = self::getUserInfoFromToken();
        $storeId = $tokenOwnerIdentity['FK_customer_id'];
        if (empty($storeId)) {
            $this->ApiResponse((object)[], Err::FAILED, 0, '未能从令牌获取用户信息，请检查令牌是否失效');
        }
        return $storeId;
    }

    protected function getStaffID()
    {
        $tokenOwnerIdentity = self::getUserInfoFromToken();
        $staff_id = $tokenOwnerIdentity['staff_id'];
        if (empty($staff_id)) {
            $this->ApiResponse((object)[], Err::FAILED, 0, '未能从令牌获取用户信息，请检查令牌是否失效');
        }
        return $staff_id;
    }

    protected function getDepartmentIds()
    {
        $department_id = StaffModel::find(self::getStaffID())->department_id;
        $departmentArr = OrganizationStructureModel::select('id', 'name', 'parent_id')->get()->toArray();
        return Util::getTree($departmentArr, $department_id);
    }

    protected function getCustomerInfo()
    {
        $user = self::getUserInfoFromToken();
        $customer = [];
        if ($user) {
            $customer = CustomerModel::find($user->FK_customer_id);
        }
        return $customer;
    }

    protected function getCurrentUser()
    {
        $uid = $this->request->input('current_uid');
        if ($uid) {
            $user = StaffModel::find($uid);
            return $user ? $user : false;
        }
        return false;
    }

    protected function getUserStructureRange()
    {
        $this->user = $this->user ? $this->user : StaffModel::find($this->getStaffID());
        if ($this->user) {
            return $this->user->getStructureRange();
        }

        return false;
    }

    protected function ApiResponse($data = '', $type = 'true', $count = 0, $msg = '')
    {
        $this->end = microtime(true);
        $ext = ($this->end - $this->start) * 1000;
        $platform = $this->request->header('platform') ?: 'Unknown';
        $version = $this->request->header('version') ?: 'Unknown';
        if (!$this->isDisableLogUrl($_SERVER['REQUEST_URI'])) {
            $log = vsprintf($this->log_fmt, [$_SERVER['REQUEST_URI'], $ext, "{$platform}-{$version}"]);
            $this->logger->debug($log, $this->request->all());
        }
        header('Content-Type: application/json');
        echo json_encode(['data' => $data, 'result' => $type, 'count' => $count, 'msg' => $msg]);
        exit;
    }



    protected function getDeviceInfo()
    {
        $info = $this->request->header('platform');
        if (is_null($info)) {
            return false;
        }
        $info = explode("-", $info);
        return ['platform' => $info[0], 'version' => $info[1]];
    }

    protected function isDisableLogUrl($url)
    {
        $url = trim($url);
        return in_array($url, config('DisableLog.urls'));
    }
}
