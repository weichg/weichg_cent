<?php
/**
 * @copyright www.weichg.com
 * @author Liusha.
 * @var unknown
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

include_once('includes/cls_json.php');
$json  = new JSON;

$http_raw_post = file_get_contents("php://input", "r");
$request = $json->decode($http_raw_post, 1);

$response = array("code"=>"0","msg"=>"OK","protocol_ver"=>"{$request['protocol_ver']}","protocol_no"=>"{$request['protocol_no']}","data"=>array());

if ($request['protocol_no'] == "1") {
	try {
		$shopCityNo = $request["data"]["city_no"];
		
		$sql = "SELECT shop_id, shop_name FROM " . $ecs->table('users') . " WHERE shop_city_no = '$shopCityNo'";
		
		$res = $db->query($sql);
		while ($shop = $db->fetchRow($res)) {
			$response["data"][] = array("id"=>"{$shop['shop_id']}", "name"=>"{$shop['shop_name']}");
		}
		
		die($json->encode($response));
		
	} catch (Exception $e) {
		$response['code'] = "1";
		$response['msg'] = "error";
		die($json->encode($response));
	}
} else if ($request['protocol_no'] == "2") {
	try {
		$shopId = $request["data"]["id"];
		
		$sql = "SELECT shop_id, shop_url FROM " . $ecs->table('users') . " WHERE shop_id = '$shopId'";
		
		$res = $db->query($sql);
		while ($shop = $db->fetchRow($res)) {
		    $response["data"] = array("id"=>"{$shop['shop_id']}", "url"=>"{$shop['shop_url']}");
		}

		die($json->encode($response));
		
	} catch (Exception $e) {
		$response['code'] = "1";
		$response['msg'] = "error";
		die($json->encode($response));
	}
	
} else if ($request['protocol_no'] == "3") {
	try {
		$shopId = $request["data"]["id"];
		
		$sql = "SELECT shop_id, shop_alipay_parterID, shop_alipay_sellerID, shop_alipay_RSAPublic, shop_alipay_RSAPrivate, shop_alipay_callUrl FROM " . $ecs->table('users') . " WHERE shop_id = '$shopId'";
		
		$res = $db->query($sql);
		while ($shop = $db->fetchRow($res)) {
		    $checkSum = md5($shop['shop_id'] . $shop['shop_alipay_parterID'] . $shop['shop_alipay_sellerID'] . $shop['shop_alipay_RSAPublic'] . $shop['shop_alipay_RSAPrivate'] . $shop['shop_alipay_callUrl']);
		    $response["data"] = array("id"=>"{$shop['shop_id']}", "parterID"=>"{$shop['shop_alipay_parterID']}", "sellerID"=>"{$shop['shop_alipay_sellerID']}", "RSAPublic"=>"{$shop['shop_alipay_RSAPublic']}", "RSAPrivate"=>"{$shop['shop_alipay_RSAPrivate']}", "callUrl"=>"{$shop['shop_alipay_callUrl']}", "checkSum"=>"$checkSum");
		}
		
		die($json->encode($response));
		
	} catch (Exception $e) {
		$response['code'] = "1";
		$response['msg'] = "error";
		die($json->encode($response));
	}
	
} else {
	$response['code'] = "1";
	$response['msg'] = "protocol_no not match";
	die($json->encode($response));
}
