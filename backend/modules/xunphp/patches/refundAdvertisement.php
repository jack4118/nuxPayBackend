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

$advertisement_id = "";
$db->where("id", $advertisement_id);
$advertisement = $db->getOne("xun_marketplace_advertisement");

if(!$advertisement){
    return;
}
$created_at = $advertisement["created_at"];
// escrow expire
$advertisement_order_table = $xunMarketplace->get_advertisement_order_transaction_table_name($created_at);

$advertisement_quantity = $advertisement["quantity"];
$user_id = $advertisement["user_id"];

$db->where("id", $user_id);
$xun_user = $db->getOne("xun_user", "id, username, nickname");
$username = $xun_user["username"];

$db->where("advertisement_id", $advertisement_id);
$db->where("disabled", 0);
$db->where("order_type", "new_advertisement");
$advertisement_order = $db->getOne($advertisement_order_table);

if(!$advertisement_order) return;

$update_data = [];
$update_data["updated_at"] = date("Y-m-d H:i:s");
$update_data["status"] = "refund";

$db->where("id", $advertisement_order["id"]);
$db->update($advertisement_order_table, $update_data);
$order_id = $advertisement_order["order_id"];
$order_quantity = $advertisement_order["quantity"];
$order_currency = $advertisement_order["currency"];
echo "advertisement_id $advertisement_id ## order_id $order_id ## username $username ## order_quantity $order_quantity ## order_currency $order_currency";

$ret = $xunMarketplace->escrow_fund_out($advertisement_id, $order_id, $username, $order_quantity, $order_currency);

?>