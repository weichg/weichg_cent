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
		$db_city = new cls_mysql($shop_ip, 'admin', 'JiaoChun_315_hui', 'weichg_cart');

		//获得商品信息
		$sql = "SELECT * FROM weic_goods WHERE goods_id = " . $goodsId;
		$row = $db->fetchRow($db->query($sql));

		//同步商品信息到子商城
		$row['goods_name'] = str_replace("'", "\'", $row['goods_name']);
		$row['goods_desc'] = str_replace("'", "\'", $row['goods_desc']);

		$sql = "SELECT * FROM weic_product WHERE product_id = " . $goodsId;
		
		include_once('includes/cls_phpzip.php');
		$zip = new PHPZip;
		//压缩图片
		if (!empty($row['original_img']) && is_file(ROOT_PATH . $row['original_img']))
		{
			$zip->add_file(file_get_contents(ROOT_PATH . $row['original_img']), $row['original_img']);
		}
		
		//同步商品数据
		$row['original_img'] = "data/wchgImg/" . $row['original_img'];
		if ($db_city->getOne($sql)) {
			$sql = "UPDATE weic_product SET model='{$row['goods_sn']}',cost={$row['shop_price']},stock_status_id=5,image='{$row['original_img']}',manufacturer_id={$row['brand_id']},date_modified=Now() WHERE product_id=" . $goodsId;
		} else {
			$sql = "INSERT INTO weic_product(product_id,model,stock_status_id,image,manufacturer_id,cost,date_added,date_modified) VALUES({$row['goods_id']},'{$row['goods_sn']}',5,'{$row['original_img']}',{$row['brand_id']},{$row['shop_price']},Now(),Now())";
		}
		$db_city->query($sql);
		
		$sql = "SELECT * FROM weic_product_description WHERE language_id=2 AND product_id = " . $goodsId;
		if ($db_city->getOne($sql)) {
			$sql = "UPDATE weic_product_description SET name='{$row['goods_name']}',description='{$row['goods_desc']}' WHERE language_id=2 AND product_id=" . $goodsId;
		} else {
			$sql = "INSERT INTO weic_product_description(product_id,language_id,name,description,meta_description,meta_keyword,tag) VALUES({$row['goods_id']},2,'{$row['goods_name']}','{$row['goods_desc']}','','','')";
		}
		$db_city->query($sql);
		
		//同步商品分类
		$catId = $row['cat_id'];
		while ($catId) {
			$sql = "SELECT * FROM weic_category WHERE cat_id=" . $catId;
			$cat = $db->fetchRow($db->query($sql));
			
			$sql = "SELECT * FROM weic_category WHERE category_id=" . $catId;
			if ($db_city->getOne($sql)) {
				$sql = "UPDATE weic_category SET parent_id={$cat['parent_id']},sort_order={$cat['sort_order']},status=1,date_modified=Now() WHERE category_id=" . $catId;
			} else {
				$sql = "INSERT INTO weic_category VALUES({$cat['cat_id']},'',{$cat['parent_id']},0,0,{$cat['sort_order']},1,Now(),Now())";
			}
			
			$db_city->query($sql);
			
			$sql = "SELECT * FROM weic_category_description WHERE category_id=" . $catId;
			if ($db_city->getOne($sql)) {
				$sql = "UPDATE weic_category_description SET language_id=2,name='{$cat['cat_name']}',description='{$cat['cat_desc']}',meta_keyword='{$cat['keywords']}' WHERE category_id=" . $catId;
			} else {
				$sql = "INSERT INTO weic_category_description VALUES({$cat['cat_id']},2,'{$cat['cat_name']}','{$cat['cat_desc']}','','{$cat['keywords']}')";
			}
				
			$db_city->query($sql);
			$catId = $cat['parent_id'];
		}
		$sql = "SELECT * FROM weic_product_to_category WHERE category_id={$row['cat_id']} AND product_id=" . $goodsId;
		if (!$db_city->getOne($sql)) {
			$sql = "INSERT INTO weic_product_to_category VALUES($goodsId,{$row['cat_id']})";
		}
		$db_city->query($sql);
		
		//同步商品品牌数据
		$brandId = $row['brand_id'];
		$sql = "SELECT * FROM weic_brand WHERE brand_id=" . $brandId;
		$brand = $db->fetchRow($db->query($sql));
		
		$sql = "SELECT * FROM weic_manufacturer WHERE manufacturer_id=" . $brandId;
		if ($db_city->getOne($sql)) {
			$sql = "UPDATE weic_manufacturer SET name='{$brand['brand_name']}', image='{$brand['brand_logo']}', sort_order={$brand['sort_order']} WHERE manufacturer_id=" . $brandId;
		} else {
			$sql = "INSERT INTO weic_manufacturer VALUES({$brand['brand_id']},'{$brand['brand_name']}','{$brand['brand_logo']}',{$brand['sort_order']})";
		}
		$db_city->query($sql);
		
		$sql = "SELECT * FROM weic_manufacturer_to_store WHERE store_id=0 AND manufacturer_id=" . $brandId;
		if (!$db_city->getOne($sql)) {
			$sql = "INSERT INTO weic_manufacturer_to_store VALUES({$brand['brand_id']},0)";
		}
		$db_city->query($sql);

		//同步商品图片
		$db_city->query("DELETE FROM weic_product_image WHERE product_id=" . $goodsId);
		$sql = "SELECT * FROM weic_goods_gallery WHERE goods_id=" . $goodsId;
		$res = $db->query($sql);
		while ($g = $db->fetchRow($res)) {
			if (!empty($g['img_original']) && is_file(ROOT_PATH . $g['img_original']))
			{
				$zip->add_file(file_get_contents(ROOT_PATH . $g['img_original']), $g['img_original']);
			}
			$g['img_original'] = "data/wchgImg/" . $g['img_original'];
			$sql = "INSERT INTO weic_product_image(product_id,image) VALUES({$g['goods_id']},'{$g['img_original']}')";
			$db_city->query($sql);
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