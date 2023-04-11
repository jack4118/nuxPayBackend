<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.country.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);

$countries = $db->get("country");
$url_string = "https://s3-ap-southeast-1.amazonaws.com/com.thenux.image/assets/country/flags/";
$ext = ".png";

foreach ($countries as $country) {
    $iso_code2 = $country["iso_code2"];
    $id = $country["id"];
    if ($iso_code2 != '') {
        $iso_code2 = strtolower($iso_code2);
        $url = $url_string . $iso_code2 . $ext;
        echo "\n id: $id, $url";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);

        if (curl_error($ch)) {
            return array("curl_error" => curl_error($ch));
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($httpcode == 200){
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            curl_close($ch);
            $headers_arr = explode("\r\n", $header); // The seperator used in the Response Header is CRLF (Aka. \r\n)
            $headers_arr = array_filter($headers_arr);
            // print_r($headers_arr); // Shows the content of the $headers_arr array
            // print_r($result);
            foreach ($headers_arr as $header_string) {
                if (strpos($header_string, 'ETag: "') === 0) {
                    // It starts with 'http'
                    $header_string = trim($header_string);
                    echo "\n $header_string";
                    $len = strlen($header_string) - 8;
                    $etag = substr($header_string, 7, $len);
                    echo "\n etag = $etag \n";
                    $update_data = [];
                    $update_data["image_url"] = $url;
                    $update_data["image_md5"] = $etag;
                    $update_data["updated_at"] = date("Y-m-d H:i:s");

                    $db->where("id", $id);
                    $db->update("country", $update_data);
                }
            }
        }else{
            echo "\n $iso_code2 : no image\n";
        }
    }
}
