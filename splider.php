<?php
/**
 * PHP通用多线程采集
 *
 * 2013-06-28 woondroo create
 */
header("Content-type: text/html;charset=utf-8");

set_time_limit(0);

require("libs/class_curl_multi.php");

//连接数据库
$link = mysql_connect("localhost","root","greenwen");
mysql_select_db("www_curlmulti",$link);

//清空数据库
mysql_query("TRUNCATE TABLE content");

//域名前缀
$base = "http://sellbest.net";

//需要采集的规则列表（分页）
$list = array(
	'http://sellbest.net/by-category/page[1-2]/36-iPad-CASES.html'
);

//在列表页面内容链接表达式
$list_rules = '<p class="productName">.*?<a href="(.*?)">.*?</a>.*?</p>';

//内容页面信息字段表达式
$detail_rules = array(
	'meta_title'=>'<title>(.*?)</title>',
	'meta_keywords'=>'<meta name="keywords" content="(.*?)" />',
	'meta_description'=>'<meta name="description" content="(.*?)" />',
	'product_name'=>'<h4 class="h4-title float-l"> (.*?)</h4>',
	'product_image'=>'<div class="v-inner">.*?<a href="(.*?)" id="originalImg"><img src=".*?" alt=".*?" /></a>.*?</div>',
	'product_price'=>'Our Price : <strong>(.*?)</strong>',
	'product_description'=>'<div class="description-text" id="description"><div class="border-cont">(.*?)</div>',
);

//实例
$mp = new MultiHttpRequest();

//调试使用记录采集条目
$j = 1;

//每次并发几个链接
$limit = 10;

// 分页时被跳过的页数
$last_page = 0;

//开始采集
foreach ($list as $link) {
	
	//解析列表页数
	preg_match_all('/\[(.*)\]/i',$link,$_page);
	if($_page[1][0]==''){
		continue;
	}
	$pages = explode('-',$_page[1][0]);
	if(count($pages) != 2){
		continue;
	}
	
	$urls = array();
	
	for($i=$pages[0];$i<=$pages[1] || $last_page === 1;$i++){
		if(count($urls) < $limit && $last_page === 0){
			$urls[] = preg_replace('/\[(.*)\]/i',$i,$link);
			if($i < $pages[1]){
				continue;
			}
		}

		// var_dump($urls);echo '<br/>';
		$last_page = 0;

		//采集列表内容
		$mp->set_urls($urls);
		$contents = $mp->start();
				
		foreach ($contents as $content) {
			$content = _prefilter($content);
			//debug
			// exit($content);
			
			//匹配内容
			preg_match_all('/'.str_replace('/', '\/', addslashes($list_rules)).'/i',$content,$pregArr);
			
			$detail_urls = array();
			foreach($pregArr[1] as $detail_key=>$detail_value){
			 	$data = array();
				if(count($detail_urls) < $limit ){
					$content_url = $base.$detail_value;
					$detail_urls[$content_url] = $content_url;
					if($pregArr[1][$detail_key+1] != ''){
						continue;
					}
				}
				
				// var_dump($detail_urls);echo '<br/>';
				//continue;
				$mp->set_urls($detail_urls);
				
				$details = $mp->start();
				//图片路径临时存放
				$images_urls = array();
				
				//采集内容页面
				foreach ($details as $url_key=>$detail) {
					$detail = _prefilter($detail);
					//debug
					// exit($detail);
					
					foreach ($detail_rules as $key => $value) {
						
						preg_match_all('/'.str_replace('/', '\/', addslashes($value)).'/i',$detail,$detailArr);
						
						//处理特殊这段信息
						switch ($key) {
							case 'product_image':
								$data[$key] = "images/".md5($detailArr[1][0]).".jpg";
								if(!file_exists($data[$key])){
									$images_urls[$data[$key]] = $base.$detailArr[1][0];
								}
								break;
							case 'product_description':
								$data[$key] = trim(strip_tags($detailArr[1][0]));
								break;
							default:
								$data[$key] = $detailArr[1][0];
								break;
						}
										
					}
					
					
					//产品url			
					$data['product_url'] = $url_key;

					//转义采集后的数据
					foreach ($data as $_k => $_v) {
						$data[$_k] = addslashes($_v);
					}

					//入库
					$r = mysql_query("
					insert into `content` values(
					null,
					'{$data['meta_title']}',
					'{$data['meta_keywords']}',
					'{$data['meta_description']}',
					'{$data['product_name']}',
					'{$data['product_image']}',
					'{$data['product_price']}',
					'{$data['product_description']}',
					'{$data['product_url']}')");

					//打印log
					_flush($j++."|".$r."|".$data['product_name']."<br/>");
					//_flush($data);
				}
				//远程图片本地化
				$mp->set_urls($images_urls);
				$images = $mp->start();
				foreach ((array)$images as $image_key => $image_value) {
					if (!empty($image_key)) {
						_flush("store image:".$image_key."<br/>");
						file_put_contents($image_key,$image_value);
					}
				}
				//清空内容url并加入本次循环url。不然本次会被跳过
				$content_url = $base.$detail_value;
				$detail_urls = array($content_url=>$content_url);
			}
		}
		//清空内容url并加入本次循环url。不然本次会被跳过
		if ($i == $pages[1] && ($pages[1] - $i) % $limit > 0) {
			$last_page = 1;
		}
		$urls = array(preg_replace('/\[(.*)\]/i',$i,$link));
	}
}

// 输出日志
function _flush($msg) {
	print_r ($msg);
	ob_flush();
	flush();
}

// 对抓去到的内容做简单过滤（过滤空白字符，便于正则匹配）
function _prefilter($output) {
	$output=preg_replace("/\/\/[\S\f\t\v ]*?;[\r|\n]/", "", $output);
	$output=preg_replace("/\<\!\-\-[\s\S]*?\-\-\>/", "", $output);
	$output=preg_replace("/\>[\s]+\</", "><", $output);
	$output=preg_replace("/;[\s]+/", ";", $output);
	$output=preg_replace("/[\s]+\}/", "}", $output);
	$output=preg_replace("/}[\s]+/", "}", $output);
	$output=preg_replace("/\{[\s]+/", "{", $output);
	$output=preg_replace("/([\s]){2,}/", "$1", $output);
	$output=preg_replace("/[\s]+\=[\s]+/", "=", $output);
	return $output;
}
?>