<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$advertisement_order_tables = $db->rawQuery("select table_schema as database_name, table_name from information_schema.tables where table_type = 'BASE TABLE' and table_name like 'xun_marketplace_advertisement_order_transaction_%' order by table_schema, table_name");

foreach($advertisement_order_tables as $table){
    $table_name = $table['table_name'];
    $db->rawQuery("ALTER TABLE ${table_name} ADD `currency` VARCHAR(255) NOT NULL AFTER `quantity`");

    $db->where("currency" ,"");
    $rows = $db->getValue($table_name, 'distinct(advertisement_id)', null);

    foreach($rows as $advertisement_id){
        $db->where("id", $advertisement_id);
        $advertisement_currency = $db->getValue("xun_marketplace_advertisement", "currency");
        
        $update_data = [];
        $update_data["currency"] = $advertisement_currency;
        $db->where("advertisement_id", $advertisement_id);
        $db->update($table_name, $update_data);
    }
}



