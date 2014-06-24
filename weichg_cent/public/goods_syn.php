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
		$db_city = new cls_mysql($shop_ip, 'weichg', 'Weichg_Syn@weichg.com', 'weichg_city');

		//获得商品信息
		$sql = "SELECT * FROM weic_goods WHERE goods_id = " . $goodsId;
		$row = $db->fetchRow($db->query($sql));

		//同步商品信息到子商城
		$row['goods_name'] = str_replace("'", "\'", $row['goods_name']);
		$row['goods_brief'] = str_replace("'", "\'", $row['goods_brief']);
		$row['goods_desc'] = str_replace("'", "\'", $row['goods_desc']);

		if ($db_city->getOne($sql)) {
			$sql = "UPDATE weic_goods SET cat_id={$row['cat_id']},goods_sn='{$row['goods_sn']}',goods_name='{$row['goods_name']}',"
					. "goods_name_style='{$row['goods_name_style']}',brand_id={$row['brand_id']},provider_name='{$row['provider_name']}',goods_weight={$row['goods_weight']},purchase_price={$row['shop_price']},market_price={$row['market_price']},"
					. "keywords='{$row['keywords']}',goods_brief='{$row['goods_brief']}',goods_desc='{$row['goods_desc']}',goods_thumb='{$row['goods_thumb']}',goods_img='{$row['goods_img']}',original_img='{$row['original_img']}',"
					. "add_time={$row['add_time']},last_update={$row['last_update']},is_delete=0 WHERE goods_id=" . $goodsId;
			
		} else {
			$sql = "INSERT INTO weic_goods(goods_id,cat_id,goods_sn,goods_name,"
					. "goods_name_style,brand_id,provider_name,goods_weight,purchase_price,market_price,"
					. "keywords,goods_brief,goods_desc,goods_thumb,goods_img,original_img,"
					. "add_time,last_update) VALUES({$row['goods_id']},{$row['cat_id']},'{$row['goods_sn']}','{$row['goods_name']}',"
					. "'{$row['goods_name_style']}',{$row['brand_id']},'{$row['provider_name']}',{$row['goods_weight']},{$row['shop_price']},"
	                . "{$row['market_price']},'{$row['keywords']}','{$row['goods_brief']}','{$row['goods_desc']}',"
	                . "'{$row['goods_thumb']}','{$row['goods_img']}','{$row['original_img']}',{$row['add_time']},{$row['last_update']})";
		}
		$db_city->query($sql);
		
		$catId = $row['cat_id'];
		while ($catId) {
			$sql = "SELECT * FROM weic_category WHERE cat_id=" . $catId;
		    $cat = $db->fetchRow($db->query($sql));
			if ($db_city->getOne($sql)) {
				$sql = "UPDATE weic_category SET cat_id={$cat['cat_id']},cat_name='{$cat['cat_name']}',"
						. "keywords='{$cat['keywords']}',cat_desc='{$cat['cat_desc']}',parent_id={$cat['parent_id']},"
						. "sort_order={$cat['sort_order']},template_file='{$cat['template_file']}',measure_unit='{$cat['measure_unit']}',"
						. "show_in_nav={$cat['show_in_nav']},style='{$cat['style']}',is_show={$cat['is_show']},"
				        . "grade={$cat['grade']},filter_attr='{$cat['filter_attr']}' WHERE cat_id=" . $catId;
			} else {
				$sql = "INSERT INTO weic_category VALUES({$cat['cat_id']},'{$cat['cat_name']}','{$cat['keywords']}',"
						. "'{$cat['cat_desc']}',{$cat['parent_id']},{$cat['sort_order']},'{$cat['template_file']}',"
						. "'{$cat['measure_unit']}',{$cat['show_in_nav']},'{$cat['style']}',{$cat['is_show']},{$cat['grade']},"
						. "'{$cat['filter_attr']}')";
			}
			$db_city->query($sql);
			$catId = $cat['parent_id'];
		}
		
		$brandId = $row['brand_id'];
		$sql = "SELECT * FROM weic_brand WHERE brand_id=" . $brandId;
		$brand = $db->fetchRow($db->query($sql));
		if ($db_city->getOne($sql)) {
			$sql = "UPDATE weic_brand SET brand_id={$brand['brand_id']},brand_name='{$brand['brand_name']}',"
					. "brand_logo='{$brand['brand_logo']}',brand_desc='{$brand['brand_desc']}',"
					. "site_url='{$brand['site_url']}',sort_order={$brand['sort_order']},"
					. "is_show={$brand['is_show']} WHERE brand_id=" . $brandId;
		} else {
			$sql = "INSERT INTO weic_brand VALUES({$brand['brand_id']},'{$brand['brand_name']}',"
					. "'{$brand['brand_logo']}','{$brand['brand_desc']}','{$brand['site_url']}',"
					. "{$brand['sort_order']},{$brand['is_show']})";
		}
		$db_city->query($sql);
		
		include_once('includes/cls_phpzip.php');
		$zip = new PHPZip;
		//压缩图片
		if (!empty($row['goods_img']) && is_file(ROOT_PATH . $row['goods_img']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['goods_img']), $row['goods_img']);
		}
		if (!empty($row['original_img']) && is_file(ROOT_PATH . $row['original_img']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['original_img']), $row['original_img']);
		}
		if (!empty($row['goods_thumb']) && is_file(ROOT_PATH . $row['goods_thumb']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['goods_thumb']), $row['goods_thumb']);
		}
		
		$db_city->query("DELETE FROM weic_goods_gallery WHERE goods_id=" . $goodsId);
		$sql = "SELECT * FROM weic_goods_gallery WHERE goods_id=" . $goodsId;
		$res = $db->query($sql);
		while ($g = $db->fetchRow($res)) {
			
			$sql = "INSERT INTO weic_goods_gallery VALUES({$g['img_id']},{$g['goods_id']},'{$g['img_url']}','{$g['img_desc']}','{$g['thumb_url']}','{$g['img_original']}')";
			$db_city->query($sql);
			
			if (!empty($g['img_url']) && is_file(ROOT_PATH . $g['img_url']))
			{
				$zip->add_file(file_get_contents(ROOT_PATH . $g['img_url']), $g['img_url']);
			}
			if (!empty($g['thumb_url']) && is_file(ROOT_PATH . $g['thumb_url']))
			{
				$zip->add_file(file_get_contents(ROOT_PATH . $g['thumb_url']), $g['thumb_url']);
			}
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
		
		if (!intval(file_get_contents("http://localhost:8080/synchro/customer/customers/page?userId=$userId&shop_ip=$shop_ip"))) {
			$result['error'] = 0;
			$result['message'] = '同步成功！';
			die($json->encode($result));
		} else {
			$result['error'] = 2;
			$result['message'] = '同步失败，请重试！';
			die($json->encode($result));
		}
		
	}
	catch (Exception $e) {
		$result['error'] = 2;
		$result['message'] = '同步失败，请重试！';
		die($json->encode($result));
	}
}

//end