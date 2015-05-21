<?php
/*

本程序用于将最新的百度空间的文章及相关图片转移到WordPress.

参考了网上的一些代码，在此感谢他们。

严重鄙视百度新版空间！！！

Auther: Luo Hui ( farmer.luo at gmail.com)
Blog：www.huilog.com
Date: 2012-07-23


使用：将本程序放到WordPress目录下，更改自己的百度用户名及cookie，命令行运行本脚本。

*/

require( dirname(__FILE__) . '/wp-load.php' );
require( dirname(__FILE__) . '/wp-admin/includes/taxonomy.php' );

class BaiduSpace {
	const MAIN_URL = 'http://hi.baidu.com/new/%s';
	const PAGE_URL = 'http://hi.baidu.com/new/%s?page=%d';
	const CONTENT_URL = 'http://hi.baidu.com/%s/item/%s';
	
	private $space = '';
	private $main_url;
    
    protected $baiduAccount;
    protected $cookie;
    protected $imageUrlProcessor;

    public function __construct( $account, $cookie = '' )
    {
        $this->baiduAccount = $account;
        $this->cookie = $cookie;
        
		$this->main_url = sprintf(BaiduSpace::MAIN_URL, $account);

		$this->space = $account;
        
        $upload_dir = wp_upload_dir();
        $filename = $upload_dir['basedir'] . '/' . 'baiduhi';
        if ( !file_exists( $filename ) && !mkdir( $filename ) )
            throw new Exception( "Create attachment dir failed!" );

        $importer = $this;

        $this->imageUrlProcessor = function( $matches ) use ( $importer )
        {
            $upload_dir = wp_upload_dir();

            $url = $matches[0];
            $filename = $matches[2];

            $retUrl = $upload_dir['baseurl'] . "/baiduhi/$filename";
            $savePath = $upload_dir['basedir'] . "/baiduhi/$filename";

            if ( file_exists( $filename ) )
                return $retUrl;

            $destFp = fopen( $savePath, 'w+' );

            try 
            {
                $importer->httpDownload( $url, $destFp );
            }
            catch ( Exception $e )
            {
                echo "ERROR: " . $e->getMessage() . "\n";
            }

            fclose( $destFp );

            return $retUrl;
        };
    }


