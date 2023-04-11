<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);

$pay_transaction = $db->get("xun_pay_transaction");
$insert_data_arr = [];
for($i = 0; $i < count($pay_transaction); $i++){
    $data = $pay_transaction[$i];

    $id = $data["id"];
    
    $db->where("pay_transaction_id", $id);
    $item_data = $db->getOne("xun_pay_transaction_item");

    if($item_data){
        continue;
    }
    $quantity = $data["quantity"];
    $quantity = $quantity ? $quantity : 1;

    for($j = 0; $j < $quantity; $j++){
        $insert_data = array(
            "pay_transaction_id" => $id,
            "payment_id" => $data["provider_transaction_id"],
            "order_id" => "",
            "status" => $data["status"],
            "created_at" => $data["created_at"],
            "updated_at" => $data["updated_at"]
        );
    
        $insert_data_arr[] = $insert_data;
    }
}
if(!empty($insert_data_arr)){
    $db->insertMulti("xun_pay_transaction_item", $insert_data_arr);
}
?>