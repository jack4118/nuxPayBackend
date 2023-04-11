<?php
class pushNotification
{
    public $platform;
    private $apnsHostProd = "https://api.push.apple.com";
    private $apnsHostDev = "https://api.development.push.apple.com";
    public $apnsHost;
    public $apnsPort = 2195;
    public $apnsKeyFile;
    public $apnsKeyID;
    public $apnsTeamID;
    public $apnsBundleID;
    public $fcmUrl = "https://fcm.googleapis.com/fcm/send";
    public $fcmKey = "";

    public function __construct($setting, $post, $platform, $voip = false)
    {
        $this->post = $post;
        $this->setting = $setting;
        $this->isVoip = $voip;
        $this->setPlatform($platform);
    }

    public function setFcmKey($key)
    {
        $this->fcmKey = $key;
    }

    public function setPlatform($platform)
    {
        $setting = $this->setting;
        $this->platform = $platform;
        if ($platform == "android") {
            $this->initFcm();
        } else {
            $this->initApns();
        }
    }

    private function initApns()
    {
        global $config;
        $setting = $this->setting;

        $env = $config["environment"];
        $this->apnsKeyID = $setting->systemSetting["apnsKeyID"];
        $this->apnsKeyFile = $setting->systemSetting["apnsKeyFile"];
        $this->apnsTeamID = $setting->systemSetting["apnsTeamID"];
        $this->apnsHost = $env == "prod" ? $this->apnsHostProd : $this->apnsHostDev;
        $apnsBundleID = $setting->systemSetting["apnsBundleID"];
        if($this->isVoip === true){
            $apnsBundleID .= '.voip';
        }

        $this->apnsBundleID = $apnsBundleID;
    }

    private function initFcm()
    {
        $setting = $this->setting;

        $this->fcmKey = $setting->systemSetting["fcmAuthorizationKey"];
    }

    public function sendMessage($regID, $payload)
    {
        if ($this->platform == "android") {
            $this->fcmSendPush($regID, $payload);
        } else {
            $this->apnsSendPush($regID, $payload);
        }
    }

    public function simpleSend($regid, $message, $title = "")
    {
        $payload = array(
            "aps" => array("badge" => "auto", "alert" => $message, "sound" => "beep.caf"),
            "title" => $title,
            "message" => $message,
            "id" => time(),
        );
        if ($this->platform == "android") {
            $this->fcmSendPush($regid, $payload);
        } else {
            $this->apnsSendPush($regid, $payload);
        }
    }

    public function fcmSendPush($regIDs, $payload)
    {
        $post = $this->post;
        if (!is_array($regIDs)) {
            $regIDs = [$regIDs];
        }
        if ($this->fcmKey == "") {
            return;
            // throw new Exception("FCM API Key not set. Use setFcmKey(key) first.");
        } else {
            $postData = array(
                "registration_ids" => $regIDs,
                "data" => $payload,
            );

            // silent push notification
            // if (!isset($postData["data"]["title"])) {$postData["data"]["title"] = "Push Notification";}
            // if (!isset($postData["data"]["message"])) {$postData["data"]["message"] = $payload["aps"]["alert"];}

            $headers = array(
                "Authorization: key=" . $this->fcmKey,
                "Content-Type: application/json",
            );

            $result = $post->curl_post($this->fcmUrl, $postData, 0, 1, $headers);
            return $result;
        }
    }

    public function apnsSendPush($regID, $payload)
    {
        $post = $this->post;

        $keyFile = $this->apnsKeyFile;
        if (!file_exists($keyFile)) {
            return;
            // throw new Exception("Certificate not found: \"{$keyFile}\"");
        } else {
            $keyID = $this->apnsKeyID;
            $teamID = $this->apnsTeamID;
            $bundleID = $this->apnsBundleID;
            $key = openssl_pkey_get_private('file://' . $keyFile);
            $header = ['alg' => 'ES256', 'kid' => $keyID];
            $claims = ['iss' => $teamID, 'iat' => time()];

            $header_encoded = $this->base64($header);
            $claims_encoded = $this->base64($claims);

            $signature = '';
            openssl_sign($header_encoded . '.' . $claims_encoded, $signature, $key, 'sha256');
            $jwt = $header_encoded . '.' . $claims_encoded . '.' . base64_encode($signature);

            $headers = array(
                "apns-topic: {$bundleID}",
                "authorization: bearer $jwt"
            );

            $apnsHost = $this->apnsHost;
            $url_string = "$apnsHost/3/device/$regID";

            $result = $post->curl_post_http2($url_string, $payload, 0, 0, $headers);

            return $result;
        }
    }

    function base64($data) {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
      }
    
}
