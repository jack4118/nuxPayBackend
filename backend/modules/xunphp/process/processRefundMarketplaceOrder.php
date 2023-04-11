<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";
// include_once $currentPath . "/../include/class.xun_currency.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);

$advertisement_id = "289";
$db->where("id", $advertisement_id);
$advertisement = $db->getOne("xun_marketplace_advertisement");
if(!$advertisement){
    echo "Invalid advertisement id";
    return;
}

$order_id = "10014601";
// $username = "+60123456780";

$advertisement_order_table = $xunMarketplace->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

$db->where("order_id", $order_id);
$db->orderBy("created_at", "DESC");
$advertisement_order = $db->getOne($advertisement_order_table);

if(!$advertisement_order){
    echo "Invalid order id";

    return;
}

$order_quantity = $advertisement_order["quantity"];
$order_currency = $advertisement_order["currency"];
$user_id = $advertisement_order["user_id"];
$db->where("id", $user_id);
$xun_user = $db->getOne("xun_user", "id, username, nickname");
$username = $xun_user["username"];

$update_data = [];
$update_data["updated_at"] = date("Y-m-d H:i:s");
$update_data["status"] = "refund";

$db->where("id", $advertisement_order["id"]);
$db->update($advertisement_order_table, $update_data);

echo "advertisement_id $advertisement_id ## order_id $order_id ## username $username ## order_quantity $order_quantity ## order_currency $order_currency";


$ret = $xunMarketplace->escrow_fund_out($advertisement_id, $order_id, $username, $order_quantity, $order_currency);
print_r($ret);




?>