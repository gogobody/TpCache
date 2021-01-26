<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho缓存插件
 *
 * @package TpCache
 * @author gogobody modify
 * @version 1.0.0
 * @link https://ijkxs.com
 */

class TpCache_Plugin implements Typecho_Plugin_Interface
{
	public static $cache = null;
	public static $html = null;
	public static $path = null;
	public static $sys_config = null;
	public static $plugin_config = null;
	public static $request = null;
	
	public static $passed = false;

	/**
	 * 激活插件方法,如果激活失败,直接抛出异常
	 *
	 * @access public
	 * @return void
	 * @throws Typecho_Plugin_Exception
	 */
	public static function activate()
	{
	    // 全局缓存
		// 在index.php开始, 尝试使用缓存
		Typecho_Plugin::factory('index.php')->begin = array(__CLASS__, 'C');
		// 在index.php结束, 尝试写入缓存
		Typecho_Plugin::factory('index.php')->end = array(__CLASS__, 'S');

        // 编辑页面后更新缓存
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array(__CLASS__, 'post_update');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array(__CLASS__, 'post_update');

        // 删除页面后更新缓存
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->delete = array(__CLASS__, 'post_del_update');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->delete = array(__CLASS__, 'post_del_update');

        //评论
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array(__CLASS__, 'comment_update');
		
		//评论后台
		Typecho_Plugin::factory('Widget_Comments_Edit')->finishDelete = array(__CLASS__, 'comment_update2');
		Typecho_Plugin::factory('Widget_Comments_Edit')->finishEdit = array(__CLASS__, 'comment_update2');
		Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array(__CLASS__, 'comment_update2');

		// 部分缓存
		// 缓存MarkDown
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array(__CLASS__, 'cache_contentEx');

        // 插入写作标签
        Typecho_Plugin::factory('admin/write-post.php')->bottom_100 = array(__CLASS__, 'forTpCacheToolbar');
        Typecho_Plugin::factory('admin/write-page.php')->bottom_100 = array(__CLASS__, 'forTpCacheToolbar');

        // 插件接口
        Typecho_Plugin::factory('TpCache.Widget_Cache')->getCache = array(__CLASS__, 'TpCache_getCache');
        Typecho_Plugin::factory('TpCache.Widget_Cache')->setCache = array(__CLASS__, 'TpCache_setCache');
        return '插件安装成功,请设置需要缓存的页面';
	}

	/**
	 * 禁用插件方法,如果禁用失败,直接抛出异常
	 *
	 * @static
	 * @access public
	 * @throws Typecho_Plugin_Exception
	 */
	public static function deactivate()
	{
	}

