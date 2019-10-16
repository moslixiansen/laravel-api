<?php

namespace Vita\Helpers;

class Pagination
{
    const DEFAULT_PAGE_SIZE = 10;

    protected $data = [];  // 原始数据
    protected $pageSize = 0;  // 每页数量
    protected $count = 0;  // 原始数据总数
    protected $pages = 0;  // 原始数据页数
    protected $current = 1;
    protected $result = [];  // 分页后数据

    protected function __construct()
    {
        $envPageSize = env('PAGE_SIZE');
        $this->pageSize = $envPageSize > 0 ? $envPageSize : self::DEFAULT_PAGE_SIZE;
    }

    /**
     * 载入需要分页的数据
     * @param $model mixed
     * @param $pageSize integer
     * @return $this
     **/

    public static function with($model, $pageSize = 0)
    {
        $instance = new static;
        $instance->data = $model;
        $instance->count = is_array($model) ? count($model) : $model->count();
        $instance->pageSize = $pageSize > 0 ? (int)$pageSize : $instance->pageSize;
        $instance->pages = ceil($instance->count / $instance->pageSize);
        return $instance;
    }

    /**
     * 获取指定分页的数据
     * @param $page integer
     * @return $this
     **/

    public function page($page)
    {
        if ($page < 1) {
            $this->current = 1;
        } elseif ($page > $this->pages) {
            $this->current = $this->pages;
        } else {
            $this->current = $page;
        }
        return $this;
    }

    /**
     * 获取第一页的数据
     * @return mixed
     **/

    public function first()
    {
        return $this->page(1)->get();
    }


    /**
     * 获取最后一页的数据
     * @return mixed
     **/

    public function last()
    {
        return $this->page($this->pages)->get();
    }

    /**
     * 获取当前页码
     * @return integer
     **/

    public function current()
    {
        return $this->current;
    }


    public function get()
    {
        try {
            $start = $this->pageSize * ($this->current - 1);
            if (is_array($this->data)) {
                $this->result = array_slice($this->data, $start, $this->pageSize);
            } else {
                $this->result = $this->data->skip($start)->take($this->pageSize)->get();
            }
            return $this->result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取数据总数
     * @return integer
     **/

    public function count()
    {
        return $this->count;
    }

    /**
     * 设置分页数量
     * @param $size integer
     * @return $this
     **/

    public function setPageSize($size)
    {
        if (isset($size) && (int)$size > 0) {
            $this->pageSize = $size;
            $this->pages = ceil($this->count / $this->pageSize);
        }
        return $this;
    }

    /**
     * 获取当前分页数量设置
     * @return integer
     **/

    public function getPageSize()
    {
        return $this->pageSize;
    }

    public function getPageCount()
    {
        return $this->pages;
    }
}
