<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);


    $tblDate = date("Ymd");

    if(!trim($tblDate)) {
        $tblDate = date("Ymd");
    }

    $table_name = "xun_cryptocurrency_".$db->escape($tblDate);

    print_r($table_name);

    $db->rawQuery("ALTER TABLE $table_name ADD `market_cap` DECIMAL(20, 8) NOT NULL AFTER `price_change_percentage_24h`");