	private function _fetch($url) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, false);
		curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_COOKIE, $this->cookie );
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_0 );
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , 5);
		curl_setopt($curl, CURLOPT_TIMEOUT , 5);
		curl_setopt($curl, CURLOPT_USERAGENT , "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:14.0) Gecko/20100101 Firefox/14.0");
		curl_setopt($curl, CURLOPT_REFERER, "http://hi.baidu.com");
		$content = curl_exec($curl);
		curl_close($curl);
		
		return $content;
	}
	
	private function get_sub_content($content, $startstr, $endstr) {
		$len = strlen($startstr);
		
        if ( $startstr == '' ) {
            $start = 0;
        } else {
        	$start = strpos($content, $startstr);
        }
        
		$end = strpos($content, $endstr, $start);
        
		//echo $startstr, $start, '---', $end, '<br />';
        if ( $end ) {
            return substr($content, $start + $len, $end - $start - $len);
        } else {
            return $content;
        }
        
	}
	
	public function GetMainPage() {
        global $wpdb;
        
		$content = $this->_fetch($this->main_url);
        
		$itemsize = (int)$this->get_sub_content($content, "allCount : '", "',");
		$itempage = (int)$this->get_sub_content($content, "pageSize : '", "',");
		$cpage = ceil($itemsize / $itempage);
		
        echo "allCount=", $itemsize, "\n";
		$allitem = array();
        
		preg_match_all ("|<span class=box-act data-blogid=\"(.*)\" data-actor-portrait|U", $content, $out, PREG_PATTERN_ORDER);
		$allitem = array_merge($allitem, $out[1]);

		for($i = 2; $i <= $cpage; $i++) {
			$pageurl = sprintf(BaiduSpace::PAGE_URL, $this->space, $i);

			$pagecontent = $this->_fetch($pageurl);
			preg_match_all ("|<span class=box-act data-blogid=\"(.*)\" data-actor-portrait|U", $pagecontent, $out, PREG_PATTERN_ORDER);
			//var_dump($out);
			$allitem = array_merge($allitem, $out[1]);
		}
        
        echo "allitem count=",count($allitem);

		$article = array();
		foreach($allitem as $item) {
			//$content_url = sprintf(BaiduSpace::CONTENT_URL, $this->space,$item);
            $content_url = sprintf(BaiduSpace::CONTENT_URL, $this->space,$item);
            //echo  "<\ br> content_url =" . $content_url ;
			
			$content = $this->_fetch($content_url);
            //echo "content_url=",$content_url;
            $article['date'] =  $this->get_sub_content($content,
												'<div class=content-other-info> <span>', 
												'</span> </div>   <h2 class="title content-title">');
            
            $article['title'] =  $this->get_sub_content($content,
												'<h2 class="title content-title">', 
												'</h2>  </div> <div id=content class="content text-content clearfix">');

			$article['text'] = $this->get_sub_content($content,
												'<div id=content class="content text-content clearfix">', 
												'<div class="mod-post-info clearfix">');
            $article['text'] = substr( $article['text'], 0, strlen($article['text'])-10 );
            
            $article['text'] = preg_replace_callback( '#http://([a-z]).hiphotos.baidu.com/space/pic/item/(.*\.jpg)#Ui', $this->imageUrlProcessor, $article['text'] );
            
            $article['text'] = preg_replace_callback( '#http://hiphotos.baidu.com/(.*)/pic/item/(.*\.jpg)#Ui', $this->imageUrlProcessor, $article['text'] );
            
			$article['category'] = $this->get_sub_content($content,
												'">#', 
												'</a>  </div> <div class=op-box> <span class=pv>浏览');
                                                
			$article['category'] = $this->get_sub_content($article['category'],
												'', 
												'</a>  <a class=tag');
                                                
            if ( strlen($article['category']) > 100 )
                $article['category'] = "默认分类";
            
            //echo ' date', $article['date'], " title:", $article['title'] , "\n";
            echo ' category=', $article['category'], " title:", $article['title'] , "\n";
            
            //$article['category'] = '默认分类';
            
            //print_r($article);
            
            $catId = $this->getCatId( $article['category'] );

            $wp_post = array(
              'post_title' => $article['title'],
              'post_content' => $article['text'],
              'post_status' => 'publish',
              'post_author' => 1,
              'post_category' => array( $catId )
            );

            // Insert the post into the database
            $post_id = wp_insert_post( $wp_post );
            $where = array( 'ID' => $post_id );

            $wpdb->update( $wpdb->posts, array( 'post_date' => $article['date'] . ':00','post_date_gmt' => $article['date'] . ':00' ), $where );

            add_post_meta( $post_id, 'source_url', $$content_url );

            echo "OK: $article[title] -> [$post_id]\n";
            
		}
	}
    
    public function httpDownload( $url, $destFp )
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_FILE, $destFp );
        curl_setopt( $ch, CURLOPT_COOKIE, $this->cookie );

        $ret = curl_exec( $ch );
        $error = curl_error( $ch );

        curl_close($ch);
        unset( $ch );

        if ( !empty( $error ) )
            throw new Exception( $error );

        return $ret;
    }
    
    
    public function getCatId( $cat_name )
    {
        $catId = 1;

        if ( $cat_name != '默认分类' )
        {
            $catId = get_cat_ID( $cat_name );

            if ( !$catId )
                $catId = wp_create_category( $cat_name );
        }

        return $catId;
    }
  
}
/*
BaiduSpace($username,$cookie)
$usernaem 百度用户名
$cookie 百度cookie,可以不要，不要时不公开的文章将不能转过去

cookie需要BAIDUID,BDUSS,BDSP,BDSPINFO,BDSTAT

如：
BAIDUID=9ABABA6C29F3459B1:FG=1;BDUSS=HpqVG1Y4AZmF4yMkyBBlBMgQZQd;BDSP=374e3d9793f080026923;BDSPINFO=337420aebf;BDSTAT=5a140c2b45897da40ad162d9803938f2148

*/
$baidu = new BaiduSpace('test','');
$baidu->GetMainPage();