	/**
	 * 获取插件配置面板
	 *
	 * @access public
	 * @param Typecho_Widget_Helper_Form $form 配置面板
	 * @return void
	 */
	public static function config(Typecho_Widget_Helper_Form $form)
	{
        ?>
        <div class="j-setting-contain">
            <link href="<?php echo Helper::options()->rootUrl ?>/usr/plugins/TpCache/assets/css/joe.setting.min.css" rel="stylesheet" type="text/css" />
            <div>
                <div class="j-aside">
                    <div class="logo">TpCache Modify</div>
                    <ul class="j-setting-tab">
                        <li data-current="j-setting-notice">插件公告</li>
                        <li data-current="j-setting-config">缓存设置</li>
                        <li data-current="j-setting-global">全局缓存</li>
                        <li data-current="j-setting-normal">部分缓存</li>
                    </ul>
                    <?php require_once('Backups.php'); ?>
                </div>
            </div>
            <span id="j-version" style="display: none;">1.0.4</span>
            <div class="j-setting-notice">请求数据中...</div>

            <script src="<?php echo Helper::options()->rootUrl ?>/usr/plugins/TpCache/assets/js/joe.setting.min.js"></script>
        <?

        $list = array('关闭', '开启');
		$element = new Typecho_Widget_Helper_Form_Element_Radio('login', $list, 1, '是否对已登录用户失效', '已经录用户不会触发缓存策略');
        $element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		$list = array('关闭', '开启');
		$element = new Typecho_Widget_Helper_Form_Element_Radio('enable_ssl', $list, '0', '是否支持SSL');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		$list = array(
			'0' => '不使用缓存',
			'memcached' => 'Memcached',
			'redis' => 'Redis'
		);
		$element = new Typecho_Widget_Helper_Form_Element_Radio('cache_driver', $list, '0', '缓存驱动');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		$element = new Typecho_Widget_Helper_Form_Element_Text('expire', null, '86400', '缓存过期时间', '86400 = 60s * 60m *24h，即一天的秒数');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		$element = new Typecho_Widget_Helper_Form_Element_Text('host', null, '127.0.0.1', '主机地址', '主机地址，一般为127.0.0.1');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		$element = new Typecho_Widget_Helper_Form_Element_Text('port', null, '11211', '端口号', 'memcache(d)默认为11211，redis默认为6379，其他类型随意填写');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		//$list = array('关闭', '开启');
		//$element = new Typecho_Widget_Helper_Form_Element_Radio('is_debug', $list, 0, '是否开启debug');
		//$form->addInput($element);

		$list = array('关闭', '清除所有数据');
		$element = new Typecho_Widget_Helper_Form_Element_Radio('is_clean', $list, 0, '清除所有数据');
		$element->setAttribute('class', 'j-setting-content j-setting-config');
		$form->addInput($element);

		// 全局缓存
        $list = array('0'=>'关闭', '1'=>'开启');
		$element = new Typecho_Widget_Helper_Form_Element_Radio('enable_gcache', $list, '1', '是否开启全局缓存','在开启全局缓存的情况下，该页面缓存选项有效');
		$element->setAttribute('class', 'j-setting-content j-setting-global');
		$form->addInput($element);

		$list = array(
			'index' => '首页',
			'archive' => '归档',
			'post' => '文章',
			'attachment' => '附件',
			'category' => '分类',
			'tag' => '标签',
			'author' => '作者',
			'search' => '搜索',
			'feed' => 'feed',
			'page' => '页面'
		);
		$element = new Typecho_Widget_Helper_Form_Element_Checkbox('cache_page', $list, array('index', 'post', 'search', 'page', 'author', 'tag'), '需要缓存的页面');
        $element->setAttribute('class', 'j-setting-content j-setting-global');
		$form->addInput($element);

		$list = array('0'=>'关闭', '1'=>'开启');
		$element = new Typecho_Widget_Helper_Form_Element_Radio('enable_markcache', $list, '1', '是否开启markdown缓存','在全局缓存命中失效的时候是否开启 markdown 部分缓存,
        选项关闭，则未命中均不缓存。选项开启后，在文章编辑界面通过 NOCACHE 标签来决定是否缓存当前文章，按钮已经集成了，插入标签就表示不缓存<br>');
		$element->setAttribute('class', 'j-setting-content j-setting-normal');
		$form->addInput($element);


	}

	public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

	public static function configHandle($config, $is_init)
	{
		if ($config['cache_driver'] != '0') {
			self::initBackend($config['cache_driver']);
			if ($config['is_clean'] == '1') {
				try {
					self::$cache->flush();
				} catch (Exception $e) {
					print $e->getMessage();
				}
				$config['is_clean'] = '0';
			}
		}else{
		    // 关闭其他插件选项
		    if ($config['enable_gcache'] == '1'){
		        $config['enable_gcache'] = 0;
		    }
		    if ($config['enable_markcache'] == '1'){
		        $config['enable_markcache'] = 0;
		    }
		}
		Helper::configPlugin('TpCache', $config);
	}

	/**
	 * 尝试使用缓存
	 */
	public static function C()
	{
		self::initEnv();
		if (!self::preCheck()) return;
		if (!self::initPath()) return;
		// 全局缓存关闭则直接返回
		if (self::$plugin_config->enable_gcache == '0')
		    return ;
		try {
			// 获取当前url的缓存
			$data = self::getCache();
			if (!empty($data)) {
				//缓存未过期, 跳过之后的缓存重写入
				if ($data['time'] + self::$plugin_config->expire < time())
					self::$passed = false;
				// 缓存命中 // 这里是我个人用来控制 主题黑夜模式的...
				$html = str_replace('{colorMode}',$_COOKIE['night']=='1'?'dark':'light',$data['html']);
				echo $html;
				die;
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		// 先进行一次刷新
		ob_flush();
	}

	/**
	 * 写入缓存页面
	 */
	public static function S($html = '')
	{
		if (is_null(self::$path) || !self::$passed)
			return;
		if (empty($html))
			$html = ob_get_contents();
		$data = array();
		$data['time'] = time();
		$data['html'] = $html;
		self::setCache($data);
	}

	public static function getCache($name = null)
	{
	    if ($name) return unserialize(self::$cache->get($name));
		return unserialize(self::$cache->get(self::$path));
	}

	public static function setCache($data, $name = null)
	{
	    if ($name) return self::$cache->set($name, serialize($data));
		return self::$cache->set(self::$path, serialize($data));
	}

	public static function delCache($path, $rmHome = True)
	{
		self::$cache->delete($path);
		if ($rmHome)
			self::$cache->delete('/');
	}

	public static function preCheck($checkPost = True)
	{
		if ($checkPost && self::$request->isPost()) return false;

		if (self::$plugin_config->login && Typecho_Widget::widget('Widget_User')->hasLogin())
			return false;
		if (self::$plugin_config->enable_ssl == '0' && self::$request->isSecure() == true)
			return false;
		if (self::$plugin_config->cache_driver == '0')
			return false;
		self::$passed = true;
		return true;
	}

	public static function initEnv()
	{
		if (is_null(self::$sys_config))
			self::$sys_config = Helper::options();
		if (is_null(self::$plugin_config))
			self::$plugin_config = self::$sys_config->plugin('TpCache');
		if (is_null(self::$request))
			self::$request = new Typecho_Request();
	}

	public static function initPath($pathInfo='')
	{
		if(empty($pathInfo))
			$pathInfo = self::$request->getPathInfo();

		if (!self::needCache($pathInfo)) return false;
		self::$path = $pathInfo;
		return self::initBackend(self::$plugin_config->cache_driver);
	}

	public static function initBackend($backend){
		$class_name = "typecho_$backend";
		require_once 'driver/cache.interface.php';
		require_once "driver/$class_name.class.php";
		self::$cache = call_user_func(array($class_name, 'getInstance'), self::$plugin_config);
		if (is_null(self::$cache))
			return false;
		return true;
	}

	public static function needCache($path)
	{
		// 后台数据不缓存
		$pattern = '#^' . __TYPECHO_ADMIN_DIR__ . '#i';
		if (preg_match($pattern, $path)) return false;
		// action动作不缓存
		$pattern = '#^/action#i';
		if (preg_match($pattern, $path)) return false;

        // fix:pjax search 失效
        $requestUrl = self::$request->getRequestUri();
        // search 请求第一次不缓存
        $pattern = '/.*?s=.*/i';
        if (preg_match($pattern, $requestUrl)) return false;
        // search 重定向后可以缓存
        $pattern = '#^/search#i';
        if (preg_match($pattern, $path) and in_array('search', self::$plugin_config->cache_page)) return true;

        $_routingTable = self::$sys_config->routingTable;
        // 针对文章页做特殊处理,付费文章不缓存
        $post_regx = $_routingTable[0]['post']['regx'];
        if (preg_match($post_regx,$path,$arr)){
            /**
             * 付费文章不缓存
             * [0] => /archives/377.html
             * [1] => 377
             */
            if ($arr[1] and !empty($arr[1])){
                // 查看文章是否是 tepass 付费文章
                $db = Typecho_Db::get();
                try {
                    $p_id = $db->fetchObject($db->select('id')->from('table.tepass_posts')->where('post_id = ?',$arr[1]))->id;
                    if ($p_id) return false;
                }catch (Typecho_Db_Query_Exception $e){
                    // 没有tepass
                }

            }
        }

		foreach ($_routingTable[0] as $page => $route) {
			if ($route['widget'] != 'Widget_Archive') continue;
			if (preg_match($route['regx'], $path)) {
				$exclude = array('_year', '_month', '_day', '_page');
				$page = str_replace($exclude, '', $page);

				if (in_array($page, self::$plugin_config->cache_page))
					return true;
			}
		}
		return false;
	}

	public static function post_update($contents, $class)
	{
		if ('publish' != $contents['visibility'] || $contents['created'] > time())
			return;

		self::initEnv();
		if (self::$plugin_config->cache_driver == '0')
			return;
		self::$passed = true;

		$type = $contents['type'];
		$routeExists = (NULL != Typecho_Router::get($type));
		if (!$routeExists) {
			self::initPath('#');
			self::delCache(self::$path);
			return;
		}

		$db = Typecho_Db::get();
		$contents['cid'] = $class->cid;
		$contents['categories'] = $db->fetchAll($db->select()->from('table.metas')
			->join('table.relationships', 'table.relationships.mid = table.metas.mid')
			->where('table.relationships.cid = ?', $contents['cid'])
			->where('table.metas.type = ?', 'category')
			->order('table.metas.order', Typecho_Db::SORT_ASC));
		$contents['category'] = urlencode(current(Typecho_Common::arrayFlatten($contents['categories'], 'slug')));
		$contents['slug'] = urlencode(empty($contents['slug'])?$class->slug:$contents['slug']);
		$contents['date'] = new Typecho_Date($contents['created']);
		$contents['year'] = $contents['date']->year;
		$contents['month'] = $contents['date']->month;
		$contents['day'] = $contents['date']->day;

		if (!self::initPath(Typecho_Router::url($type, $contents))){
		    return;
//		    throw new Typecho_Exception('初始化失败。url info:'.Typecho_Router::url($type, $contents));
		}
		self::delCache(self::$path);
		// 同时，删除 markdown 的部分缓存
		if ($class->cid)
		    self::delCache(self::getPostMarkCacheName($class->cid));
	}

	public static function post_del_update($cid, $obj)
	{
	    $db = Typecho_Db::get();
	    $postObject = $db->fetchObject($db->select('cid','slug', 'type')
                ->from('table.contents')->where('cid = ?', $cid));
	    if (!$postObject->cid){
	        return;
	    }
	    // 生成path
	    $value = [];
	    $value['cid'] = $cid;
	    $value['type'] = $postObject->type;
	    $value['slug'] = urlencode($postObject->slug);
        $pathInfo = Typecho_Router::url($value['type'], $value);

		self::initEnv();

		self::initBackend(self::$plugin_config->cache_driver);
		self::delCache($pathInfo);
        if ($cid){
            self::delCache(self::getPostMarkCacheName($cid));
        }
	}

	public static function comment_update($comment)
	{
		self::initEnv();
		if (!self::preCheck(false)) return;
		if (!self::initBackend(self::$plugin_config->cache_driver))
			return;

		// 获取评论的PATH_INFO
		$path_info = self::$request->getPathInfo();
		// 删除最后的 /comment就是需删除的path
		$article_url = preg_replace('/\/comment$/i','',$path_info);

		self::delCache($article_url);
		// 同时，删除 markdown 的部分缓存
		if ($comment->cid)
		    self::delCache(self::getPostMarkCacheName($comment->cid));
	}
 
	public static function comment_update2($comment = null, $edit)
	{
		self::initEnv();
		self::preCheck(false);
		self::initBackend(self::$plugin_config->cache_driver);

		$perm = stripslashes($edit->parentContent['permalink']);
		$perm = preg_replace('/(https?):\/\//', '', $perm);
		$perm = preg_replace('/'.$_SERVER['HTTP_HOST'].'/', '', $perm);

		self::delCache($perm);
			// 同时，删除 markdown 的部分缓存
		if ($edit->cid)
		    self::delCache(self::getPostMarkCacheName($edit->cid));
		if ($comment['cid'])
            self::delCache(self::getPostMarkCacheName($comment['cid']));

	}

	// 缓存content
	public static function getPostMarkCacheName($cid){
	    if (!self::$path)
	        self::$path = self::$request->getPathInfo();
	    return self::$path.'_'.$cid.'_markdown';
	}

	public static function cache_contentEx($content, $obj, $lastResult){
	    $content = empty( $lastResult ) ? $content : $lastResult;
        if (self::$plugin_config->enable_markcache == '0'){
            return $content;
        }
	    // 文章页面设置了标记的就不缓存
        if (substr_count($content,'<!--no-cache-->'))
            return $content;
        // 为 content 设置特殊的 cache name
        self::$path = self::$request->getPathInfo();
        $cacheName = self::getPostMarkCacheName($obj->cid);
        self::initEnv();
        if (!self::preCheck(false)) {
            return $content;
        }
        if (!self::initBackend(self::$plugin_config->cache_driver)){
            return $content;
        }
        // 获取评论的PATH_INFO
//        $requestUrl = self::$request->getRequestUri();
        try {
            // 获取当前url的缓存
            $data = self::getCache($cacheName);
            if (!empty($data)) {
                //缓存未过期, 跳过之后的缓存重写入
                if ($data['time'] + self::$plugin_config->expire < time())
                    self::$passed = false;
                return $data['html'];
            }
        } catch (Exception $e) {
//            echo $e->getMessage();
            return $content;
        }

        // 没有缓存就缓存起来
        if (is_null(self::$path) || !self::$passed)
            return $content;
        $data = array();
        $data['time'] = time();
        $data['html'] = $content;
        self::setCache($data,$cacheName);
        return $content;
    }

    public static function forTpCacheToolbar(){
        ?>
        <script>
            $(document).ready(function(){
                $('#wmd-button-row').append('<li class="wmd-button" id="wmd-TePass-button" title="插入no-cache"><span style="background: none;font-size: 16px;border: 1px solid #dedede;padding: 2px;color: red;width: auto;height: auto;">NOCACHE</span></li>');
                if($('#wmd-button-row').length !== 0){
                    $('#wmd-TePass-button').click(function(){
                        var rs = "\r\n<!--no-cache-->\r\n";
                        var myField = $('#text')[0];
                        insertAtCursor(myField,rs);
                    })
                }
                function insertAtCursor(myField, myValue) {
                    //IE 浏览器
                    if (document.selection) {
                        myField.focus();
                        sel = document.selection.createRange();
                        sel.text = myValue;
                        sel.select();
                    }
                    //FireFox、Chrome等
                    else if (myField.selectionStart || myField.selectionStart == '0') {
                        var startPos = myField.selectionStart;
                        var endPos = myField.selectionEnd;
                        // 保存滚动条
                        var restoreTop = myField.scrollTop;
                        myField.value = myField.value.substring(0, startPos) + myValue + myField.value.substring(endPos, myField.value.length);
                        if (restoreTop > 0) {myField.scrollTop = restoreTop;}
                        myField.selectionStart = startPos + myValue.length;
                        myField.selectionEnd = startPos + myValue.length;
                        myField.focus();
                    } else {
                        myField.value += myValue;
                        myField.focus();
                    }
                }
            });
        </script>
        <?php
    }

    /* 确保key值唯一 */
    public static function TpCache_setCache($cacheKey,$val){
	    self::initEnv();
        if (!self::preCheck(false)) {
            return false;
        }
        if (!self::initBackend(self::$plugin_config->cache_driver)){
            return false;
        }
        $data = array();
        $data['time'] = time();
        $data['html'] = $val;
        self::setCache($data,$cacheKey);
        return true;
    }
    // 插件接口，获取唯一cache值对应val
    public static function TpCache_getCache($cacheKey){
	    self::initEnv();
        if (!self::preCheck(false)) {
            return false;
        }
        if (!self::initBackend(self::$plugin_config->cache_driver)){
            return false;
        }
        try{
            // 获取当前缓存
            $data = self::getCache($cacheKey);
            if (!empty($data)) {
                //缓存未过期, 跳过之后的缓存重写入
                if ($data['time'] + self::$plugin_config->expire < time())
                    self::$passed = false;
                return $data['html'];
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        return false;
    }
}
