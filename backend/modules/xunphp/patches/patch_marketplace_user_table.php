<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);

$date = date("Y-m-d H:i:s");

$advertisements = $db->get("xun_marketplace_advertisement");

foreach ($advertisements as $advertisement) {
    $table_name = $xunMarketplace->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

    if ($db->tableExists($table_name)) {

        $db->where("advertisement_id", $advertisement["id"]);
        $db->where("order_type", "place_order");
        $db->where("disabled", 0);
        $db->where("status", "completed");

        $advertisement_orders = $db->get($table_name);

        foreach ($advertisement_orders as $advertisement_order) {
            $order_id = $advertisement_order["order_id"];
            $db->where("order_id", $order_id);
            $db->where("status", "completed", "!=");
            $order_cache = $db->getOne("xun_marketplace_advertisement_order_cache");

            if ($order_cache) {
                $update_data = [];
                $update_data["updated_at"] = $date;
                $update_data["status"] = "completed";
                $db->where("id", $order_cache["id"]);
                $db->update("xun_marketplace_advertisement_order_cache", $update_data);
            }
        }
    }
}

// patch xun_marketplace_user

$db->groupBy("user_id");
$ad_user_ids = $db->getValue("xun_marketplace_advertisement", "user_id", null);

$db->groupBy("user_id");
$order_user_id = $db->getValue("xun_marketplace_advertisement_order_cache", "user_id", null);

$user_ids = array_unique(array_merge($ad_user_ids, $order_user_id));

foreach ($user_ids as $user_id) {
    $db->where("user_id", $user_id);
    $user_marketplace = $db->getOne("xun_marketplace_user");

    $db->where("user_id", $user_id);
    $user_rating = $db->getValue("xun_marketplace_user_rating", "avg(rating)");
    $user_rating = $user_rating ? round($user_rating, 2) : 0;

    // total trade
    $db->where("user_id", $user_id);
    $db->where("status", "completed");
    $order_count = $db->getValue("xun_marketplace_advertisement_order_cache", "count(id)");

    $db->where("user_id", $user_id);
    $user_advertisements = $db->get("xun_marketplace_advertisement");

    $advertisement_order_count = 0;
    foreach ($user_advertisements as $advertisement) {
        $table_name = $xunMarketplace->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

        if ($db->tableExists($table_name)) {
            $db->where("advertisement_id", $advertisement["id"]);
            $db->where("order_type", "place_order");
            $db->where("disabled", 0);
            $db->where("status", "completed");
            $ad_order_count = $db->getValue($table_name, "count(DISTINCT(order_id))");
            
            $advertisement_order_count += $ad_order_count;
        }
    }

    $total_trade = $order_count + $advertisement_order_count;

    if (!$user_marketplace) {
        $insert_data = array(
            "user_id" => $user_id,
            "total_trade" => $total_trade,
            "avg_rating" => $user_rating,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $db->insert("xun_marketplace_user", $insert_data);

    } else {
        $update_data = [];
        $update_data["total_trade"] = $total_trade;
        $update_data["avg_rating"] = $user_rating;
        $update_data["updated_at"] = $date;

        $db->where("id", $user_marketplace["id"]);
        $db->update("xun_marketplace_user", $update_data);
    }
}
