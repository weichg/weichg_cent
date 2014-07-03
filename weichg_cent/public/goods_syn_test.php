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
		$shop = $db->getOne($sql);

		//获得商品信息
		$sql = "SELECT * FROM weic_goods WHERE goods_id = " . $goodsId;
		$row = $db->fetchRow($db->query($sql));

		//同步商品信息到子商城
		$goods = array();
		$goods['goods_id'] = $goodsId;
		$goods['goods_name'] = str_replace("'", "\'", $row['goods_name']);
		$goods['goods_desc'] = str_replace("'", "\'", $row['goods_desc']);
		$goods['brand_id'] = $row['brand_id'];
		$goods['cat_id'][] = array();
		
		$catId = $row['cat_id'];
		while ($catId) {
			$sql = "SELECT * FROM weic_category WHERE cat_id=" . $catId;
			$cat = $db->fetchRow($db->query($sql));
		
			$catId = $cat['parent_id'];
		}
		
		
		//打包压缩图片
		include_once('includes/cls_phpzip.php');
		$zip = new PHPZip;
		if (!empty($row['original_img']) && is_file(ROOT_PATH . $row['original_img']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['original_img']), $row['original_img']);
		}
		
		//同步商品图片
		$sql = "SELECT * FROM weic_goods_gallery WHERE goods_id=" . $goodsId;
		$res = $db->query($sql);
		while ($g = $db->fetchRow($res)) {
			if (!empty($g['img_original']) && is_file(ROOT_PATH . $g['img_original']))
			{
				$zip->add_file(file_get_contents(ROOT_PATH . $g['img_original']), $g['img_original']);
			}
		}
		
		$zipPath = 'temp/goods_syn/' . $userId . '/';
		$zipFileName = time() . '.zip';
		mkdir($zipPath);
		
		$out = $zip -> file();
		$fp = fopen($zipPath . $zipFileName, 'wb');
		fwrite($fp, $out, strlen($out));
		fclose($fp);
		
		$zipFile = realpath($zipPath . $zipFileName);
		$fields['zip'] = '@' . $zipFile;
		$fields['checkSn'] = md5("10.162.48.225" . $shop_ip);
		$fields['directory'] = "wchgImg";
		
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
