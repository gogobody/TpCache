<?php

interface TpCache
{
	//初始化
	public function init($option);

	//添加缓存
	public function add($key, $value);

	//删除缓存
	public function delete($key);

	//设置缓存
	public function set($key, $value);

	//获取缓存
	public function get($key);

	//清空缓存
	public function flush();
}
