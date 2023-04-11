<?php

    include_once('include/config.php');
    include_once('include/class.database.php');
    include_once('include/class.setting.php');
    include_once('include/class.language.php');
    include_once('include/class.general.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general  = new General($db, $setting);
    $language = new Language($db, $general, $setting);
    
    $language->generateLanguageFile();

    echo date("Y-m-d H:i:s")." Done generating language file.\n";
?>
