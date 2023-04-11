<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.xun_currency.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);
$xunXmpp = new XunXmpp($db, $post);
$xunCurrency = new XunCurrency($db);

$process_id = getmypid();

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start process check advertisement expiration\n");

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

while (true) {
    $date = date("Y-m-d H:i:s");

    $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();

// refund expired advertisements
    // refund failed advertisements (trading fee)
    $db->where("status", ["new", "pending_escrow"], "in");
    $db->where("expires_at < NOW()");
// $db->where("expires_at", date("Y-m-d H:i:s"), "<");

    $expired_ads = $db->get("xun_marketplace_advertisement");
    $update_ad_data = [];
    $update_ad_data["updated_at"] = date("Y-m-d H:i:s");
    $update_ad_data["status"] = "expired";

    $refund_total = 0;

    foreach ($expired_ads as $advertisement) {
        /**
         * escrow refund for sell ads and c2c buy ads
         */
        $db->where("id", $advertisement["id"]);
        $db->update("xun_marketplace_advertisement", $update_ad_data);

        if ($advertisement["status"] == "new") {
            // if ($advertisement["type"] == "sell" || ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"])) {
                $escrow_result = $xunMarketplace->escrow_refund_advertisement($advertisement);
                $refund_quantity = $escrow_result["refund_quantity"];
                $refund_total += $refund_quantity;
            // }
        } else {
            /**
             * select new_advertisement and trading fee order, refund if needed
             */
            $advertisement_order_table = $xunMarketplace->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

            // check if table exists
            if ($db->tableExists($advertisement_order_table)) {

                $db->where("advertisement_id", $advertisement["id"]);
                $db->where("disabled", 0);

                $advertisement_orders = $db->get($advertisement_order_table);
                $new_advertisement_order = array();
                $trading_fee_order = array();

                foreach ($advertisement_orders as $ad_order) {
                    if ($ad_order["order_type"] == "new_advertisement") {
                        $new_advertisement_order = $ad_order;
                    } else if ($ad_order["order_type"] == "trading_fee") {
                        $trading_fee_order = $ad_order;
                    }
                }

                if ($advertisement["type"] == "sell") {
                    if (!($new_advertisement_order["status"] == "completed" && $trading_fee_order["status"] == "completed")) {
                        $db->where("id", $advertisement["user_id"]);
                        $xun_user = $db->getOne("xun_user", "username");
                        $username = $xun_user["username"];

                        $update_order_data = [];
                        $update_order_data["updated_at"] = date("Y-m-d H:i:s");
                        $update_order_data["status"] = "refund";

                        if ($new_advertisement_order["status"] != "pre_escrow") {
                            // refund
                            $xunMarketplace->escrow_fund_out($advertisement["id"], $new_advertisement_order["order_id"], $username, $new_advertisement_order["quantity"], $new_advertisement_order["currency"]);
                            $db->where("id", $new_advertisement_order["id"]);
                            $db->update($advertisement_order_table, $update_order_data);

                        }
                        if ($trading_fee_order["status"] != "pre_escrow" && $trading_fee_order["quantity"] > 0) {
                            // refund
                            $xunMarketplace->escrow_fund_out($advertisement["id"], $trading_fee_order["order_id"], $username, $trading_fee_order["quantity"], $trading_fee_order["currency"]);

                            $db->where("id", $trading_fee_order["id"]);
                            $db->update($advertisement_order_table, $update_order_data);
                        }
                    }
                }
            }
        }
    }

// refund advertisement orders

    $db->where("status", "pending_payment");
    $db->where("expires_at < NOW()");
// $db->where("expires_at", date("Y-m-d H:i:s"), "<");

    $expired_ad_order = $db->get("xun_marketplace_advertisement_order_cache");

    $update_order_data = [];
    $update_order_data["updated_at"] = $date;
    $update_order_data["status"] = "expired";

    foreach ($expired_ad_order as $ad_order_cache) {
        $order_id = $ad_order_cache["order_id"];
        $db->where("order_id", $order_id);
        $db->where("disabled", 0);
        $advertisement_order = $db->getOne($ad_order_cache["table_name"]);

        $advertisement_id = $advertisement_order["advertisement_id"];
        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if ($advertisement) {
            $seller_user_id = $advertisement["type"] == "sell" ? $advertisement["user_id"] : $ad_order_cache["user_id"];
            $buyer_user_id = $advertisement["type"] == "buy" ? $advertisement["user_id"] : $ad_order_cache["user_id"];

            $db->where("id", [$seller_user_id, $buyer_user_id], "in");
            $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");
            
            $seller_user = $xun_users[$seller_user_id];
            $seller_username = $seller_user->username;
            $seller_nickname = $seller_user->nickname;
            
            $buyer_user = $xun_users[$buyer_user_id];
            $buyer_username = $buyer_user->username;;
            $buyer_nickname = $buyer_user->nickname;
            
            $owner_username = $advertisement["type"] == "sell" ? $seller_username : $buyer_username;

            $advertisement_order_table = $ad_order_cache["table_name"];

            $xunMarketplace->update_expired_advertisement_order($advertisement, $advertisement_order, $advertisement_order_table, $buyer_username, $buyer_nickname, $seller_username, $seller_nickname);

        }
    }
    if ($refund_total > 0) {
        $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Total refunded for expired advertisement orders: " . $refund_total . "\n");
    }
    update_monitoring();
}

function update_monitoring(){
    $targetUrl = "http://xunmonitoring.backend/server_process_record.php";
    $fields = array("SERVERNAME" => "SGPRODAPI_PHP_001",
                    "SERVERID" => "i-0f35b94beb3ca6d16",
                    "PUBLICIP" => "",
                    "PRIVATEIP" => "10.2.0.193",
                    "SERVERTYPE" => "t3.large",
                    "PROCESS_NAME" => basename(__FILE__, '.php'),
                    "STATUS" => "active",
                    "URGENCY_LEVEL" => "Critical"
                    );
    $dataString = json_encode($fields);

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                               'Content-Type: application/json',
                                               'Content-Length: ' . strlen($dataString))
                );
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}