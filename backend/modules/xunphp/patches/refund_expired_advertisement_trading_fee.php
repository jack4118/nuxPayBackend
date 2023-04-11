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

$db->where("id", 90, ">=");
$db->where("status", "expired");
$advertisement_arr = $db->get("xun_marketplace_advertisement");

foreach ($advertisement_arr as $advertisement){
    // if either new_advertisement = refunded and another is completed then refund the completed one
    $advertisement_id = $advertisement["id"];
    
    $expires_at = $advertisement["expires_at"];
    $created_at = $advertisement["created_at"];

    $expires_at_ts = strtotime($expires_at);
    $created_at_ts = strtotime($created_at);

    if(($expires_at_ts - $created_at_ts) == 300){
        // escrow expire
        $advertisement_order_table = $xunMarketplace->get_advertisement_order_transaction_table_name($created_at);

        $db->where("advertisement_id", $advertisement_id);
        $db->where("order_type", ["new_advertisement", "advertisement_trading_fee"], "in");
        $advertisement_orders = $db->get($advertisement_order_table);

        foreach ($advertisement_orders as $advertisement_order){
            $order_status = $advertisement_order["status"];

            if($order_status == "completed"){
                $order_id = $advertisement_order["order_id"];
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
            }
        }
    }
}

?>