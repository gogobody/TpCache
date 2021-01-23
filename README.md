## TpCache 魔改版

让 `typecho` 支持 `memcached` 和 `redis` 缓存器

了解详情: https://github.com/gogobody/TpCache

原插件地址: https://github.com/phpgao/TpCache

## 说明
插件适配了 Tepass ，默认不会对 Tepass 付费文章缓存。  

插件有两种缓存机制：
### 全局缓存：
采用全局缓存的话，所有非js实现的功能全部失效，悉知！此方法对于ip量大的或者由于服务器 TTFB 时间长的很有效果！但是需要手动去修改代码，更改一些机制由js实现。  
例如：基于php cookie的阅读次数失效，基于typehco cookie 的评论人信息缓存失效，这些都可以通过js解决。

### 部分缓存：
插件默认开启 markdown 缓存，仅对文章 markdown 转换后的内容做缓存，不对其他组件缓存。此方法对于长文章有良好的效果。但此方法可能导致一些其他也使用 typecho contentEX 接口或插件失效。

### 组件缓存（beta）：
当然，启用全局缓存后这里是无效的。  
插件提供了缓存接口，你可以自定义缓存内容。比如有很多数据库耗时的查询等等。

#### 设置缓存
参数说明：  
- $key 是唯一标识符,可以是任意唯一的字符串。
- $val 是缓存对象，内部采用php默认序列化实现，不保证对所有对象有效。
```
<?php echo Typecho_Plugin::factory('TpCache.Widget_Cache')->TpCache_setCache($key,$val); ?>
```
#### 获取缓存
获取 $key 值对应的字符串
```
<?php echo Typecho_Plugin::factory('TpCache.Widget_Cache')->TpCache_getCache($key); ?>
```


## 缓存更新机制

**目前以下操作会触发缓存更新**

- 来自原生评论系统的评论
- 后台文章或页面更新
- 后台更新评论
- 重启缓存器后端
- 缓存到期
- 删除文章或者页面


## 安装

请将文件夹**重命名**为`TpCache`。再拷贝至`usr/plugins/下`。

请正确配置缓存后台，配置对应redis 或者 memcache

## 升级

请先**禁用此插件**后再升级，很多莫名其妙的问题都是因为没有先禁用而直接升级导致的！

## 如何查看缓存生效
首先不开启缓存查看一个页面的加载时间，此刻 TTFB 为 215ms：
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210123133349.png)

同一个页面开启缓存后，TTFB 70ms
![](https://cdn.jsdelivr.net/gh/gogobody/blog-img/blogimg/20210123133558.png)
