<?php
/**
 * @copyright www.weichg.com
 * @author Liusha.
 * @var unknown
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- INPUT
/*------------------------------------------------------ */

$goodsId = isset($_REQUEST['id'])  ? intval($_REQUEST['id']) : 0;

if($_REQUEST['act'] == 'goods_syn') {
	
	include_once('includes/cls_json.php');
	$result = array('error' => 0, 'message' => '');
	$json  = new JSON;
	
	if ($_SESSION['user_id'] == 0)
	{
		/* 用户没有登录，转向到登录页面 */
		$result['error'] = 1;
		$result['message'] = '请先登录！';
		die($json->encode($result));
	}
	else {
		$userId = $_SESSION['user_id'];
	}
	
	try {
		//获得子商城数据库IP
		$sql = "SELECT shop_ip FROM " . $ecs->table('users') . " WHERE user_id=" . $_SESSION['user_id'];
		$shop_ip = $db->getOne($sql);

		//获得商品信息
		$sql = "SELECT * FROM weic_goods WHERE goods_id = " . $goodsId;
		$row = $db->fetchRow($db->query($sql));

		//同步商品信息到子商城
		$goods_img_directory = "data/wchgImg/";
		$goods = array();
		$goods['goods_id'] = $goodsId;
		$goods['goods_sn'] = $row['goods_sn'];
		$goods['goods_name'] = $row['goods_name'];
		$goods['goods_desc'] = $row['goods_desc'];
		$goods['goods_keywords'] = $row['keywords'];
		$goods['goods_original_img'] = $goods_img_directory . $row['original_img'];
		$goods['goods_shop_price'] = $row['shop_price'];
		$goods['goods_cat'] = array();
		$goods['goods_images'] = array();
		
		$catId = $row['cat_id'];
		while ($catId) {
			$sql = "SELECT cat_id,cat_name,keywords,cat_desc,parent_id FROM weic_category WHERE cat_id=" . $catId;
			$cat = $db->fetchRow($db->query($sql));
			$r = array();
			foreach ($cat as $key=>$value) {
				$r[$key] = $value;
			}
			$goods['goods_cat'][] = $r;
			$catId = $cat['parent_id'];
		}
		
		$sql = "SELECT brand_id,brand_name,brand_logo FROM weic_brand WHERE brand_id=" . $row['brand_id'];
		$brands = $db->fetchRow($db->query($sql));
		$goods['goods_brand_id'] = $brands['brand_id'];
		$goods['goods_brand_name'] = $brands['brand_name'];
		$goods['goods_brand_logo'] = $goods_img_directory . $brands['brand_logo'];
		
		//打包压缩图片
		include_once('includes/cls_phpzip.php');
		$zip = new PHPZip;
		if (!empty($row['original_img']) && is_file(ROOT_PATH . $row['original_img']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['original_img']), $row['original_img']);
		}
		
		//同步商品图片
		$sql = "SELECT goods_id,img_original FROM weic_goods_gallery WHERE goods_id=" . $goodsId;
		$res = $db->query($sql);
		while ($g = $db->fetchRow($res)) {
			if (!empty($g['img_original']) && is_file(ROOT_PATH . $g['img_original']))
			{
				$zip->add_file(file_get_contents(ROOT_PATH . $g['img_original']), $g['img_original']);
			}
			$img = array();
			$img['goods_id'] = $g['goods_id'];
			$img['img_original']= $goods_img_directory . $g['img_original'];
			$goods['goods_images'][] = $img;
		}
		
		//生成本地图片压缩包
		$zipPath = 'temp/goods_syn/' . $userId . '/';
		$zipFileName = time() . '.zip';
		mkdir($zipPath);
		
		$out = $zip -> file();
		$fp = fopen($zipPath . $zipFileName, 'wb');
		fwrite($fp, $out, strlen($out));
		fclose($fp);
		
		//POST同步信息给子商城
		$zipFile = realpath($zipPath . $zipFileName);
		$fields['zip'] = '@' . $zipFile;
		$fields['checkSn'] = md5("10.162.48.225" . $shop_ip);
		$fields['directory'] = $goods_img_directory;
		$fields['data'] = urlencode($json->encode($goods));
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,"http://$shop_ip/index.php?route=product/product_syn");
		curl_setopt($ch, CURLOPT_POST, 1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		$result = curl_exec($ch);
		curl_close($ch);
		
		if (file_exists($zipFile)) {
			@unlink($zipFile);
		}
		die($result);
		
	}
	catch (Exception $e) {
		$result['error'] = 2;
		$result['message'] = '同步失败，请重试！';
		die($json->encode($result));
	}
}

//end
