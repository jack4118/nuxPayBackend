<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunReward
{

    public function __construct($db, $partnerDB, $post, $general, $setting)
    {
        $this->db = $db;
        $this->partnerDB = $partnerDB;
        $this->post = $post;
        $this->general = $general;
        $this->setting = $setting;
    }

    public function send_reward($params)
    {
        //global $xunPhoneApprove;
        global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $partnerDB = $this->partnerDB;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $mobile_arr = $params['mobile'];
        $reward_amount = $params['reward_amount'];
        $description = $params['description'];

        $reward_point_info = $params['reward_point_info'];
        $transaction_method = $params['transaction_method'];
        $send_all_followers = $params['send_all_followers'];
        $business_id = $params['business_id'];

        $date = date("Y-m-d H:i:s");

        if ($send_all_followers == 0 && !$mobile_arr) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if($api_key == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Api Key cannot be empty");
        // }

        if ($reward_amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00317') /*Reward amount cannot be empty*/);
        }

        if ($description == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00318') /*Description cannot be empty*/);
        }

        if ($transaction_method == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00319') /*Transaction Method cannot be empty*/);
        }

        if (bccomp((string) $reward_amount, "0", 18) <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00320') /*Invalid amount*/);
        }

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business", "id, user_id");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00028'][$language]/*Business does not exist.*/);
        }

        $db->where('a.user_id', $business_id);
        $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
        $xun_business_account = $db->getOne('xun_business_account a', 'b.id');

        $owner_user_id = $xun_business_account['id'];

        $db->where("user_id", $business_id);
        $db->where("type", "reward");
        $reward_setting = $db->getOne("xun_business_reward_setting");
        $business_sending_limit = $reward_setting["reward_sending_limit"];

        $business_coin = $this->getBusinessCoinDetails($business_id, 'reward');

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        $business_coin_id = $business_coin['id'];
        $business_wallet_type = $business_coin['wallet_type'];

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
        }

        $currency_info = $xunCurrency->get_currency_info($business_wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];
        $coin_symbol = strtoupper($currency_info["symbol"]);
        if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $reward_amount, (string) $business_sending_limit, 18) > 0) {
            $translation_message = $this->get_translation_message('E00329'); /*You're only allowed to send a maximum of %%business_sending_limit%% %%coin_symbol%%*/
            $error_message = str_replace("%%business_sending_limit%%", $business_sending_limit, $translation_message);
            $error_message = str_replace("%%coin_symbol%%", $coin_symbol, $error_message);

            return array("code" => 0,
                "message" => "FAILED",
                "message_d" => $error_message);
        }
        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($reward_amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);
            $error_message = $this->get_translation_message('E00325') /*A maximum of %%no_of_decimals decimals%% is allowed for reward amount.*/;
            $error_message = str_replace("%%no_of_decimals%%", $no_of_decimals, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_cp_address_id = $crypto_user_address["id"];

        $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);

        $user_id_arr = [];
        $failed_request_list = [];
        $successful_request_list = [];
        if ($send_all_followers == 1) {
            $db->where('user_id', array($business_id, $owner_user_id), 'NOT IN');
            $db->where('business_coin_id', $business_coin_id);
            $user_coin_list = $db->get('xun_user_coin');

            foreach ($user_coin_list as $key => $value) {
                $follower_user_id = $value['user_id'];

                $user_id_arr[] = $follower_user_id;
            }

        }

        if ($mobile_arr) {

            foreach ($mobile_arr as $phone_number) {

                $mobileFirstChar = $phone_number[0];
                if ($mobileFirstChar != '+') {
                    $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
                    $phone_number = str_replace("-", "", $mobileNumberInfo["phone"]);

                }
                $mobile_list[] = $phone_number;

            }
            $db->where('username', $mobile_list, 'IN');
            // $db->where('email', '');
            $db->where('register_site', '');
            $db->where('type', 'user');
            $mobile_arr_data = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
            if ($mobile_arr_data) {
                foreach ($mobile_list as $mobile_value) {

                    if ($mobile_arr_data[$mobile_value]) {
                        $user_id_arr[] = $mobile_arr_data[$mobile_value]['id'];
                    } else {
                        $unregistered_user[] = $mobile_value;
                    }

                }
            } else {
                $unregistered_user = $mobile_list;
            }

        }

        foreach ($unregistered_user as $value) {
            $failed_request_arr = array(
                "mobile" => $value,
                "error_message" => "Phone Number not registered in thenux",
            );
            $error_message = "%$value% - Phone Number not registered in thenux";
            $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message);

            $failed_request_list[] = $failed_request_arr;

            //  add unregistered users to business partner table

            $insert_business_user_data = array(
                "business_id" => $business_id,
                "mobile" => $value,
                "is_registered" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $update_columns = array(
                "is_registered",
                "updated_at",
            );

            $partnerDB->onDuplicate($update_columns);

            $ids = $partnerDB->insert("business_user", $insert_business_user_data);
        }

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
        $decimal_place = $decimal_place_setting['decimal_places'];

        $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);

        $total_user = count($user_id_arr);
        $total_amount = bcmul($total_user, $reward_amount, $decimal_place);

        if ($total_amount > $wallet_balance) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00338') /*Insufficient Balance"*/);
        }

        if ($user_id_arr) {

            $db->where('id', $user_id_arr, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            //  $db->where('email', '');
            $copyDb = $db->copy();
            $xun_user_list = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

            $mapped_user_id_list = $copyDb->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
            $db->where('user_id', $user_id_arr, 'IN');
            $db->where('active', 1);
            $db->where('address_type', 'personal');
            $recipient_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

            $user_id_with_wallet_arr = [];
            foreach ($recipient_user_address as $address_key => $address_value) {
                $user_id_with_wallet_arr[] = $address_value['user_id'];
            }

            $user_without_wallet = array_diff($user_id_arr, $user_id_with_wallet_arr);

            foreach ($user_without_wallet as $value) {
                $failed_mobile = $mapped_user_id_list[$value]['username'];
                $error_message = 'User did not have a wallet';
                $failed_request_list[] = array(
                    "mobile" => $failed_mobile,
                    "error" => $error_message,
                );
                $wallet_tx_error_message = "%$failed_mobile% - $error_message";

                $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $wallet_tx_error_message);
            }

            $insert_reward = array(
                "business_id" => $business_id,
                "transaction_method" => $transaction_method,
                "reference_id" => '',
                "created_at" => $date,
            );
            $reward_tx_id = $db->insert('xun_business_reward_transaction', $insert_reward);

            if (!$reward_tx_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00339') /*Insert Reward Transaction Failed.*/, 'developer_message' => $db->getLastError());
            }

            $insert_wallet_tx_list = [];
            $reward_details_list = [];
            foreach ($user_id_with_wallet_arr as $value) {
                $receiver_user_id = $value;
                $recipient_address = $recipient_user_address[$receiver_user_id]['address'];

                $insert_wallet_tx = array(
                    "user_id" => $business_id,
                    "sender_address" => $business_cp_address,
                    "recipient_address" => $recipient_address,
                    "sender_user_id" => $business_id,
                    "recipient_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "wallet_type" => $business_wallet_type,
                    "fee" => '',
                    "fee_unit" => '',
                    "transaction_hash" => '',
                    "transaction_token" => '',
                    "status" => "pending",
                    "transaction_type" => 'send',
                    "escrow" => '0',
                    "escrow" => '',
                    "reference_id" => '',
                    "batch_id" => '',
                    "message" => '',
                    "expires_at" => '',
                    "address_type" => 'reward',
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);

                if (!$wallet_tx_id) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                }

                unset($insert_reward_details);
                $insert_reward_details = array(
                    "reward_transaction_id" => $reward_tx_id,
                    "receiver_user_id" => $receiver_user_id,
                    "business_id" => $business_id,
                    "wallet_type" => $business_wallet_type,
                    "amount" => $reward_amount,
                    "transaction_type" => 'reward',
                    "status" => "pending",
                    "wallet_transaction_id" => $wallet_tx_id,
                    "business_reference" => '',
                    "created_at" => $date,
                    "updated_at" => $date,

                );

                $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);

                if (!$reward_details_id) {
                    // $failed_mobile = $mapped_user_id_list[$receiver_user_id];
                    // $error_message = "Something went wrong. : ".$db->getLastError();

                    // $failed_request_list[] = array(
                    //     "mobile" => $failed_mobile,
                    //     "error" => $error_message,
                    // );

                    $update_wallet_tx = array(
                        "status" => 'failed',
                        "updated_at" => $date,
                    );

                    $db->where('id', $wallet_tx_id);
                    $db->update('xun_wallet_transaction', $update_wallet_tx);

                }

                $success_mobile = $mapped_user_id_list[$receiver_user_id]['username'];
                $successful_request_list[] = $success_mobile;

                $crypto_user_address_id = $recipient_user_address[$receiver_user_id]['id'];
                $insert_wallet_sending_queue = array(
                    "sender_crypto_user_address_id" => $business_cp_address_id,
                    "receiver_crypto_user_address_id" => $crypto_user_address_id,
                    "receiver_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "amount_satoshi" => $amount_satoshi,
                    "wallet_type" => $business_wallet_type,
                    "status" => 'pending',
                    "wallet_transaction_id" => $wallet_tx_id,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

            }
        }

        if ($successful_request_list) {
            $db->where('username', $successful_request_list, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            $success_send_user = $db->get('xun_user', null, 'id');

            $db->where('user_id', $success_send_user, 'IN');
            $db->where('business_coin_id', $business_coin_id);
            $existing_follower = $db->map('user_id')->ArrayBuilder()->get('xun_user_coin');

            foreach ($success_send_user as $success_user_key => $success_user_value) {
                $follower_user_id = $success_user_value['id'];
                if (!$existing_follower[$follower_user_id]) {
                    $insert_user_coin = array(
                        "user_id" => $follower_user_id,
                        "business_coin_id" => $business_coin_id,
                        "created_at" => $date,
                    );
                    $inserted = $db->insert('xun_user_coin', $insert_user_coin);
                }
            }
        }

        $returnData['success_request_list'] = $successful_request_list;
        $returnData['failed_request_list'] = $failed_request_list;

        if (!$successful_request_list && $failed_request_list) {
            return array('code' => -101, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00343') /*Send Reward Failed.*/, 'data' => $returnData);
        } else {
            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00200') /*Send Reward Successful.*/, 'data' => $returnData);
        }

    }

    public function send_reward_v1($params)
    {
        //global $xunPhoneApprove;
        global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency ,$country, $config;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $partnerDB = $this->partnerDB;
        $post = $this->post;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $reward_point_info = $params['reward_point_info'];
        $transaction_method = $params['transaction_method'];
        $send_all_followers = $params['send_all_followers'];
        $business_id = $params['business_id'];

        if($send_all_followers){
            $reward_amount = $reward_point_info[0]['reward_amount'];
            $description = $reward_point_info[0]['description'];
        }

        $date = date("Y-m-d H:i:s");

        // if ($send_all_followers == 0 && !$mobile_arr) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty*/);
        // }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if($api_key == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Api Key cannot be empty");
        // }

        // if ($reward_amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00317') /*Reward amount cannot be empty*/);
        // }

        // if ($description == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00318') /*Description cannot be empty*/);
        // }

        // if ($transaction_method == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00319') /*Transaction Method cannot be empty*/);
        // }

        // if (bccomp((string) $reward_amount, "0", 18) <= 0) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00320') /*Invalid amount*/);
        // }

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business", "id, user_id");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00028'][$language]/*Business does not exist.*/);
        }

        $db->where('a.user_id', $business_id);
        $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
        $xun_business_account = $db->getOne('xun_business_account a', 'b.id');

        $owner_user_id = $xun_business_account['id'];

        $db->where("user_id", $business_id);
        $db->where("type", "reward");
        $reward_setting = $db->getOne("xun_business_reward_setting");
        $business_sending_limit = $reward_setting["reward_sending_limit"];

        $business_coin = $this->getBusinessCoinDetails($business_id, 'reward');

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        $business_coin_id = $business_coin['id'];
        $business_wallet_type = $business_coin['wallet_type'];

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
        }

        $currency_info = $xunCurrency->get_currency_info($business_wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];
        $coin_symbol = strtoupper($currency_info["symbol"]);
        if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $reward_amount, (string) $business_sending_limit, 18) > 0) {
            $translation_message = $this->get_translation_message('E00329'); /*You're only allowed to send a maximum of %%business_sending_limit%% %%coin_symbol%%*/
            $error_message = str_replace("%%business_sending_limit%%", $business_sending_limit, $translation_message);
            $error_message = str_replace("%%coin_symbol%%", $coin_symbol, $error_message);

            return array("code" => 0,
                "message" => "FAILED",
                "message_d" => $error_message);
        }
        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($reward_amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);
            $error_message = $this->get_translation_message('E00325') /*A maximum of %%no_of_decimals decimals%% is allowed for reward amount.*/;
            $error_message = str_replace("%%no_of_decimals%%", $no_of_decimals, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_cp_address_id = $crypto_user_address["id"];

        $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);
       $db->where('currency_id', $business_wallet_type);
       $marketplaceCurrencies = $db->getOne('xun_marketplace_currencies');

       $total_supply = $marketplaceCurrencies['total_supply'];

        $user_id_arr = [];
        $failed_request_list = [];
        $successful_request_list = [];
        if ($send_all_followers == 1) {
            $db->where('user_id', array($business_id, $owner_user_id), 'NOT IN');
            $db->where('business_coin_id', $business_coin_id);
            $user_coin_list = $db->get('xun_user_coin');

            foreach ($user_coin_list as $key => $value) {
                $follower_user_id = $value['user_id'];

                $user_id_arr[] = $follower_user_id;
            }

        }

        if ($send_all_followers != 1 && $reward_point_info) {

            unset($unregistered_user);
            foreach ($reward_point_info as $key => $value) {
                unset($mobile_arr);
                unset($mobile_list);
                $reward_amount = $value['reward_amount'];
                $country_code = $value['country_code'];
                $description = $value['description'];
                $mobile_arr = $value['mobile'];
                $countryParams = array(
                    "country_code_arr" => array($country_code),
                );
                $countryData = $country->getCountryByCountryCode($countryParams);

                $selectedCountryName = $countryData[$country_code]['name'];
                $selectedCountryCode = $country_code;

                foreach ($mobile_arr as $mobile) {

                    $mobileFirstChar = $mobile[0];
                    $phone_number = $country_code ."".$mobile;
                    $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
                    $mobileCountryCode = $mobileNumberInfo['countryCode'];
                    $phone_number = str_replace("-", "", $mobileNumberInfo["phone"]);
                    $isValid = $mobileNumberInfo['isValid'];

                    if($isValid != 1){
                        $failed_request_arr = array(
                            "mobile" => $phone_number,
                            "error_message" => "Phone Number is not valid",
                        );
                        $error_message = "%$phone_number% - Phone Number is not valid";
                        $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'reward');

                        $failed_request_list[] = $failed_request_arr;
                        continue;
                    }
                    
                    if ($selectedCountryCode == $mobileCountryCode) {
                        $mobile_list[] = $phone_number;
                    } else {
                        $failed_request_arr = array(
                            "mobile" => $phone_number,
                            "error_message" => "Phone Number is not from $selectedCountryName",
                        );
                        $error_message = "%$phone_number% - Phone Number is not from %$selectedCountryName%";
                        $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'reward');

                        $failed_request_list[] = $failed_request_arr;

                    }
                }

                if($mobile_list){
                    $db->where('username', $mobile_list, 'IN');
                    // $db->where('email', '');
                    $db->where('register_site', '');
                    $db->where('type', 'user');
                    $mobile_arr_data = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

                    if ($mobile_arr_data) {
                        foreach ($mobile_list as $mobile_value) {

                            if ($mobile_arr_data[$mobile_value]) {
                                $user_id_arr[] = $mobile_arr_data[$mobile_value]['id'];

                                $reward_point_array = array(
                                    "user_id" => $mobile_arr_data[$mobile_value]['id'],
                                    "description" => $description,
                                    "reward_amount" => $reward_amount,
                                    "country_code" => $country_code,
                                );

                                $mapped_reward_point_info[$mobile_arr_data[$mobile_value]['id']] = $reward_point_array;
                            } else {
                                $unregistered_user[] = $mobile_value;
                            }

                        }
                    } else {
                        $unregistered_user = $mobile_list;
                    }
                }
            }
        }

        foreach ($unregistered_user as $value) {
            $failed_request_arr = array(
                "mobile" => $value,
                "error_message" => "Phone Number not registered in thenux",
            );
            $error_message = "%$value% - Phone Number not registered in thenux";
            $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message);

            $failed_request_list[] = $failed_request_arr;

            //  add unregistered users to business partner table

            $insert_business_user_data = array(
                "business_id" => $business_id,
                "mobile" => $value,
                "is_registered" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $update_columns = array(
                "is_registered",
                "updated_at",
            );

            $partnerDB->onDuplicate($update_columns);

            $ids = $partnerDB->insert("business_user", $insert_business_user_data);
        }

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
        $decimal_place = $decimal_place_setting['decimal_places'];

        $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);

        // $total_user = count($user_id_arr);
        // $total_amount = bcmul($total_user, $reward_amount, $decimal_place);

        // if ($total_amount > $wallet_balance) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00338') /*Insufficient Balance"*/);
        // }

        if ($user_id_arr) {

            $db->where('id', $user_id_arr, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            //  $db->where('email', '');
            $copyDb = $db->copy();
            $xun_user_list = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

            $registered_user_list = array_keys($xun_user_list);

            $server = $config['server'];
            $erlang_server = $config['erlang_server'];
            $db->where('username', $registered_user_list, 'IN');
            $db->where('business_id', $business_id);
            $db->where('server_host', $server);
            $business_follow_list = $db->map('username')->ArrayBuilder()->get('xun_business_follow');
            $business_followed_mobile = array_keys($business_follow_list);

            //Phone number that is not yet in xun_business_follow
            $not_business_follow_mobile = array_diff($registered_user_list, $business_followed_mobile);
            foreach($not_business_follow_mobile as $no_follow_mobile){
                $insert_business_follow = array(
                    "username" => $no_follow_mobile,
                    "business_id" => $business_id,
                    "server_host" => $server,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $business_follow_id = $db->insert('xun_business_follow', $insert_business_follow);
                
                if($business_follow_id){
                    $user_follow_array = array(
                        "mobile" => (string) $no_follow_mobile,
                        "business_follow_id" => (string) $business_follow_id
                    );

                    $user_follow_list[] = $user_follow_array;
 
                }
            }
            $failed_auto_message_list = [];
            if($user_follow_list){
                $erlang_params = array(
                    "business_id" => (string) $business_id,
                    "user_follow_list" => $user_follow_list
                );

                $erlangReturn = $post->curl_post("user/business/follow", $erlang_params);                
                $error_follow_list = $erlangReturn['data'];

                foreach($error_follow_list as $error_key => $error_value){

                    if($error_value['error']){

                        $failed_auto_message_list[] = $error_value['error'];

                    }   
                }
        
            }

            $mapped_user_id_list = $copyDb->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
            $db->where('user_id', $user_id_arr, 'IN');
            $db->where('active', 1);
            $db->where('address_type', 'personal');
            $recipient_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

            $user_id_with_wallet_arr = [];
            foreach ($recipient_user_address as $address_key => $address_value) {
                $user_id_with_wallet_arr[] = $address_value['user_id'];
            }

            $user_without_wallet = array_diff($user_id_arr, $user_id_with_wallet_arr);

            foreach ($user_without_wallet as $value) {
                $failed_mobile = $mapped_user_id_list[$value]['username'];
                $error_message = 'User did not have a wallet';
                $failed_request_list[] = array(
                    "mobile" => $failed_mobile,
                    "error" => $error_message,
                );
                $wallet_tx_error_message = "%$failed_mobile% - $error_message";

                $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $wallet_tx_error_message);
            }

            $insert_reward = array(
                "business_id" => $business_id,
                "transaction_method" => $transaction_method,
                "reference_id" => '',
                "created_at" => $date,
            );
            $reward_tx_id = $db->insert('xun_business_reward_transaction', $insert_reward);

            if (!$reward_tx_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00339') /*Insert Reward Transaction Failed.*/, 'developer_message' => $db->getLastError());
            }

            $insert_wallet_tx_list = [];
            $reward_details_list = [];
            foreach ($user_id_with_wallet_arr as $value) {
                $receiver_user_id = $value;
                if($reward_point_info && $send_all_followers != 1){
                    $reward_amount = $mapped_reward_point_info[$receiver_user_id]['reward_amount'];
                    $total_amount = $total_amount + $reward_amount;

                }
                else{
                    $total_amount = $total_amount + $reward_amount;
                }
            }

            if($total_amount > $wallet_balance){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
                // $request_amount = bcsub($total_amount, $wallet_balance, 8);
                
                // $thenuxRewardTotalSupply = $setting->systemSetting['theNuxRewardTotalSupply'];
                // $updated_total_supply = bcadd($total_supply, $request_amount, 8);

                // if(bcadd($total_supply, $request_method, 8) >= $thenuxRewardTotalSupply ){
                //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00492') /*Exceeded Total Supply Amount.*/, 'developer_message' => "Exceeded Total Supply Amount.");
                // }

                // $transaction_token = $this->generate_transaction_token($business_id);
                
                // $request_credit_params = array(
                //     "walletType" => $business_wallet_type,
                //     "receiverAddress" => $business_cp_address,
                //     "amount" => $request_amount,
                //     "transactionToken" => $transaction_token,
                // );

                // $crypto_result = $xunCrypto->request_credit_transfer_pool($request_credit_params);
                // if($crypto_result['status'] == 'error'){
                //     return $crypto_result;
                // }
                // $transaction_hash = $crypto_result['data']['transactionHash'];
                // $custom_coin_tx_arr  = $this->insert_custom_coin_transaction_token($business_id, $request_amount,  $business_wallet_type,$transaction_token, $transaction_hash);

                // $custom_coin_supply_tx_id = $custom_coin_tx_arr['custom_coin_supply_transaction_id'];


 
            }

            foreach ($user_id_with_wallet_arr as $value) {
                $receiver_user_id = $value;
                $recipient_address = $recipient_user_address[$receiver_user_id]['address'];
                if($reward_point_info && $send_all_followers != 1){
                    $description = $mapped_reward_point_info[$receiver_user_id]['description'];
                    $reward_amount = $mapped_reward_point_info[$receiver_user_id]['reward_amount'];
                    $country_id = $mapped_reward_point_info[$receiver_user_id]['country_id'];

                }

                $insert_wallet_tx = array(
                    "user_id" => $business_id,
                    "sender_address" => $business_cp_address,
                    "recipient_address" => $recipient_address,
                    "sender_user_id" => $business_id,
                    "recipient_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "wallet_type" => $business_wallet_type,
                    "fee" => '',
                    "fee_unit" => '',
                    "transaction_hash" => '',
                    "transaction_token" => '',
                    "status" => "pending",
                    "transaction_type" => 'send',
                    "escrow" => '0',
                    "escrow" => '',
                    "reference_id" => '',
                    "batch_id" => '',
                    "message" => '',
                    "expires_at" => '',
                    "address_type" => 'reward',
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);

                if (!$wallet_tx_id) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                }

                unset($insert_reward_details);
                $insert_reward_details = array(
                    "reward_transaction_id" => $reward_tx_id,
                    "receiver_user_id" => $receiver_user_id,
                    "business_id" => $business_id,
                    "wallet_type" => $business_wallet_type,
                    "amount" => $reward_amount,
                    "transaction_type" => 'reward',
                    "status" => "pending",
                    "wallet_transaction_id" => $wallet_tx_id,
                    "business_reference" => '',
                    "created_at" => $date,
                    "updated_at" => $date,

                );

                $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);

                if (!$reward_details_id) {
                    // $failed_mobile = $mapped_user_id_list[$receiver_user_id];
                    // $error_message = "Something went wrong. : ".$db->getLastError();

                    // $failed_request_list[] = array(
                    //     "mobile" => $failed_mobile,
                    //     "error" => $error_message,
                    // );

                    $update_wallet_tx = array(
                        "status" => 'failed',
                        "updated_at" => $date,
                    );

                    $db->where('id', $wallet_tx_id);
                    $db->update('xun_wallet_transaction', $update_wallet_tx);

                }

                $success_mobile = $mapped_user_id_list[$receiver_user_id]['username'];
                $successful_request_list[] = $success_mobile;

                $crypto_user_address_id = $recipient_user_address[$receiver_user_id]['id'];

                $insert_wallet_sending_queue = array(
                    "sender_crypto_user_address_id" => $business_cp_address_id,
                    "receiver_crypto_user_address_id" => $crypto_user_address_id,
                    "receiver_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "amount_satoshi" => $amount_satoshi,
                    "wallet_type" => $business_wallet_type,
                    "status" => 'pending',
                    "wallet_transaction_id" => $wallet_tx_id,
                    "custom_coin_supply_transaction_id" => $custom_coin_supply_tx_id ? $custom_coin_supply_tx_id : 0,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

            }
        }

        if ($successful_request_list) {
            $db->where('username', $successful_request_list, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            $success_send_user = $db->get('xun_user', null, 'id');

            $db->where('user_id', $success_send_user, 'IN');
            $db->where('business_coin_id', $business_coin_id);
            $existing_follower = $db->map('user_id')->ArrayBuilder()->get('xun_user_coin');

            foreach ($success_send_user as $success_user_key => $success_user_value) {
                $follower_user_id = $success_user_value['id'];
                if (!$existing_follower[$follower_user_id]) {
                    $insert_user_coin = array(
                        "user_id" => $follower_user_id,
                        "business_coin_id" => $business_coin_id,
                        "created_at" => $date,
                    );
                    $inserted = $db->insert('xun_user_coin', $insert_user_coin);
                }
            }
        }

        $returnData['success_request_list'] = $successful_request_list;
        $returnData['failed_request_list'] = $failed_request_list;
        $returnData['failed_auto_message_list'] = $failed_auto_message_list;

        if (!$successful_request_list && $failed_request_list) {
            return array('code' => -101, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00343') /*Send Reward Failed.*/, 'data' => $returnData);
        } else {
            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00200') /*Send Reward Successful.*/, 'data' => $returnData);
        }

    }

    public function send_reward_external_api($params)
    {
        //global $xunPhoneApprove;
        global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency;
        $db = $this->db;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id = $params["business_id"];
        $request_arr = $params["request_arr"];
        $total_amount = $params["total_amount"];
        $batch_id = $params["batch_id"];
        $reference_id = $params["reference_id"];
        $wallet_type = $params["wallet_type"];

        $date = date("Y-m-d H:i:s");

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business", "id, user_id");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00028'][$language]/*Business does not exist.*/);
        }

        $db->where("user_id", $business_id);
        $db->where("type", "reward");
        $reward_setting = $db->getOne("xun_business_reward_setting");
        $business_sending_limit = $reward_setting["reward_sending_limit"];

        // $db->where('business_id',$business_id);
        // $db->where('type', 'reward');
        // $business_coin = $db->getOne('xun_business_coin');
        $business_coin = $this->getBusinessCoinDetails($business_id, 'reward');

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business coin does not exist.");
        }

        $business_coin_id = $business_coin['id'];
        $business_wallet_type = $business_coin['wallet_type'];

        if ($wallet_type != $business_wallet_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid rewards currency.");
        }

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business company pool wallet not created.");
        }

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
        $decimal_place = $decimal_place_setting['decimal_places'];

        $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);

        $failed_request_list = [];
        $successful_request_list = [];

        foreach ($request_arr as $request_data) {
            $request_amount = $request_data["amount"];
            if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $request_amount, (string) $business_sending_limit, 18) > 0) {
                $error_message = "Maximum sending amount is $business_sending_limit " . strtoupper($coin_symbol);

                $failed_request_list[] = array(
                    "mobile" => $request_data["username"],
                    "error" => $error_message,
                );

                continue;
            } else {
                $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $request_amount);
                $request_data["amount_satoshi"] = $amount_satoshi;
                $successful_request_list[] = $request_data;
            }
        }

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_cp_address_id = $crypto_user_address["id"];

        $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);

        if ($total_amount > $wallet_balance) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
        }

        $insert_reward = array(
            "business_id" => $business_id,
            "transaction_method" => "company_pool",
            "business_reference_id" => $reference_id,
            "batch_id" => $batch_id,
            "created_at" => $date,
        );
        $reward_tx_id = $db->insert('xun_business_reward_transaction', $insert_reward);

        if (!$reward_tx_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insert Reward Transaction Failed.", 'developer_message' => $db->getLastError());
        }

        $insert_wallet_tx_list = [];
        $reward_details_list = [];
        $success_mobile_list = [];
        foreach ($successful_request_list as $request_data) {
            $receiver_user_id = $request_data["user_id"];
            $receiver_username = $request_data["username"];
            $recipient_address = $request_data['address'];
            $request_amount = $request_data["amount"];
            $request_amount_satoshi = $request_data["amount_satoshi"];

            $insert_wallet_tx = array(
                "user_id" => $business_id,
                "sender_address" => $business_cp_address,
                "recipient_address" => $recipient_address,
                "sender_user_id" => $business_id,
                "recipient_user_id" => $receiver_user_id,
                "amount" => $request_amount,
                "wallet_type" => $business_wallet_type,
                "fee" => '',
                "fee_unit" => '',
                "transaction_hash" => '',
                "transaction_token" => '',
                "status" => "pending",
                "transaction_type" => 'send',
                "escrow" => '0',
                "escrow" => '',
                "reference_id" => '',
                "batch_id" => '',
                "message" => '',
                "expires_at" => '',
                "address_type" => 'reward',
                "created_at" => $date,
                "updated_at" => $date,
            );

            $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);

            if (!$wallet_tx_id) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $db->getLastError());
            }

            unset($insert_reward_details);
            $insert_reward_details = array(
                "reward_transaction_id" => $reward_tx_id,
                "receiver_user_id" => $receiver_user_id,
                "business_id" => $business_id,
                "wallet_type" => $business_wallet_type,
                "amount" => $request_amount,
                "transaction_type" => 'reward',
                "status" => "pending",
                "wallet_transaction_id" => $wallet_tx_id,
                "business_reference" => '',
                "created_at" => $date,
                "updated_at" => $date,

            );

            $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);

            if (!$reward_details_id) {
                $update_wallet_tx = array(
                    "status" => 'failed',
                    "updated_at" => $date,
                );

                $db->where('id', $wallet_tx_id);
                $db->update('xun_wallet_transaction', $update_wallet_tx);
            }

            $success_mobile_list[] = $receiver_username;

            $crypto_user_address_id = $request_data["crypto_user_address_id"];
            $insert_wallet_sending_queue = array(
                "sender_crypto_user_address_id" => $business_cp_address_id,
                "receiver_crypto_user_address_id" => $crypto_user_address_id,
                "receiver_user_id" => $receiver_user_id,
                "amount" => $request_amount,
                "amount_satoshi" => $request_amount_satoshi,
                "wallet_type" => $business_wallet_type,
                "status" => 'pending',
                "wallet_transaction_id" => $wallet_tx_id,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

        }

        $return_data = [];
        $return_data["successful_request_list"] = $success_mobile_list;
        $return_data['failed_request_list'] = $failed_request_list;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Send Reward Successful.", 'data' => $return_data);
    }

    public function get_redeem_listing($params)
    {
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        // $submerchant_name = $params['submerchant_name'];
        // $name = $params['name'];
        // $amount = $params['amount'];
        $business_id = trim($params['business_id']);
        $follower_mobile = $params['follower_mobile'];
        $reference_no = $params['reference_no'];
        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];
        $filter_employee_external_address = $params["employee_external_address"];
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]/*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        // get business_coin_id from xun_business_coin
        // $db->where("business_id", $business_id);
        // $db->where('type', 'reward');
        // $business_coin_info = $db->getOne("xun_business_coin");
        $business_coin_info = $this->getBusinessCoinDetails($business_id, 'reward');
        if (!$business_coin_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['E00103'][$language]/*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['wallet_type']);

        $date = date("Y-m-d H:i:s");

        $db->where('user_id', $business_id);
        $db->where('address_type', 'reward');
        $db->where('active', 1);
        $xun_crypto_user_address = $db->getOne('xun_crypto_user_address');

        $crypto_user_address_id = $xun_crypto_user_address['id'];
        $business_cp_address = $xun_crypto_user_address['address'];
        $business_deposit_address = $xun_crypto_user_address['external_address'];

        $db->where('crypto_user_address_id', $crypto_user_address_id);
        $db->where('wallet_type', $business_coin_wallet_type);
        $employee_external_address_list = $db->map('user_id')->ArrayBuilder()->get('xun_user_crypto_external_address');

        $all_employee_ids = array_keys($employee_external_address_list);

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where("a.created_at", $from_datetime, ">=");
        }
        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where("a.created_at", $to_datetime, "<=");
        }

        if ($reference_no) {
            $db->where("a.reference_id", $reference_no);
        }

        $db->where("((a.address_type = ? AND a.recipient_user_id = ? AND sender_user_id != ?) or (a.address_type = ? and recipient_user_id = ? and e.status in ('submitted','pending', 'success')))", array("reward", $business_id, $business_id, "pay", "topup"));
        // $db->where('a.recipient_user_id', $business_id);
        $db->where("a.wallet_type", $business_coin_wallet_type);
        $db->where("a.status", "completed");

        $copyDb = $db->copy();
        if ($follower_mobile) {
            $db->where("(c.username like ? or d.main_mobile like ?)", array("%$follower_mobile%", "%$follower_mobile%"));

        }
        if ($filter_employee_external_address) {
            $db->where('b.reference_address', $filter_employee_external_address);
        }

        $db->where('b.reference_address', $business_deposit_address, '!=');
        $db->where("b.type", "send");
        $db->orderBy("a.created_at", $order);
        $db->join('xun_business_account d', 'd.user_id = a.sender_user_id', 'LEFT');
        $db->join('xun_user c', 'c.id = a.sender_user_id', 'LEFT');
        $db->join('xun_pay_transaction e', 'e.wallet_transaction_id=a.id', 'LEFT');
        $db->join('xun_crypto_callback b', 'b.transaction_hash = a.transaction_hash', 'LEFT');
        $redeem_info = $db->get("xun_wallet_transaction a", $limit, "a.id, a.user_id, a.sender_user_id, a.recipient_user_id, a.amount, a.receiver_reference, a.status,  a.created_at, b.reference_address, c.username, c.nickname");
        if (!$redeem_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['E00103'][$language]/*No Results Found.*/, 'data' => []);
        }

        //$totalRecord = $copyDb->getValue("xun_wallet_transaction a", "count(a.id)");
        $totalRecord = count($redeem_info);

        // print_r($redeem_info);
        foreach ($redeem_info as $redeem) {
            $recipient_ids[] = $redeem['recipient_user_id'];
            $sender_ids[] = $redeem['sender_user_id'];
            $reference_address_arr[] = $redeem['reference_address'];
        }

        $db->where('external_address', $reference_address_arr, 'IN');
        $db->where('address_type', 'reward');
        $user_crypto_external_address = $db->map('external_address')->ArrayBuilder()->get('xun_user_crypto_external_address');

        if ($user_crypto_external_address) {
            foreach ($user_crypto_external_address as $key => $value) {
                $employee_user_id = $value['user_id'];
                $employee_ids[] = $employee_user_id;
            }
        }
        //get user info

        if (!empty($recipient_ids) && is_array($recipient_ids)) {
            $db->where("id", $recipient_ids, "IN", "OR");
            $db->where('type', 'user');
        }
        if (!empty($sender_ids) && is_array($sender_ids)) {
            $db->where("id", $sender_ids, "IN", "OR");
            $db->where('type', 'user');
        }
        if ($all_employee_ids) {
            $db->where('id', $all_employee_ids, 'IN', 'OR');
            $db->where('type', 'user');
        }

        $user_info = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username as phone, nickname");
        $user_id_arr = array_keys($user_info);
        $merge_arr = array_merge($recipient_ids, $sender_ids, $all_employee_ids);

        $business_id_arr = array_diff($merge_arr, $user_id_arr);

        if ($business_id_arr) {
            $db->where('a.user_id', $business_id_arr, 'IN');
            $db->join('xun_business b', 'b.user_id = a.user_id', 'LEFT');
            $business_account = $db->map('user_id')->ArrayBuilder()->get('xun_business_account a');
        }

        unset($redeem);

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_employee_arr = $db->map("mobile")->ArrayBuilder()->get("xun_employee");

        foreach ($all_employee_ids as $employee_user_id) {
            $employee_mobile = $user_info[$employee_user_id]['phone'];
            $employee_name = $xun_employee_arr[$employee_mobile]['name'];
            $employee_external_address = $employee_external_address_list[$employee_user_id]['external_address'];

            $employee_arr = array(
                "employee_name" => $employee_name,
                "employee_external_address" => $employee_external_address,

            );
            $employee_list[] = $employee_arr;
        }

        foreach ($redeem_info as $redeem) {
            $sender_user_id = $redeem['sender_user_id'];
            $recipient_user_id = $redeem['recipient_user_id'];
            $reference_address = $redeem['reference_address'];
            $employee_user_id = $user_crypto_external_address[$reference_address]['user_id'] ? $user_crypto_external_address[$reference_address]['user_id'] : $recipient_user_id;
            // $submerchant_name = $user_info[$employee_user_id]['nickname'] ? $user_info[$employee_user_id]['nickname'] : $business_account[$employee_user_id]['name'];
            $submerchant_mobile = $user_info[$employee_user_id]['phone'];
            if($recipient_user_id == 'topup'){
                $submerchant_name = "Shop";
            }else{
                $submerchant_name = $xun_employee_arr[$submerchant_mobile]['name'] ? $xun_employee_arr[$submerchant_mobile]['name'] : $business_account[$employee_user_id]['name'];
            }
            $status = $redeem['status'];

            $employee_external_address = '';
            // if($recipient_user_id == $business_id){
            //     $submerchant_name = $user_info[$recipient_user_id]['nickname'];
            //     $submerchant_mobile = $user_info[$recipient_user_id]['phone'];
            //     $employee_external_address = $xun_crypto_user_address['external_address'];
            // }
            // elseif($employee_external_address_list){
            //     $employee_external_address = $employee_external_address_list[$recipient_user_id]['external_address'];
            // }
            // else{
            //     $employee_external_address = '';
            // }

            $redeem_array['follower_name'] = $user_info[$sender_user_id]['nickname'] ? $user_info[$sender_user_id]['nickname'] : $business_account[$sender_user_id]['name'];
            $redeem_array['follower_mobile'] = $user_info[$sender_user_id]['phone'] ? $user_info[$sender_user_id]['phone'] : $business_account[$sender_user_id]['main_mobile'];
            $redeem_array['amount'] = $redeem['amount'];
            $redeem_array['reference_no'] = $redeem['receiver_reference'];
            $redeem_array['submerchant_name'] = $submerchant_name ? $submerchant_name : "";
            $redeem_array['submerchant_mobile'] = $submerchant_mobile ? $submerchant_mobile : "";
            $redeem_array['submerchant_ex_address'] = $reference_address ? $reference_address : '';
            $redeem_array['status'] = $status;
            $redeem_array['created_at'] = $redeem['created_at'];

            $redeem_list[] = $redeem_array;
            $total_amount = bcadd($total_amount, $redeem['amount'], 8);
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $returnData["employee_list"] = $employee_list;
        $returnData["data"] = $redeem_list;
        $returnData['total_amount'] = $total_amount;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $num_record;
        $returnData["totalPage"] = $total_page;
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00201'), 'data' => $returnData);
    }

    public function dashboard_statistic($params)
    {
        global $xunCrypto, $xunCurrency;
        $db = $this->db;
        $business_id = $params['business_id'];
        $from_datetime = $params['date_from'];
        $to_datetime = $params['date_to'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($from_datetime == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00346') /*Date from cannot be empty.*/);
        }

        if ($to_datetime == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00347') /*Date to cannot be empty.*/);
        }

        $from_date = date("Y-m-d H:i:s", $from_datetime);
        $to_date = date("Y-m-d H:i:s", $to_datetime);

        $db->where('business_id', $business_id);
        $business_coin = $db->map('type')->ArrayBuilder()->get('xun_business_coin');

        $business_wallet_type = $business_coin['reward']['wallet_type'];
        $business_coin_id = $business_coin['reward']['id'];
        $cash_reward_wallet_type = $business_coin['cash_token']['wallet_type'];

        $dateFrom = date("Y-m-d H:00:00", $from_datetime);
        $dateTo = date("Y-m-d H:00:00", $to_datetime);

        $d1 = strtotime($dateFrom);
        $d2 = strtotime($dateTo);

        $diff = $d2 - $d1;
        $hours = $diff / (60 * 60); //get the difference in hours

        $chart_list = [];
        //loop the hours and push each hour into the date arr
        $cash_reward_issued = '0';
        for ($i = 0; $i <= $hours; $i++) {
            if ($i == 0) {
                $date_time = $dateFrom;
            } else {
                $date_time = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($date_time)));
            }
            $chart_arr = array(
                "date" => $date_time,
                "redemption" => '0',
                "follower" => '0',
                "points_issued" => '0',
                "cash_reward_issued" => $cash_reward_issued,
            );

            $chart_list[$date_time] = $chart_arr;

        }

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_deposit_address = $crypto_user_address['external_address'];

        $db->where('user_id', $business_id);
        $business_account = $db->getOne('xun_business_account');

        $previous_last_login = $business_account['previous_last_login'];

        $db->where('user_id', $business_id);
        $db->where('name', 'cashpoolBalance');
        $cashpool_result = $db->getOne('xun_user_setting');

        $cashpool_balance = $cashpool_result['value'] ? $cashpool_result['value'] : '0.00000000';

        $db->where('currency_id', $business_wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');

        $total_coin_supply = $marketplace_currencies['total_supply'];

        $db->where('user_id', $business_id);
        $business_account = $db->getOne('xun_business_account');

        $username = $business_account['main_mobile'];

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');

        $user_ids = array($business_id, $xun_user['id']);

        $db->where('user_id', $user_ids, 'IN');
        $db->where('address_type', 'personal');
        $db->where('active', 1);
        $personal_user_address = $db->get('xun_crypto_user_address');

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
        $decimal_place = $decimal_place_setting['decimal_places'];

        $total_converted_balance = 0;
        foreach ($personal_user_address as $key => $value) {
            $internal_address = $value['address'];

           
            try{
                // temporary comment due to timeout issue
                $wallet_info = $xunCrypto->get_wallet_info($internal_address, $business_wallet_type);
                $balance = $wallet_info[$business_wallet_type]['balance'];
                // $balance = 0;
            
                $unit_conversion = $wallet_info[$business_wallet_type]['unitConversion'];
                $converted_balance = bcdiv($balance, $unit_conversion, $decimal_place);
                $total_converted_balance = bcadd($total_converted_balance, $converted_balance, $decimal_place);
            }
            catch (Exception $e){
                $total_converted_balance = "0";
            }
          
        }

        $market_floating_fund = bcsub($total_coin_supply, $total_converted_balance, $decimal_place);

        if (!$crypto_user_address) {
            $chart_data = array_values($chart_list);
            $returnData = array(
                "chart_data" => $chart_data,
                "pool_balance" => '0.00000000',
                'cash_reward_pool_balance' => '0.00000000',
                "cashpool_balance" => $cashpool_balance,
                "market_floating_fund" => $market_floating_fund,
                "follower" => (string) 0,
                "points_issued" => (string) 0,
                "redemption" => (string) 0,
                "last_login" => $previous_last_login,
            );

            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00205') /*Dashboard Statistics*/, 'data' => $returnData);

        }

        try{
            // temporary comment due to timeout issue
            $reward_wallet_info = $xunCrypto->get_wallet_info($business_cp_address, $business_wallet_type);
             $balance = $reward_wallet_info[$business_wallet_type]["balance"];
             $unit_conversion = $reward_wallet_info[$business_wallet_type]["unitConversion"];
             $wallet_balance = bcdiv($balance, $unit_conversion, 8);
            // $balance = 0;
            // $wallet_balance = 0;
    
            $cash_reward_balance = $reward_wallet_info[$cash_reward_wallet_type]["balance"];
            $cash_reward_unit_conversion = $reward_wallet_info[$cash_reward_wallet_type]["unitConversion"];
    
            $cash_reward_wallet_balance = bcdiv($cash_reward_balance, $cash_reward_unit_conversion, 8);
    
        }
        catch (Exception $e){
            $wallet_balance = "0";
            $cash_reward_wallet_balance = "0";
        }
       
        $market_floating_fund = bcsub((string) $market_floating_fund, (string) $wallet_balance, $decimal_place);

        $db->where('a.created_at', $from_date, '>=');
        $db->where('a.created_at', $to_date, '<');
        $db->where('a.address_type', 'reward');
        $db->where('a.wallet_type', $business_wallet_type);
        $db->where('a.status', 'completed');
        $db->where('b.type', 'send');
        // $db->where('status', 'pending');
        $db->join('xun_crypto_callback b', 'a.transaction_hash = b.transaction_hash', 'LEFT');
        $wallet_transaction = $db->get('xun_wallet_transaction a');

        $total_redemption = 0;
        $total_points_issued = 0;
        $total_follower = 0;

        foreach ($wallet_transaction as $key => $value) {
            $sender_user_id = $value['sender_user_id'];
            $recipient_user_id = $value['recipient_user_id'];
            $amount = $value['amount'];
            $recipient_address = $value['recipient_address'];
            $reference_address = $value['reference_address'];
            $sender_address = $value['sender_address'];

            $created_at = $value['created_at'];
            $dateTime = strtotime($created_at);
            $date = date("Y-m-d H:i:s", $dateTime);
            $dateWithHour = date("Y-m-d H:00:00", strtotime($created_at));
            if ($sender_user_id == $business_id && $sender_address == $business_deposit_address) {

                if ($chart_list[$dateWithHour]) {

                    $total_amount = $chart_list[$dateWithHour]['points_issued'] + $amount;
                    $chart_list[$dateWithHour]["points_issued"] = strval($total_amount);

                }

                $total_points_issued = $total_points_issued + $amount;
            } elseif ($recipient_user_id == $business_id && $reference_address != $business_deposit_address) {

                if ($chart_list[$dateWithHour]) {
                    $total_amount = $chart_list[$dateWithHour]['redemption'] + $amount;
                    $chart_list[$dateWithHour]["redemption"] = strval($total_amount);
                }

                $total_redemption = $total_redemption + $amount;
            }

        }

        $db->where('b.wallet_type', $business_wallet_type);
        $db->where("a.status", "success");
        $db->where('a.created_at', $from_date, '>=');
        $db->where('a.created_at', $to_date, '<');
        $db->join("xun_pay_transaction b", "a.pay_transaction_id = b.id", "LEFT");
        $pay_transaction_item_arr = $db->getOne("xun_pay_transaction_item a", "sum(b.amount/b.quantity) as total_purchase");
        // echo $db->getLastQuery();
        // print_r($pay_transaction_item_arr);
        // SELECT * FROM `xun_pay_transaction_item` a JOIN xun_pay_transaction b on a.pay_transaction_id = b.id where b.wallet_type = 'sms123rewards' and a.status = 'success' 
        // foreach($pay_transaction_item_arr as $pay_transaction_item){
        //     $product_quantity = $pay_transaction_item["quantity"];
        //     $product_amount = $pay_transaction_item["amount"];
        // }
        $pay_total_purchase = $pay_transaction_item_arr["total_purchase"];
        $pay_total_purchase = $pay_total_purchase ?: 0;
        // echo "\n pay_total_purchase $pay_total_purchase";
        $total_redemption += $pay_total_purchase;

        $db->where('business_coin_id', $business_coin_id);
        $db->where('created_at', $from_date, '>=');
        $db->where('created_at', $to_date, '<');
        $user_coin = $db->get('xun_user_coin');

        $cash_reward_issued = '0';
        foreach ($user_coin as $user_key => $user_value) {
            $created_at = $user_value['created_at'];
            $dateWithHour = date("Y-m-d H:00:00", strtotime($created_at));
            $cash_reward_issued = $cash_reward_issued * 3;

            if ($chart_list[$dateWithHour]) {
                $follower_amount = $chart_list[$dateWithHour]['follower'];

                //$chart_list[$dateWithHour]['follower'] = (string) ($follower_amount + 1);
                $chart_list[$dateWithHour]['cash_reward_issued'] = (string) ($cash_reward_issued);
            }

            $total_follower = $total_follower + 1;

        }

        $chart_data = array_values($chart_list);

        $returnData = array(
            "chart_data" => $chart_data,
            "pool_balance" => $wallet_balance,
            'cash_reward_pool_balance' => $cash_reward_wallet_balance ? : '0.00000000',
            "cashpool_balance" => $cashpool_balance,
            "market_floating_fund" => $market_floating_fund,
            "follower" => (string) $total_follower,
            "points_issued" => (string) $total_points_issued,
            "redemption" => (string) $total_redemption,
            "cash_token_issued" => '0.0000',
            "last_login" => $previous_last_login,
        );

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00205') /*Dashboard Statistics*/, 'data' => $returnData);
    }

    public function get_reward_transaction_listing($params)
    {

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id = trim($params['business_id']);
        $follower_mobile = trim($params['follower_mobile']);
        $status = trim($params['status']);
        $from_datetime = $params["date_from"];
        $to_datetime = $params["date_to"];
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]/*Business ID cannot be empty*/);
        }
        if ($status) {
            $status_check = array('success', 'failed', 'pending');
            $status = strtolower($status);
            if (!in_array($status, $status_check)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00386') /*Invalid status.*/);
            }
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        // get business_coin_id from xun_business_coin
        // $db->where("business_id", $business_id);
        // $db->where('type', 'reward');
        // $business_coin_info = $db->getOne("xun_business_coin");
        $business_coin_info = $this->getBusinessCoinDetails($business_id, 'reward');
        if (!$business_coin_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['E00103'][$language]/*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['wallet_type']);

        $date = date("Y-m-d H:i:s");

        if ($follower_mobile) {
            $follower_mobile = "%$follower_mobile%";
            $db->where("username", $follower_mobile, "LIKE");
            $user = $db->get("xun_user", null, "id, username as phone, nickname");

            // get user_ids
            foreach ($user as $x) {
                $user_ids[] = $x['id'];
            }
            if (!empty($user_ids)) {
                $db->where("recipient_user_id", $user_ids, "IN");
            }
        }
        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, ">=");
        }
        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, "<=");
        }
        if ($status) {
            if ($status == 'success') {
                $status = 'completed';
            }
            $status_condition = $status;
            $db->where("status", "%$status_condition%", 'LIKE');
        }
        //else{
        //     $status_condition = array("completed", "pending", "failed");
        //     $db->where("status", $status_condition, "IN");
        // }
        $db->where("address_type", "reward");
        $db->where("wallet_type", $business_coin_wallet_type);
        $db->where('sender_user_id', $business_id);
        $db->orderBy("created_at", $order);
        $copyDb = $db->copy();
        $redeem_info = $db->get("xun_wallet_transaction", $limit, "id, user_id, sender_user_id, recipient_user_id, amount, status, message,  reference_id, created_at");

        if (!$redeem_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['E00103'][$language]/*No Results Found.*/, 'data' => []);
        }

        $totalRecord = $copyDb->getValue("xun_wallet_transaction", "count(id)");

        // print_r($redeem_info);
        foreach ($redeem_info as $redeem) {
            $recipient_ids[] = $redeem['recipient_user_id'];
        }
        if ($recipient_ids) {
            //get submerchant info
            $db->where("id", $recipient_ids, "IN");
            $user_info = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username as phone, nickname");
        }
        // if (!$user_info){
        //     return array("code" => 1, "message" => "SUCCESS", "message_d" => "No Users Found In Reward.", 'data' => []);
        // }

        unset($redeem);
        foreach ($redeem_info as $redeem) {
            $recipient_user_id = $redeem['recipient_user_id'];
            $name = $user_info[$recipient_user_id]['nickname'];
            $mobile = $user_info[$recipient_user_id]['phone'];
            $status = $redeem['status'];
            if ($status == 'completed') {
                $status = "success";
            } elseif ($status == 'wallet_success') {
                $status = "pending";
            }

            $error_message = $redeem['message'];
            $msg_arr = explode("%", $error_message);
            if ($mobile) {
                $user_mobile = $mobile;
            } elseif ($msg_arr) {
                $user_mobile = $msg_arr[1];
            } else {
                $user_mobile = '';
            }
            $strreplace_message = str_replace("%", "", $error_message);

            $redeem_array['name'] = $name ? $name : '';
            $redeem_array['mobile'] = $user_mobile ? $user_mobile : '';
            $redeem_array['amount'] = $redeem['amount'];
            $redeem_array['status'] = ucfirst($status);
            $redeem_array['error_message'] = $strreplace_message ? $strreplace_message : '';
            $redeem_array['created_at'] = $redeem['created_at'];

            $reward_list[] = $redeem_array;
            $total_amount = bcadd($total_amount, $redeem['amount'], 8);
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $returnData["data"] = $reward_list;
        $returnData["total_amount"] = $total_amount;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $num_record;
        $returnData["totalPage"] = $total_page;
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00211') /*Reward Transaction List*/, 'data' => $returnData);
    }

    public function get_coin_transaction_listing($params)
    {
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $page_number = $params['page'] ? $params['page'] : 1;
        $business_id = $params['business_id'];
        $mobile = $params['mobile'];
        $transaction_type = $params['transaction_type'];
        $from_datetime = $params["date_from"];
        $to_datetime = $params["date_to"];
        $see_all = $params["see_all"];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        //$order = $params["order"] ? $params["order"] :"DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // $db->where('business_id', $business_id);
        // $db->where('type', 'reward');
        // $business_coin = $db->getOne('xun_business_coin');
        $business_coin = $this->getBusinessCoinDetails($business_id, 'reward');

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00389') /*Business does not have its own coin.*/);
        }

        $db->where('user_id', $business_id);
        $db->where('address_type', 'reward');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $company_pool_address = $crypto_user_address['external_address'];
        $crypto_user_address_id = $crypto_user_address['id'];

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('a.created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('a.created_at', $to_datetime, '<');
        }

        if ($mobile) {
            $db->where('b.username', "%$mobile%", 'LIKE');
        }

        if ($transaction_type) {
            if ($transaction_type == 'Deposit') {
                $db->where('c.reference_address', $company_pool_address);
            }
            if ($transaction_type == 'Send Reward') {
                $db->where('a.sender_user_id', $business_id);
                $db->where('c.reference_address', $company_pool_address, '!=');
                $db->where('a.recipient_user_id', $business_id, '!=');
            } elseif ($transaction_type == 'Redemption') {
                $db->where('a.recipient_user_id', $business_id);
                $db->where('c.reference_address', $company_pool_address, '!=');
                $db->where('a.sender_user_id', $business_id, '!=');
            } elseif ($transaction_type == 'Transfer') {
                $db->where('a.recipient_user_id', $business_id, '!=');
                $db->where('a.sender_user_id', $business_id, '!=');
            }
        }
        $tx_type_list = array(
            "All", "Deposit", "Send Reward", "Redemption", "Transfer",
        );

        $business_wallet_type = $business_coin['wallet_type'];
        $db->where('a.address_type', 'reward');
        $db->where('c.type', 'send');
        $db->where('a.wallet_type', $business_wallet_type);
        $db->where('c.wallet_type', $business_wallet_type);
        //$db->where('c.type', 'receive');
        $db->join('xun_crypto_callback c', 'c.transaction_hash = a.transaction_hash', 'LEFT');
        $db->join('xun_user b', 'b.id = a.user_id', 'LEFT');
        $copyDb = $db->copy();
        $db->orderBy('a.id', 'DESC');
        $coin_transaction = $db->get('xun_wallet_transaction a', $limit, 'a.*, b.username, b.nickname, c.reference_address');

        if (!$coin_transaction) {
            $error_tx_list['tx_type_list'] = $tx_type_list;
            $data['data'] = $error_tx_list;
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['E00103'][$language]/*No Results Found.*/, 'data' => $data);
        }

        $totalRecord = $copyDb->getValue('xun_wallet_transaction a', 'count(a.id)');

        $coin_transaction_list = [];
        $total_amount_in = "0";
        $total_amount_out = "0";
        if ($coin_transaction) {
            $user_id_arr = [];
            $reference_address_arr = [];
            foreach ($coin_transaction as $tx_key => $tx_value) {
                $sender_user_id = $tx_value['sender_user_id'];
                $recipient_user_id = $tx_value['recipient_user_id'];
                $reference_address = $tx_value['reference_address'];

                if (!in_array($sender_user_id, $user_id_arr)) {
                    array_push($user_id_arr, $sender_user_id);
                }

                if (!in_array($recipient_user_id, $user_id_arr)) {
                    array_push($user_id_arr, $recipient_user_id);
                }

            }

            $db->where('crypto_user_address_id', $crypto_user_address_id);
            $user_crypto_external_address = $db->map('external_address')->ArrayBuilder()->get('xun_user_crypto_external_address');

            $employee_user_ids = array_column($user_crypto_external_address, 'user_id');
            $user_id_arr = array_merge($user_id_arr, $employee_user_ids);

            $db->where('id', $user_id_arr, 'IN');
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user');

            foreach ($xun_user as $key => $value) {
                if ($value['type'] == 'business') {
                    $business_id_arr[] = $value['id'];
                }
            }

            $db->where('user_id', $business_id_arr, 'IN');
            $xun_business_account = $db->map('user_id')->ArrayBuilder()->get('xun_business_account', null, 'id, user_id, main_mobile');

            $db->where('user_id', $business_id_arr, 'IN');
            $xun_business = $db->map('user_id')->ArrayBuilder()->get('xun_business', null, 'id, user_id, name');

            foreach ($coin_transaction as $key => $value) {

                $sender_user_id = $value['sender_user_id'];
                $recipient_user_id = $value['recipient_user_id'];
                $nickname = $value['nickname'];
                $mobile = $value['username'] ? $value['username'] : $xun_business_account[$sender_user_id]['main_mobile'];
                $amount_in = "0";
                $amount_out = "0";

                $reference_address = $value['reference_address'];

                if ($reference_address == $company_pool_address) {
                    $transaction_type = 'Deposit';
                    $amount_in = $value['amount'];

                    $to_from = $xun_user[$recipient_user_id]['nickname'] ? $xun_user[$recipient_user_id]['nickname'] : $xun_business[$recipient_user_id]['name'];
                } elseif ($business_id == $sender_user_id) {
                    $transaction_type = 'Send Reward';
                    $to_from = $xun_business[$business_id]['name'];
                    $nickname = $xun_user[$recipient_user_id]['nickname'];
                    $mobile = $xun_user[$recipient_user_id]['username'] ? $xun_user[$recipient_user_id]['username'] : $xun_business_account[$recipient_user_id]['main_mobile'];
                    $amount_out = $value['amount'];

                } elseif ($business_id == $recipient_user_id) {
                    $transaction_type = 'Redemption';
                    $amount_in = $value['amount'];
                    $user_id = $user_crypto_external_address[$reference_address]['user_id'];
                    $to_from = $xun_user[$user_id]['nickname'] ? $xun_user[$user_id]['nickname'] : $xun_business[$user_id]['name'];
                } else {
                    $transaction_type = 'Transfer';
                    $to_from = $xun_user[$recipient_user_id]['nickname'] ? $xun_user[$recipient_user_id]['nickname'] : $xun_business[$recipient_user_id]['name'];
                    $amount_out = $value['amount'];
                }

                $reference_id = $value['reference_id'];
                $created_at = $value['created_at'];
                $coin_transaction_arr = array(
                    "name" => $nickname,
                    "mobile" => $mobile,
                    "transaction_type" => $transaction_type,
                    "amount_in" => $amount_in,
                    "amount_out" => $amount_out,
                    "reference_no" => $reference_id,
                    "to_from" => $to_from,
                    "created_at" => $created_at,

                );

                $coin_transaction_list[] = $coin_transaction_arr;
                $total_amount_in = bcadd($total_amount_in, $amount_in, 8);
                $total_amount_out = bcadd($total_amount_out, $amount_out, 8);
            }

        }

        $data['tx_type_list'] = $tx_type_list;
        $data['coin_transaction_list'] = $coin_transaction_list;

        $num_record = !$see_all ? count($coin_transaction_list) : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $returnData["data"] = $data;
        $returnData['total_amount_in'] = $total_amount_in;
        $returnData['total_amount_out'] = $total_amount_out;
        $returnData['totalPage'] = $total_page;
        $returnData['pageNumber'] = $page_number;
        $returnData['totalRecord'] = $totalRecord;
        $returnData['numRecord'] = $num_record;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00212') /*Coin Transaction List.*/, 'data' => $returnData);

    }

    public function web_get_business_rewards_address($params)
    {
        $db = $this->db;

        $business_id = trim($params["business_id"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00390') /*Business ID is required.*/);
        }
        $business_rewards_address = $this->get_business_rewards_address($business_id);

        if (isset($business_rewards_address["code"]) && $business_rewards_address["code"] == 0) {
            return $business_rewards_address;
        }

        $return_data = [];
        $return_data["address"] = $business_rewards_address["external_address"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00213') /*Business Rewards Address.*/, "data" => $business_rewards_address);
    }

    public function app_generate_payment_address($params)
    {
        global $xunBusinessPartner, $xunCrypto;
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $date = date("Y-m-d H:i:s");
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00390') /*Business ID is required.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist*/);
        }

        $user_id = $xun_user["id"];

        $db->where('id', $business_id);
        $db->where('type', 'business');
        $business_result = $db->getOne('xun_user');

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        /**
         * check if business have business coin
         * check if user have address generated in xun_user_crypto_external_address
         *
         */
        $business_coin = $xunBusinessPartner->get_business_coin($business_id);
        if (!$business_coin) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00399') /*No rewards coin found.*/,
            );
        }
        $wallet_type = $business_coin["wallet_type"];

        if (!$wallet_type) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00399') /*No rewards coin found.*/,
            );
        }

        $address_type = "reward";
        $db->where("user_id", $business_id);
        $db->where("address_type", $address_type);
        $business_reward_address_data = $db->getOne("xun_crypto_user_address");

        if (!$business_reward_address_data) {

            //  create business reward address
            $business_reward_address_data = $this->get_business_rewards_address($business_id);

            if (isset($business_reward_address_data["code"]) && $business_reward_address_data["code"] == 0) {
                return $business_reward_address_data;
            }

            $rewards_internal_address = $business_reward_address_data["internal_address"];
            $crypto_user_address_id = $business_reward_address_data["crypto_user_address_id"];

            $has_employee_external_address = false;
        } else {
            $rewards_internal_address = $business_reward_address_data["address"];
            $crypto_user_address_id = $business_reward_address_data["id"];
            //  check if employee have external address
            $db->where("user_id", $user_id);
            $db->where("crypto_user_address_id", $crypto_user_address_id);
            $employee_external_address_data = $db->getOne("xun_user_crypto_external_address");

            $has_employee_external_address = false;
            if ($employee_external_address_data) {
                $has_employee_external_address = true;
                $employee_external_address = $employee_external_address_data["external_address"];
            }
        }

        if (!$has_employee_external_address) {

            //  generate employee external address

            $crypto_result = $xunCrypto->crypto_bc_create_multi_wallet($rewards_internal_address, $wallet_type);

            try {
                if ($crypto_result["status"] == "ok") {
                    $crypto_data = $crypto_result["data"];
                    $external_address = $crypto_data["address"];

                    if (!$external_address) {
                        throw new Exception($this->get_translation_message('E00392') /*Error creating external address*/, 999);
                    }

                    //  save external address as user's external address

                    // $user_address_obj->externalAddress = $external_address;

                    // $xun_user_service->insertCryptoExternalAddress($user_address_obj);
                } else {
                    $status_message = $crypto_result["statusMsg"];

                    throw new Exception($status_message);
                }
            } catch (Exception $e) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $e->getMessage(),
                );
            }

            $employee_external_address = $external_address;

            $insert_data = array(
                "user_id" => $user_id,
                "crypto_user_address_id" => $crypto_user_address_id,
                "external_address" => $external_address,
                "address_type" => $address_type,
                "wallet_type" => $wallet_type,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $row_id = $db->insert("xun_user_crypto_external_address", $insert_data);
            if (!$row_id) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                    "error_message" => $db->getLastMessage(),
                );
            }
        }

        $db->where("currency_id", $wallet_type);
        $currency_info = $db->getOne("xun_marketplace_currencies", "symbol, id");

        $wallet_unit = $currency_info["symbol"];

        $db->where('name', 'selectedFiatCurrency');
        $db->where('user_id', $business_id);
        $user_setting = $db->getOne('xun_user_setting');

        $db->where('user_id', $business_id);
        $business_owner = $db->getOne('xun_business_account', 'id, user_id,  main_mobile');

        $business_owner_mobile = $business_owner['main_mobile'];
        if ($user_setting['value']) {
            $owner_fiat_currency_id = strtolower($user_setting['value']);
        } elseif ($business_owner) {
            $mobileNumberInfo = $general->mobileNumberInfo($business_owner_mobile, null);
            $country_code = $mobileNumberInfo['countryCode'];

            $db->where('country_code', $country_code);
            $country_result = $db->getOne('country');
            $owner_fiat_currency_id = $country_result['currency_code'] ? strtolower($country_result['currency_code']) : 'usd';

        }

        $return_data = array(
            "internal_address" => $rewards_internal_address,
            "external_address" => $employee_external_address,
            "wallet_type" => $wallet_type,
            "unit" => $wallet_unit,
            "owner_fiat_currency_id" => $owner_fiat_currency_id,
        );

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00217') /*Payment address details*/,
            "data" => $return_data,
        );
    }

    public function get_business_rewards_address($business_id)
    {
        global $xunBusinessPartner;
        $db = $this->db;
        $post = $this->post;
        $setting = $this->setting;
        $general = $this->general;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        //  get wallet type
        $business_coin_data = $xunBusinessPartner->get_business_coin($business_id);
        if (!$business_coin_data) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00399') /*No rewards coin found.*/,
            );
        }

        $wallet_type = $business_coin_data["wallet_type"];
        $address_type = "reward";

        $xunBusinessService = new XunBusinessService($db);
        // $business = $xunBusinessService->getBusinessByBusinessID($businessID);
        $crypto_user_address = $xunBusinessService->getActiveAddressByUserIDandType($business_id, $address_type);

        $return_data = [];
        $return_data["wallet_type"] = $wallet_type;

        if ($crypto_user_address) {
            $return_data["internal_address"] = $crypto_user_address["address"];
            $return_data["external_address"] = $crypto_user_address["external_address"];
            $return_data["crypto_user_address_id"] = $crypto_user_address["id"];

            return $return_data;
        }

        //  generate addrsss
        $walletResponse = $xunCompanyWallet->createUserServerWallet($business_id, $address_type, $wallet_type);

        if ($walletResponse["code"] == 1) {
            $walletData = $walletResponse["data"];
            $walletData = $walletData;
            $address = $walletData["address"];
            $external_address = $walletData["externalAddress"];

            $userObj = new stdClass();
            $userObj->userID = $business_id;
            $userObj->addressType = $address_type;
            $userObj->internalAddress = $address;
            $userObj->externalAddress = $external_address;
            $userObj->walletType = $wallet_type;

            if ($external_address) {
                $res = $xunBusinessService->setActiveWalletAddress($userObj);
                // return array("code" => 1, "id" => $row_id);
                $crypto_user_address_id = $res["id"];
                $return_data["internal_address"] = $address;
                $return_data["external_address"] = $external_address;
                $return_data["crypto_user_address_id"] = $crypto_user_address_id;
                return $return_data;
            } else {
                $xunBusinessService->setActiveWalletAddress($userObj);
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00141'][$language], /**  Internal server error. Please try again later. */
                );
            }
        } else {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $translations['E00141'][$language], /**  Internal server error. Please try again later. */
            );
        }
    }

    public function process_redemmption($params, $wallet_transaction_id)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $sender_user_id = $params["sender_user_id"];
        $business_id = $params["receiver_user_id"];
        $wallet_type = $params["wallet_type"];
        $amount = $params["amount"];
        $status = $params["status"];

        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $reward_details = $db->getOne("xun_business_reward_transaction_details");

        if ($reward_details) {
            if ($reward_details["status"] == $status) {
                return;
            }

            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = $status;

            $db->where("id", $reward_details["id"]);
            $db->update("xun_business_reward_transaction_details", $update_data);
        } else {
            $insert_reward_details = array(
                "receiver_user_id" => $sender_user_id,
                "business_id" => $business_id,
                "wallet_type" => $wallet_type,
                "amount" => $amount,
                "transaction_type" => 'redeem',
                "status" => $status,
                "wallet_transaction_id" => $wallet_transaction_id,
                "business_reference" => '',
                "created_at" => $date,
                "updated_at" => $date,

            );

            $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);
            // if(!$reward_details_id){
            //     print_r($db->getLastError());
            // }
        }

    }

    public function update_redemption_reference($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $transaction_hash = trim($params["transaction_hash"]);
        $reference_no = trim($params["reference_no"]);

        $date = date("Y-m-d H:i:s");
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00394') /*Transaction Hash is required.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist*/);
        }

        $user_id = $xun_user["id"];

        // $db->where("a.transaction_hash", $transaction_hash);
        // $db->join("xun_business_reward_transaction_details b", "a.id=b.wallet_transaction_id");
        // $business_reward_details = $db->getOne("xun_wallet_transaction a", "b.*");
        $db->where("transaction_hash", $transaction_hash);
        $wallet_transaction = $db->getOne("xun_wallet_transaction", "id, sender_user_id, recipient_user_id, transaction_hash");

        if (!$wallet_transaction) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00396'), /*Invalid transaction hash*/
            );
        }

        $update_data = [];
        $update_data["updated_at"] = $date;
        $update_data["receiver_reference"] = $reference_no;

        $db->where("id", $wallet_transaction["id"]);
        $db->update("xun_wallet_transaction", $update_data);

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('E00397'), /*Success*/
        );
    }

    public function process_send_reward($params, $wallet_transaction_id)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $sender_user_id = $params["sender_user_id"];
        $business_id = $params["receiver_user_id"];
        $wallet_type = $params["wallet_type"];
        $amount = $params["amount"];
        $status = $params["status"];

        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $reward_details = $db->getOne("xun_business_reward_transaction_details");

        if ($reward_details) {
            if ($reward_details["status"] == $status) {
                return;
            }

            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = $status;

            $db->where("id", $reward_details["id"]);
            $db->update("xun_business_reward_transaction_details", $update_data);
        } else {
            $insert_reward_details = array(
                "receiver_user_id" => $sender_user_id,
                "business_id" => $business_id,
                "wallet_type" => $wallet_type,
                "amount" => $amount,
                "transaction_type" => 'redeem',
                "status" => $status,
                "wallet_transaction_id" => $wallet_transaction_id,
                "business_reference" => '',
                "created_at" => $date,
                "updated_at" => $date,

            );

            $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);

        }
    }

    public function reward_follow_count($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        $db->where('business_id', $business_id);
        $db->where('type', 'reward');
        $business_coin = $db->getOne('xun_business_coin');

        $business_coin_id = $business_coin['id'];

        $db->where('business_coin_id', $business_coin_id);
        $total_reward_follower = $db->getValue('xun_user_coin', 'count(id)');

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00218') /*Reward follow count*/, "total_reward_follower" => $total_reward_follower);

    }

    private function insert_wallet_transaction($business_id, $business_cp_address, $recipient_address, $receiver_user_id, $reward_amount, $business_wallet_type, $status, $error_message, $address_type = 'reward')
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insert_wallet_tx = array(
            "user_id" => $business_id,
            "sender_address" => $business_cp_address,
            "recipient_address" => $recipient_address,
            "sender_user_id" => $business_id,
            "recipient_user_id" => $receiver_user_id,
            "amount" => $reward_amount,
            "wallet_type" => $business_wallet_type,
            "fee" => '',
            "fee_unit" => '',
            "transaction_hash" => '',
            "transaction_token" => '',
            "status" => $status,
            "transaction_type" => 'send',
            "escrow" => '0',
            "escrow" => '',
            "reference_id" => '',
            "batch_id" => '',
            "message" => $error_message,
            "expires_at" => '',
            "address_type" => $address_type,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);
        if(!$wallet_tx_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }
    
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function set_business_reward_setting($params)
    {
        $db = $this->db;
        $general = $this->general;

        $business_id = trim($params['business_id']);
        $min_amount = trim($params['min_amount']);
        $max_amount = trim($params['max_amount']);
        $auto_send = trim($params['auto_send']);
        $reward_amount = trim($params['reward_amount']);
        $send_reward_max_amount = trim($params['send_reward_max_amount']);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        $db->where('user_id', $business_id);
        $business_account = $db->getOne('xun_business');

        if (!$business_account) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $business_acc_id = $business_account['user_id'];

        if ($min_amount != '' || $max_amount != '' || $reward_amount != '' || $send_reward_max_amount != '') {

            $db->where("business_id", $business_acc_id);
            $db->where('type', 'reward');
            $wallet_type = $db->getValue("xun_business_coin", "wallet_type");

            $db->where("cryptocurrency_id", $wallet_type);
            $coin_rate_in_usd = $db->getValue("xun_cryptocurrency_rate", "value");

            $db->where("currency_id", $wallet_type);
            $unit_conversion = $db->getValue("xun_marketplace_currencies", "unit_conversion");
            $unit_decimal_place = log10($unit_conversion);

            if ($max_amount != '' && $min_amount != '') {
                if ($max_amount != 0) {
                    if ($max_amount < $min_amount) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00452') /*Maximum should more than Minimum.*/);
                    }
                }
            }

            if ($min_amount != '') {
                if (!$general->checkDecimalPlaces($min_amount, $unit_conversion)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00453') /*Minimum Amount Decimal Exceeded.*/);
                }
                $reward_setting_update["min_amount"] = $min_amount;
            }
            if ($max_amount != '') {
                if (!$general->checkDecimalPlaces($max_amount, $unit_conversion)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00454') /*Maximum Amount Decimal Exceeded.*/);
                }
                $reward_setting_update["max_amount"] = $max_amount;
            }

            if ($reward_amount != '' && $send_reward_max_amount == '') {
                $db->where("user_id", $business_acc_id);
                $db->where("type", "reward");
                $default_send_reward_max = $db->getValue("xun_business_reward_setting", "reward_sending_limit");

                if ($default_send_reward_max == 0) {
                    $max_reward_params['coin_rate_in_usd'] = $coin_rate_in_usd;
                    $max_reward_params['unit_decimal_place'] = $unit_decimal_place;
                    $default_send_reward_max = $this->default_send_reward_maximum($max_reward_params);
                }
                if ($reward_amount > $default_send_reward_max) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00455') /*Reward Amount should not more than Reward Maximum Limit.*/);
                }
            }

            if ($reward_amount != '' && $send_reward_max_amount != '') {
                if ($send_reward_max_amount) {
                    if ($reward_amount > $send_reward_max_amount) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00455') /*Reward Amount should not more than Reward Maximum Limit.*/);
                    }
                }
            }

            if ($reward_amount != '') {
                if (!$general->checkDecimalPlaces($reward_amount, $unit_conversion)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00456') /*Reward Amount Decimal Exceeded.*/);
                }
                $reward_setting_update["reward_amount"] = $reward_amount;
            }
            if ($send_reward_max_amount != '') {
                if (!$general->checkDecimalPlaces($send_reward_max_amount, $unit_conversion)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00457') /*Sending Reward Maximum Amount Decimal Exceeded.*/);
                }
                $reward_setting_update["reward_sending_limit"] = $send_reward_max_amount;
            }
        }

        if ($auto_send != '') {
            if ($auto_send != 0 && $auto_send != 1) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00458') /*Invalid Auto Send Value.*/);
            }
            $reward_setting_update["auto_send"] = $auto_send;
        }
        $reward_setting_update["updated_at"] = date("Y-m-d H:i:s");

        $db->where("user_id", $business_acc_id);
        $db->where("type", "reward");
        $reward_record = $db->getOne("xun_business_reward_setting");
        if ($reward_record) {
            $db->where("user_id", $business_acc_id);
            $db->where("type", "reward");
            $setting_update = $db->update("xun_business_reward_setting", $reward_setting_update);
        } else {
            $reward_setting_update['user_id'] = $business_acc_id;
            $reward_setting_update['type'] = "reward";
            $reward_setting_update['created_at'] = date("Y-m-d H:i:s");
            $setting_update = $db->insert("xun_business_reward_setting", $reward_setting_update);
        }

        if (!$setting_update) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00451') /*Set Reward Setting Failed.*/, "developer_msg" => $db->getLastError());
        } else {
            if ($auto_send == 1) {
                try {
                    $auto_send_params['business_acc_id'] = $business_acc_id;
                    $auto_send_params['wallet_type'] = $wallet_type;
                    $auto_send_params['reward_amount'] = $reward_amount;
                    $send_welcome_reward = $this->auto_send_welcome_reward($auto_send_params);
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "developer_msg" => $db->getLastError());
                }
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00247') /*Reward settings updated.*/, "developer_msg" => $send_welcome_reward);

    }

    public function get_business_reward_setting($params)
    {
        $db = $this->db;

        $business_id = trim($params['business_id']);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }
        $db->where('user_id', $business_id);
        $business_account = $db->getOne('xun_business');

        if (!$business_account) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $business_acc_id = $business_account['user_id'];
        $db->where("user_id", $business_acc_id);
        $db->where("type", "reward");
        $reward_setting = $db->getOne("xun_business_reward_setting");

        if (!$reward_setting) {
            $insert_array = array(
                "user_id" => $business_acc_id,
                "type" => "reward",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->insert("xun_business_reward_setting", $insert_array);

            $db->where("user_id", $business_acc_id);
            $reward_setting = $db->getOne("xun_business_reward_setting");
        }

        // to get unit decimal places //
        $db->where("business_id", $business_acc_id);
        $db->where('type', 'reward');
        $wallet_type = $db->getValue("xun_business_coin", "wallet_type");

        $db->where("cryptocurrency_id", $wallet_type);
        $coin_rate_in_usd = $db->getValue("xun_cryptocurrency_rate", "value");

        $db->where("currency_id", $wallet_type);
        $unit_conversion = $db->getValue("xun_marketplace_currencies", "unit_conversion");
        $unit_decimal_place = log10($unit_conversion);
        // //

        $reward_max_limit = $reward_setting["reward_sending_limit"];
        if ($reward_max_limit == 0) {
            $max_reward_params['coin_rate_in_usd'] = $coin_rate_in_usd;
            $max_reward_params['unit_decimal_place'] = $unit_decimal_place;
            $reward_max_limit = $this->default_send_reward_maximum($max_reward_params);
        }

        $return_data["min_amount"] = number_format($reward_setting["min_amount"], $unit_decimal_place, ".", "");
        $return_data["max_amount"] = number_format($reward_setting["max_amount"], $unit_decimal_place, ".", "");
        $return_data["auto_send"] = $reward_setting["auto_send"];
        $return_data["reward_amount"] = number_format($reward_setting["reward_amount"], $unit_decimal_place, ".", "");
        $return_data["send_reward_max_amount"] = number_format($reward_max_limit, $unit_decimal_place, ".", "");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00226') /*Success.*/, "data" => $return_data);

    }

    private function default_send_reward_maximum($params)
    {
        $db = $this->db;

        $coin_rate_in_usd = $params['coin_rate_in_usd'];
        $unit_decimal_place = $params['unit_decimal_place'];

        //to get default reward max limit info
        $db->where("name", "sendingRewardMaxValue", "=", "OR");
        $db->where("name", "sendingRewardMaxUnit", "=", "OR");
        $limit_info = $db->map("name")->ArrayBuilder()->get("system_settings", null, "name, value");

        $limit_unit = $limit_info['sendingRewardMaxUnit'];
        $limit_value = $limit_info['sendingRewardMaxValue'];
        // print_r($limit_info);

        $db->where("currency", $limit_unit);
        $limit_rate = $db->getValue("xun_currency_rate", "exchange_rate");

        $value_per_coin = bcmul((string) $coin_rate_in_usd, (string) $limit_rate, 8);
        $reward_max_limit = bcdiv((string) $limit_value, (string) $value_per_coin, $unit_decimal_place);

        return $reward_max_limit;
    }

    public function business_my_followers($params)
    {
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        global $xunCrypto, $setting;

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";
        $business_id = trim($params['business_id']);
        $phone_number = trim($params['mobile']);
        $from_date = trim($params['from_date']);
        $to_date = trim($params['to_date']);
        $see_all = trim($params["see_all"]);
        $reward_type = trim($params['reward_type']);

        $payWalletAddress = $setting->systemSetting['payWalletAddress'];

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($reward_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00511') /*Reward Type cannot be empty.*/);
        }

        if($reward_type == 'reward_point'){
            $type = 'reward';
        }
        elseif($reward_type == 'reward_cash'){
            $type = 'cash_token';
        }
        else{
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00512') /*Invalid Reward Type.*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where('a.user_id', $business_id);
        $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
        $xun_business_account = $db->getOne('xun_business_account a');

        $owner_user_id = $xun_business_account['id'];

        // $db->where("business_id", $business_id);
        // $db->where('type', 'reward');
        // $business_coin_info = $db->getOne("xun_business_coin");

        $business_coin_info = $this->getBusinessCoinDetails($business_id, $type);
        if (!$business_coin_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['wallet_type']);

        if ($from_date) {
            $from_date = date("Y-m-d H:i:s", $from_date);
            $db->where("b.created_at", $from_date, ">=");
        }
        if ($to_date) {
            $to_date = date("Y-m-d H:i:s", $to_date);
            $db->where("b.created_at", $to_date, "<=");
        }
        if ($phone_number) {
            $phone_number = "%$phone_number%";
            $db->where("a.username", $phone_number, "LIKE");
        }

        $db->orderBy("b.created_at", $order);
        $db->where("b.business_coin_id", $business_coin_id);
        $db->where('b.user_id', $owner_user_id, '!=');
        $db->where("a.type", "user");
        $db->join('xun_user a', 'a.id= b.user_id', 'LEFT');
        $copyDb = $db->copy();
        $follower_array = $db->get("xun_user_coin b", $limit, "b.*, a.username, a.nickname");

        if (!$follower_array) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        foreach ($follower_array as $follower) {
            $follower_username[] = $follower['username'];
            $follower_since[$follower['username']] = $follower['created_at'];
            $user_ids[] = $follower['user_id'];
        }

        $totalRecord = $copyDb->getValue("xun_user_coin b", "count(b.id)");

        // $db->where("username", $follower_username, "IN");
        // $user = $db->get("xun_user", null, "id, username as phone, nickname");

        // get user_ids
        // foreach($user as $x){
        //     $user_ids[] = $x['id'];
        // }

        // print_r($db->getLastQuery());
        // get business_coin_id from xun_business_coin

        $db->where("currency_id", $business_coin_wallet_type);
        $unit_conversion = $db->getValue("xun_coins", "unit_conversion");

        $db->where('user_id', $business_id);
        $db->where('address_type', 'reward');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $company_pool_address = $crypto_user_address['external_address'];

        // get internal address by using user_ids
        if (!empty($user_ids)) {
            $db->where("user_id", $user_ids, "IN");
            $db->where("active", "1");
            $db->where("address_type", "personal");
            $internal_address = $db->get("xun_crypto_user_address", null, "id, user_id, address");
        } else {
            $internal_address = [];
        }

        // business_get_wallet_info
        foreach ($internal_address as $user_address) {
            foreach ($user_address as $key => $value) {
                if ($key == "user_id") {
                    $user_id = $value;
                    // print_r($value);
                }
                if ($key == "address") {
                    $wallet_info[$user_id] = $xunCrypto->get_wallet_info($value, $business_coin_wallet_type);
                }
            }
        }

        if (!empty($user_ids)) {
            if($reward_type == 'reward_point'){
                $db->where("a.address_type", "reward");
                $db->where("a.wallet_type", $business_coin_wallet_type);
                $db->where("a.sender_user_id", $user_ids, "IN");
                // $db->where("a.sender_user_id", array("283", "11391"), "IN");
                $db->where("a.status", "completed");
                $db->where('b.reference_address', $company_pool_address, '!=');
                $db->where('b.type', 'send');
                $db->orderBy("a.updated_at", "DESC");
                $db->join('xun_crypto_callback b', 'a.transaction_hash = b.transaction_hash', 'LEFT');
                $redeem_info = $db->get("xun_wallet_transaction a", null, "a.user_id, a.sender_user_id, a.amount, a.updated_at, b.reference_address, b.type");
            }
            elseif($reward_type == 'reward_cash'){
                $db->where('recipient_address', $payWalletAddress);
                $db->where('wallet_type', $business_coin_wallet_type);
                $redeem_info = $db->get('xun_wallet_transaction', null, 'user_id, sender_user_id, amount, updated_at');
            }
        }
        // if (!$redeem_info){
        //     return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        // }

        // print_r($redeem_info);
        $redeem_array = [];
        if ($redeem_info) {
            foreach ($redeem_info as $redeem) {
                $redeem_array[$redeem['sender_user_id']][] = $redeem;
            }
            unset($redeem);
        }

        $overall_total_used = "0.00000000";
        $total_reward_balance = "0.00000000";
        $total_last_used = "0.00000000";

        foreach ($follower_array as $x) {
            $user_id = $x['user_id'];
            foreach ($x as $key => $value) {
                if ($key == "user_id") {
                    $id = $value;
                    $return_data[$id]['user_id'] = $x['user_id'];
                    $return_data[$id]['phone'] = $x['username'];
                    $return_data[$id]['name'] = $x['nickname'];
                    // print_r($id);
                    $return_data[$id]['reward_balance'] = bcdiv((string) $wallet_info[$id][$business_coin_wallet_type]["balance"], (string) $unit_conversion, "8");
                    $return_data[$id]['total_redeemed'] = "";
                    foreach ($redeem_array[$id] as $redeem) {
                        $return_data[$id]['total_redeemed'] += $redeem["amount"];
                    }
                    $return_data[$id]['last_redeem_date'] = $redeem_array[$id][0]['updated_at'] ? $redeem_array[$id][0]['updated_at'] : '';
                    $return_data[$id]['last_redeem'] = $redeem_array[$id][0]['amount'] ? $redeem_array[$id][0]['amount'] : '';
                    $total_reward_balance = bcadd($total_reward_balance, $return_data[$id]['reward_balance'], "8");
                    $total_last_used = bcadd($total_last_used, $return_data[$id]['last_redeem'], '8');
                    $overall_total_used = bcadd($overall_total_used, $return_data[$id]['total_redeemed'], "8");
                
                
                   
                }
                if ($key == "username") {
                    $return_data[$id]['follow_since'] = $follower_since[$value];
                }

            }
        }

        $return_data = array_values($return_data);
        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $data["result"] = $return_data;
        $data['total_reward_balance'] = $total_reward_balance;
        $data['total_last_redeem'] = $total_last_used;
        $data['overall_total_redeemed'] = $overall_total_used;
        $data["totalRecord"] = $totalRecord;
        $data["numRecord"] = $num_record;
        $data["totalPage"] = $total_page;
        $data["pageNumber"] = $page_number;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "data" => $data);
    }

    private function auto_send_welcome_reward($params)
    {
        $db = $this->db;
        global $xunCrypto;

        $business_id = $params['business_acc_id'];
        $wallet_type = $params['wallet_type'];
        $reward_amount = $params['reward_amount'];

        // $db->where("business_id", $business_id);
        // $business_coin_data = $db->getOne("xun_business_coin", "id, wallet_type");

        $business_coin_data = $this->getBusinessCoinDetails($business_id, 'reward', 'id, wallet_type');

        if ($wallet_type == "") {
            $wallet_type = $business_coin_data['wallet_type'];
        }

        $coin_id = $business_coin_data['id'];

        if ($reward_amount == '') {
            $db->where("user_id", $business_id);
            $db->where("type", "reward");
            $reward_amount = $db->getValue("xun_business_reward_setting", "reward_amount");
        }

        $amount_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $reward_amount);

        // $db->where("wallet_type", $wallet_type);
        // $xun_business_coin_ids = $db->get("xun_business_coin", null, "id");

        // //get business coin ids
        // foreach($xun_business_coin_ids as $business_coin_id){
        //     $business_coin_ids[] = $business_coin_id['id'];
        // }

        $db->where("business_coin_id", $coin_id);
        $xun_coin_user_info = $db->get("xun_user_coin", null, 'user_id, business_coin_id');

        //get the business's follower
        foreach ($xun_coin_user_info as $coin_user_info) {
            $user_ids[] = $coin_user_info['user_id'];
            // $user_coin[$coin_user_info['user_id']] = $coin_user_info['business_coin_id'];
        }

        //check the users received the reward or not

        //check in business_reward_payout_transaction table
        // $db->where("b.status", array("completed", "pending"), "IN");
        $reward_payout_tx = [];
        if (!empty($user_ids)) {
            $db->where("a.business_coin_id", $coin_id);
            $db->where("b.recipient_user_id", $user_ids, "IN");
            $db->join("xun_wallet_transaction b", "a.wallet_tx_id=b.id");
            $reward_payout_tx = $db->get("xun_business_reward_payout_transaction a", null, "a.id, b.transaction_hash, b.recipient_user_id, b.status");
        }

        if ($reward_payout_tx) {
            //get new users or failed case
            foreach ($reward_payout_tx as $payout_tx) {
                $existing_user[] = $payout_tx['recipient_user_id'];
            }
            foreach ($user_ids as $user_id) {
                if (!in_array($user_id, $existing_user)) {
                    $new_users_id[] = $user_id;
                }
            }
        } else {
            $new_users_id = $user_ids;
        }

        if ($new_users_id == "") {
            return;
        }

        $db->where("user_id", $new_users_id, "IN");
        $db->where("active", "1");
        $crypto_address_info = $db->map("user_id")->ArrayBuilder()->get("xun_crypto_user_address", null, "id, user_id, address");

        $new_users_id = array_unique($new_users_id);

        $db->where("user_id", $business_id);
        $db->where("active", "1");
        $db->where("address_type", "reward");
        $sender_address_info = $db->getOne("xun_crypto_user_address", "id, user_id, address");
        if (!$sender_address_info) {
            return;
        }

        $sender_address = $sender_address_info["address"];
        $sender_address_id = $sender_address_info["id"];

        //new user
        $i = 0;
        foreach ($new_users_id as $new_user) {
            $new_user_address_info[$i]['user_id'] = $new_user;
            $new_user_address_info[$i]['address'] = $crypto_address_info[$new_user]['address'];
            $new_user_address_info[$i]['address_id'] = $crypto_address_info[$new_user]['id'];
            $i++;
        }

        //new users
        foreach ($new_user_address_info as $new_address_info) {
            $receiver_user_id = $new_address_info['user_id'];
            $receiver_address = $new_address_info['address'];
            $receiver_address_id = $new_address_info['address_id'];
            $wallet_tx_insert = array(
                "user_id" => $business_id,
                "sender_address" => $sender_address,
                "recipient_address" => $receiver_address,
                "sender_user_id" => $business_id,
                "recipient_user_id" => $receiver_user_id,
                "amount" => $reward_amount,
                "wallet_type" => $wallet_type,
                "status" => "pending",
                "transaction_type" => "send",
                "address_type" => "reward",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $wallet_tx_id = $db->insert("xun_wallet_transaction", $wallet_tx_insert);
            if (!$wallet_tx_id) {
                throw new Exception($db->getLastError());
            }

            $sending_queue_insert = array(
                "sender_crypto_user_address_id" => $sender_address_id,
                "receiver_crypto_user_address_id" => $receiver_address_id,
                "receiver_user_id" => $receiver_user_id,
                "amount" => $reward_amount,
                "amount_satoshi" => $amount_satoshi,
                "wallet_type" => $wallet_type,
                "status" => "pending",
                "wallet_transaction_id" => $wallet_tx_id,
                "processed" => "0",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $sending_queue_id = $db->insert("wallet_server_sending_queue", $sending_queue_insert);
            if (!$sending_queue_id) {
                throw new Exception($db->getLastError());
            }

            $business_reward_payout_insert = array(
                "user_id" => $receiver_user_id,
                "business_coin_id" => $coin_id,
                "wallet_tx_id" => $wallet_tx_id,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $business_reward_payout_id = $db->insert("xun_business_reward_payout_transaction", $business_reward_payout_insert);
            if (!$business_reward_payout_id) {
                throw new Exception($db->getLastError());
            }
        }
        return;
    }

    public function new_follower_send_welcome_reward($params)
    {
        // user_id, business_id, wallet_type, business_coin_id
        global $xunCrypto;
        $db = $this->db;

        $user_id = $params["user_id"];
        $business_id = $params["business_id"];
        $wallet_type = $params["wallet_type"];
        $business_coin_id = $params["business_coin_id"];
        $date = date("Y-m-d H:i:s");

        if ($business_id == "" && $business_coin_id == "") {
            if ($user_id == '') {
                return;
                // return "FAILED USER ID";
            }
            $db->where("user_id", $user_id);
            $business_coin_ids = $db->get("xun_user_coin", null, "business_coin_id");

            foreach ($business_coin_ids as $coin_ids) {
                $business_coin_id_arry[] = $coin_ids['business_coin_id'];
            }

            $db->where("id", $business_coin_id_arry, "IN");
            $business_coin_info = $db->get("xun_business_coin", null, "id, business_id, wallet_type");

        } else {
            if ($wallet_type == '' || $business_coin_id == '') {
                if ($business_coin_id) {
                    $db->where("id", $business_coin_id);
                }
                if ($wallet_type) {
                    $db->where("wallet_type", $wallet_type);
                }
                if ($business_id) {
                    $db->where("business_id", $business_id);
                }
                $business_coin_info = $db->get("xun_business_coin", null, "id, business_id, wallet_type");

                // $wallet_type = $business_coin_info['wallet_type'];
                // $business_coin_id = $business_coin_info['id'];
            }
        }

        if ($business_coin_info) {
            foreach ($business_coin_info as $coin_info) {
                $business_id_arry[] = $coin_info['business_id'];
                $business_coin_data['wallet_type'] = $coin_info['wallet_type'];
                $business_coin_data['business_coin_id'] = $coin_info['id'];
                $business_coin_data_arry[$coin_info['business_id']][] = $business_coin_data;
            }
        } else {
            return;
        }
        $xun_user_service = new XunUserService($db);
        $receiver_crypto_user_address_data = $xun_user_service->getActiveAddressByUserIDandType($user_id, "personal");

        if (!$receiver_crypto_user_address_data) {
            return;
        }

        $receiver_user_id = $receiver_crypto_user_address_data['user_id'];
        $receiver_address = $receiver_crypto_user_address_data['address'];
        $receiver_address_id = $receiver_crypto_user_address_data['id'];

        foreach ($business_id_arry as $business_id) {

            foreach ($business_coin_data_arry[$business_id] as $coin_array_info) {

                $wallet_type = $coin_array_info['wallet_type'];
                $business_coin_id = $coin_array_info['business_coin_id'];

                if (!empty($user_id)) {
                    $db->where("a.business_coin_id", $business_coin_id);
                    $db->where("b.recipient_user_id", $user_id);
                    $db->join("xun_wallet_transaction b", "a.wallet_tx_id=b.id");
                    $reward_payout_tx = $db->get("xun_business_reward_payout_transaction a", null, "a.id, b.transaction_hash, b.recipient_user_id, b.status");
                }

                if ($reward_payout_tx) {
                    // return;
                    // return "FAILED REWARD PAYOUT TX";
                    continue;
                }

                $db->where("user_id", $business_id);
                $db->where("type", "reward");
                $business_reward_setting = $db->getOne("xun_business_reward_setting", "reward_amount, auto_send");

                // print_r($coin_array_info);
                $reward_amount = $business_reward_setting['reward_amount'];
                $auto_send = $business_reward_setting['auto_send'];

                if ($reward_amount == 0) {
                    return;
                    // return "FAILED REWARD AMOUNT";
                }

                if ($auto_send == "0") {
                    return;
                    // return "FAILED AUTO SEND";
                }

                $amount_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $reward_amount);

                $business_cp_address_data = $xun_user_service->getActiveAddressByUserIDandType($business_id, "reward");
                if (!$business_cp_address_data) {
                    // return;
                    return "FAILED BUSINESS DATA";
                }

                $sender_address = $business_cp_address_data["address"];
                $sender_address_id = $business_cp_address_data["id"];

                $wallet_tx_insert = array(
                    "user_id" => $business_id,
                    "sender_address" => $sender_address,
                    "recipient_address" => $receiver_address,
                    "sender_user_id" => $business_id,
                    "recipient_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "wallet_type" => $wallet_type,
                    "status" => "pending",
                    "transaction_type" => "send",
                    "address_type" => "reward",
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $wallet_tx_id = $db->insert("xun_wallet_transaction", $wallet_tx_insert);
                if (!$wallet_tx_id) {
                    throw new Exception($db->getLastError());
                }

                $sending_queue_insert = array(
                    "sender_crypto_user_address_id" => $sender_address_id,
                    "receiver_crypto_user_address_id" => $receiver_address_id,
                    "receiver_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "amount_satoshi" => $amount_satoshi,
                    "wallet_type" => $wallet_type,
                    "status" => "pending",
                    "wallet_transaction_id" => $wallet_tx_id,
                    "processed" => "0",
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $sending_queue_id = $db->insert("wallet_server_sending_queue", $sending_queue_insert);

                if (!$sending_queue_id) {
                    throw new Exception($db->getLastError());
                }

                $business_reward_payout_insert = array(
                    "user_id" => $receiver_user_id,
                    "business_coin_id" => $business_coin_id,
                    "wallet_tx_id" => $wallet_tx_id,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $business_reward_payout_id = $db->insert("xun_business_reward_payout_transaction", $business_reward_payout_insert);

                if (!$business_reward_payout_id) {
                    throw new Exception($db->getLastError());
                }

            }
        }
        return;

    }

    public function get_customer_listing($params)
    {
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id = trim($params['business_id']);
        $mobile = trim($params['mobile']);
        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]/*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        $date = date("Y-m-d H:i:s");

        $partnerDB->where("business_id", $business_id);
        $partnerDB->where("is_registered", 0);

        if ($mobile) {
            $partnerDB->where("mobile", "%$mobile%", "LIKE");
        }
        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $partnerDB->where("created_at", $from_datetime, ">=");
        }
        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $partnerDB->where("created_at", $to_datetime, "<=");
        }

        $copyDb = $partnerDB->copy();
        $partnerDB->orderBy("created_at", $order);

        $result = $partnerDB->get("business_user", $limit);

        if (!$result) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        $totalRecord = $copyDb->getValue("business_user", "count(*)");

        $result_arr = [];
        foreach ($result as $data) {
            $data_arr = array(
                "mobile" => $data["mobile"],
                "created_at" => $data["created_at"],
            );
            $result_arr[] = $data_arr;
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $return_data["result"] = $result_arr;
        $return_data["totalRecord"] = $totalRecord;
        $return_data["numRecord"] = $num_record;
        $return_data["totalPage"] = $total_page;
        $return_data["pageNumber"] = $page_number;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "data" => $return_data);

    }

    public function send_cash_token($params)
    {
        global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency, $country, $config;
        $db = $this->db;
        $general = $this->general;
        $partnerDB = $this->partnerDB;

        $cash_token_info = $params['cash_token_info'];
        $transaction_method = $params['transaction_method'];
        $send_all_followers = $params['send_all_followers'];
        $business_id = $params['business_id'];
        $date = date("Y-m-d H:i:s");
        
        if($send_all_followers){
            $reward_amount = $cash_token_info[0]['reward_amount'];
            $description = $cash_token_info[0]['description'];
        }

        // if ($send_all_followers == 0 && !$mobile_arr) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty*/);
        // }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if($reward_amount == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00317') /*Reward amount cannot be empty*/);
        // }

        // if($description == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00318') /*Description cannot be empty*/);
        // }

        // if($transaction_method == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00319') /*Transaction Method cannot be empty*/);
        // }

        // if($country_id == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00486') /* Please select a country. */, 'developer_msg' => 'Country ID cannot be empty.');
        // }

        // if(bccomp((string)$reward_amount, "0", 18) <= 0){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00320') /*Invalid amount*/);
        // }

        $xunBusinessService = new XunBusinessService($db);

        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('a.user_id', $business_id);
        $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
        $xun_business_account = $db->getOne('xun_business_account a', 'b.id');

        $owner_user_id = $xun_business_account['id'];

        $db->where("user_id", $business_id);
        $db->where("type", "cash_token");
        $reward_setting = $db->getOne("xun_business_reward_setting");
        $business_sending_limit = $reward_setting["reward_sending_limit"];

        $business_coin_result = $this->getBusinessCoinDetails($business_id, 'cash_token');
        $business_coin_id = $business_coin_result['id'];
        $business_wallet_type = $business_coin_result['wallet_type'];

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
        }

        $currency_info = $xunCurrency->get_currency_info($business_wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];
        $coin_symbol = strtoupper($currency_info["symbol"]);
        if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $reward_amount, (string) $business_sending_limit, 18) > 0) {
            $translation_message = $this->get_translation_message('E00329'); /*You're only allowed to send a maximum of %%business_sending_limit%% %%coin_symbol%%*/
            $error_message = str_replace("%%business_sending_limit%%", $business_sending_limit, $translation_message);
            $error_message = str_replace("%%coin_symbol%%", $coin_symbol, $error_message);

            return array("code" => 0,
                "message" => "FAILED",
                "message_d" => $error_message);
        }
        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($reward_amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);
            $error_message = $this->get_translation_message('E00325') /*A maximum of %%no_of_decimals decimals%% is allowed for reward amount.*/;
            $error_message = str_replace("%%no_of_decimals%%", $no_of_decimals, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_cp_address_id = $crypto_user_address["id"];

        //$wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);
        $wallet_balance = '100000';
        $user_id_arr = [];
        $failed_request_list = [];
        $successful_request_list = [];
        if ($send_all_followers == 1) {
            $db->where('user_id', array($business_id, $owner_user_id), 'NOT IN');
            $db->where('business_coin_id', $business_coin_id);
            $user_coin_list = $db->get('xun_user_coin');
            foreach ($user_coin_list as $key => $value) {
                $follower_user_id = $value['user_id'];

                $user_id_arr[] = $follower_user_id;
            }

        }
        
        if ($send_all_followers != 1 && $cash_token_info) {
            unset($unregistered_user);
            foreach ($cash_token_info as $key => $value) {
                unset($mobile_arr);
                unset($mobile_list);
                $reward_amount = $value['reward_amount'];
                $country_code = $value['country_code'];
                $description = $value['description'];
                $mobile_arr = $value['mobile'];

                $countryParams = array(
                    "country_code_arr" => array($country_code),
                );
                $countryData = $country->getCountryByCountryCode($countryParams);

                $selectedCountryName = $countryData[$country_code]['name'];
                $selectedCountryCode = $country_code;

                foreach ($mobile_arr as $mobile) {
                    // $mobileFirstChar = $phone_number[0];
                    $phone_number = $country_code ."".$mobile;
                    $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
                    $mobileCountryCode = $mobileNumberInfo['countryCode'];
                    $phone_number = str_replace("-", "", $mobileNumberInfo["phone"]);
                    $isValid = $mobileNumberInfo['isValid'];

                    if($isValid != 1){
                        $failed_request_arr = array(
                            "mobile" => $phone_number,
                            "error_message" => "Phone Number is not valid",
                        );
                        $error_message = "%$phone_number% - Phone Number is not valid";
                        $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');

                        $failed_request_list[] = $failed_request_arr;
                        continue;
                    }
                    
                    if ($selectedCountryCode == $mobileCountryCode) {
                        $mobile_list[] = $phone_number;
                    } else {
                        $failed_request_arr = array(
                            "mobile" => $phone_number,
                            "error_message" => "Phone Number is not from $selectedCountryName",
                        );
                        $error_message = "%$phone_number% - Phone Number is not from %$selectedCountryName%";
                        $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');

                        $failed_request_list[] = $failed_request_arr;

                    }

                }

                if ($mobile_list) {

                    $db->where('username', $mobile_list, 'IN');
                    // $db->where('email', '');
                    $db->where('register_site', '');
                    $db->where('type', 'user');
                    $mobile_arr_data = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

                    if ($mobile_arr_data) {
                        foreach ($mobile_list as $mobile_value) {
                            if ($mobile_arr_data[$mobile_value]) {
                                $user_id_arr[] = $mobile_arr_data[$mobile_value]['id'];
                                
                                $cash_token_array = array(
                                    "user_id" => $mobile_arr_data[$mobile_value]['id'],
                                    "description" => $description,
                                    "reward_amount" => $reward_amount,
                                    "country_code" => $country_code,
                                );

                                $mapped_cash_token_info[$mobile_arr_data[$mobile_value]['id']] = $cash_token_array;

                            } else {
                                $unregistered_user[] = $mobile_value;
                            }

                        }
                    } else {
                        if($unregistered_user){
                            $unregistered_user = array_merge($unregistered_user, $mobile_list);
                        }
                        else{
                            $unregistered_user = $mobile_list;
                        }
                        
                    }
                }

            }

            foreach ($unregistered_user as $value) {
                $failed_request_arr = array(
                    "mobile" => $value,
                    "error_message" => "Phone Number not registered in thenux",
                );
                $error_message = "%$value% - Phone Number not registered in thenux";
                $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');

                $failed_request_list[] = $failed_request_arr;
                //  add unregistered users to business partner table

                $insert_business_user_data = array(
                    "business_id" => $business_id,
                    "mobile" => $value,
                    "is_registered" => 0,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $update_columns = array(
                    "is_registered",
                    "updated_at",
                );

                $partnerDB->onDuplicate($update_columns);

                $ids = $partnerDB->insert("business_user", $insert_business_user_data);
            }
        }


            if ($user_id_arr) {

                $db->where('id', $user_id_arr, 'IN');
                $db->where('type', 'user');
                $db->where('register_site', '');
                //  $db->where('email', '');
                $copyDb = $db->copy();
                $xun_user_list = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

                $registered_user_list = array_keys($xun_user_list);
                $server = $config['server'];
                $db->where('username', $registered_user_list, 'IN');
                $db->where('business_id', $business_id);
                $db->where('server_host', $server);
                $business_follow_list = $db->map('username')->ArrayBuilder()->get('xun_business_follow');
                $business_followed_mobile = array_keys($business_follow_list);
            
                //Phone number that is not yet in xun_business_follow
                $not_business_follow_mobile = array_diff($registered_user_list, $business_followed_mobile);
                foreach($not_business_follow_mobile as $no_follow_mobile){
                    $insert_business_follow = array(
                        "username" => $no_follow_mobile,
                        "business_id" => $business_id,
                        "server_host" => $server,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s")
                    );

                    $business_follow_id = $db->insert('xun_business_follow', $insert_business_follow);
                
                    if($business_follow_id){
                        $user_follow_array = array(
                            "mobile" => $no_follow_mobile,
                            "business_follow_id" => $business_follow_id
                        );
    
                        $user_follow_list[] = $user_follow_array;
     
                    }
                }          
                $failed_auto_message_list = [];
             
                if($user_follow_list){
                    $erlang_params = array(
                        "business_id" => $business_id,
                        "user_follow_list" => $user_follow_list
                    );
                    $erlangReturn = $post->curl_post("user/business/follow", $erlang_params);

                    $error_follow_list = $erlangReturn['data'];

                    foreach($error_follow_list as $error_key => $error_value){
                        if($error_value['error']){
                            $failed_auto_message_list[] = $error_value['error'];

                        }   
                    }
    
     
                }

                $mapped_user_id_list = $copyDb->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
                $db->where('user_id', $user_id_arr, 'IN');
                $db->where('active', 1);
                $db->where('address_type', 'personal');
                $recipient_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

                $user_id_with_wallet_arr = [];
                foreach ($recipient_user_address as $address_key => $address_value) {
                    $user_id_with_wallet_arr[] = $address_value['user_id'];
                }  

                $user_without_wallet = array_diff($user_id_arr, $user_id_with_wallet_arr);

                foreach ($user_without_wallet as $value) {
                    $failed_mobile = $mapped_user_id_list[$value]['username'];
                    $error_message = 'User did not have a wallet';
                    $failed_request_list[] = array(
                        "mobile" => $failed_mobile,
                        "error" => $error_message,
                    );
                    $wallet_tx_error_message = "%$failed_mobile% - $error_message";

                    $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $wallet_tx_error_message, 'cash_token');
                }

                foreach($user_id_with_wallet_arr as $user_id){
                    if($cash_token_info && $send_all_followers != 1){
                        $description = $mapped_cash_token_info[$user_id]['description'];
                        $reward_amount = $mapped_cash_token_info[$user_id]['reward_amount'];
                        $country_id = $mapped_cash_token_info[$user_id]['country_id'];
                    }

                    unset($confirmed_arr);
                    $confirmed_arr = array(
                        "user_id" => $user_id,
                        "description" => $description,
                        "reward_amount" => $reward_amount,
                        "country_id" => $country_id,
                        "transaction_method" => $transaction_method
                    );

                    $total_amount = $total_amount + $reward_amount;
                    $confirmed_list[] = $confirmed_arr;
                }

            }

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
            $decimal_place = $decimal_place_setting['decimal_places'];

            if ($total_amount > $wallet_balance) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00338') /*Insufficient Balance"*/);
            }

            $insert_cash_token = array(
                "business_id" => $business_id,
                "transaction_method" => $transaction_method,
                "created_at" => $date,
            );

            $cash_token_id = $db->insert('xun_cash_token_transaction', $insert_cash_token);

            if (!$cash_token_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00485') /*Insert Cash Token Failed.*/, 'developer_message' => $db->getLastError());
            }

            unset($insert_wallet_tx_list);
            unset($reward_details_list);

            foreach ($confirmed_list as $confirmed_key => $confirmed_value) {
                $receiver_user_id = $confirmed_value['user_id'];
                $recipient_address = $recipient_user_address[$receiver_user_id]['address'];
                $reward_amount = $confirmed_value['reward_amount'];
                $description = $confirmed_value['description'];
                $country_id = $confirmed_value['country_id'];

                $insert_wallet_tx = array(
                    "user_id" => $business_id,
                    "sender_address" => $business_cp_address,
                    "recipient_address" => $recipient_address,
                    "sender_user_id" => $business_id,
                    "recipient_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "wallet_type" => $business_wallet_type,
                    "fee" => '',
                    "fee_unit" => '',
                    "transaction_hash" => '',
                    "transaction_token" => '',
                    "status" => "pending",
                    "transaction_type" => 'send',
                    "escrow" => '0',
                    "escrow" => '',
                    "reference_id" => '',
                    "batch_id" => '',
                    "message" => '',
                    "expires_at" => '',
                    "address_type" => 'cash_token',
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);

                if (!$wallet_tx_id) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                }

                unset($insert_cash_token_details);
                $insert_cash_token_details = array(
                    "cash_token_transaction_id" => $cash_token_id,
                    "receiver_user_id" => $receiver_user_id,
                    "business_id" => $business_id,
                    "wallet_type" => $business_wallet_type,
                    "amount" => $reward_amount,
                    "status" => "pending",
                    "wallet_transaction_id" => $wallet_tx_id,
                    'description' => $description,
                    "country_id" => $country_id ? $country_id : 0,
                    "created_at" => $date,
                    "updated_at" => $date,

                );

                $cash_token_details_id = $db->insert('xun_cash_token_transaction_details', $insert_cash_token_details);

                if (!$cash_token_details_id) {

                    $update_wallet_tx = array(
                        "status" => 'failed',
                        "updated_at" => $date,
                    );

                    $db->where('id', $wallet_tx_id);
                    $db->update('xun_wallet_transaction', $update_wallet_tx);

                }

                $success_mobile = $mapped_user_id_list[$receiver_user_id]['username'];
                $successful_request_list[] = $success_mobile;

                $crypto_user_address_id = $recipient_user_address[$receiver_user_id]['id'];
                $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);
            
                $insert_wallet_sending_queue = array(
                    "sender_crypto_user_address_id" => $business_cp_address_id,
                    "receiver_crypto_user_address_id" => $crypto_user_address_id,
                    "receiver_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "amount_satoshi" => $amount_satoshi,
                    "wallet_type" => $business_wallet_type,
                    "status" => 'pending',
                    "wallet_transaction_id" => $wallet_tx_id,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

            }
        

        if ($successful_request_list) {
            $db->where('username', $successful_request_list, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            $success_send_user = $db->get('xun_user', null, 'id');

            $db->where('user_id', $success_send_user, 'IN');
            $db->where('business_coin_id', $business_coin_id);
            $existing_follower = $db->map('user_id')->ArrayBuilder()->get('xun_user_coin');

            foreach ($success_send_user as $success_user_key => $success_user_value) {
                $follower_user_id = $success_user_value['id'];
                if (!$existing_follower[$follower_user_id]) {
                    $insert_user_coin = array(
                        "user_id" => $follower_user_id,
                        "business_coin_id" => $business_coin_id,
                        "created_at" => $date,
                    );
                    $inserted = $db->insert('xun_user_coin', $insert_user_coin);
                }
            }
        }

        $returnData['success_request_list'] = $successful_request_list;
        $returnData['failed_request_list'] = $failed_request_list;
        $returnData['failed_auto_message_list'] = $failed_auto_message_list;

        if (!$successful_request_list && $failed_request_list) {
            return array('code' => -102, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00484') /*Send Cash Token Failed.*/, 'data' => $returnData);
        } else {
            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00257') /*Send Cash Token Successful.*/, 'data' => $returnData);
        }

    }

    public function getBusinessCoinDetails($business_id, $type, $col = '')
    {
        $db = $this->db;

        $db->where('business_id', $business_id);
        $db->where('type', $type);
        $business_coin = $db->getOne('xun_business_coin', $col);

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        return $business_coin;
    }

    public function import_cash_token($params)
    {
        global $country;
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $general = $this->general;

        $business_id = $params['business_id'];
        $attachment_name = $params["attachment_name"];
        $attachment_data = $params["attachment_data"];
        $attachment_type = $params["attachment_type"];

        if ($business_id == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        if ($attachment_name == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00473') /*Attachment name cannot be empty.*/);
        }

        if ($attachment_type == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment type cannot be empty.*/);
        }

        if ($attachment_data == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment data cannot be empty.*/);
        }

        include_once 'PHPExcel.php';

        $tmp_file_name = "cash_token_" . $business_id . "_" . time();
        $file_data = explode(",", $attachment_data);
        $file = base64_decode($file_data[1]);
        $tmp_handle = tempnam(sys_get_temp_dir(), $tmp_file_name);
        $handle = fopen($tmp_handle, 'r+');

        fwrite($handle, $file);
        rewind($handle);

        $file_type = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($file_type);

        $excel_obj = $objReader->load($tmp_handle);
        $worksheet = $excel_obj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if ($lastRow <= 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00470') /*File content empty.*/);
        }

        // get creator type
        $db->where("id", $business_id);
        $accountType = $db->getValue("xun_user", "type");

        // insert import_data
        $dataInsert = array(
            'type' => "sendCoinToken",
            'file_name' => $attachment_name,
            'creator_id' => $business_id,
            'creator_type' => $accountType,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $import_id = $db->insert('xun_import_data', $dataInsert);
        if (empty($import_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "insert import fail. Error:" . $db->getLastError());
        }

        // insert upload table
        $dataInsert2 = array(
            'file_type' => $attachment_type,
            'file_name' => $attachment_name,
            'data' => $file,
            'type' => "sendCoinToken",
            'reference_id' => $import_id,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $upload_id = $db->insert('uploads', $dataInsert2);
        if (empty($upload_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert upload fail. Error:" . $db->getLastError());
        }

        //update upload file id
        $db->where("id", $import_id);
        $db->update("xun_import_data", array("upload_id" => $upload_id));

        // Loop file content
        $recordCount = 0;
        $processedCount = 0;
        $failedCount = 0;

        for ($row = 2; $row <= $lastRow; $row++) {
            $recordCount++;

            $country = $worksheet->getCell('A' . $row)->getValue();
            $mobile = $worksheet->getCell('B' . $row)->getValue();
            $amount = $worksheet->getCell('C' . $row)->getValue();
            $remark = $worksheet->getCell('A' . $row)->getValue();

            $errorMessage = "";

            // if (empty($mobile)) {
            //     continue;
            // }

            // unset($sales_info);
            // $sales_info[] = array(
            //     "mobile" => $mobile,
            //     "amount" => $amount,
            // );

            unset($params2);
            $params2["business_id"] = $business_id;
            $params2["mobile"] = $mobile;
            $params2["reward_amount"] = $amount;
            $params2['description'] = $description;
            $params2['country_name'] = $country;
            $params2["transaction_method"] = 'company_pool';

            unset($temp_res);
            $temp_res = $this->send_cash_token_import($params2);

            $status = $temp_res["message"];

            if ($temp_res["code"] == 1) {
                $reason = "";
                $processedCount++;
            } else {
                $reason = $temp_res["message_d"];
                $debug_msg = $temp_res["developer_msg"];
                $failedCount++;
            }

            unset($json);
            $json = array(
                'mobile' => $mobile,
                'amount' => $amount,
                'status' => $status,
                'reason' => $reason ?: "",
                'developer_msg' => $debug_msg ?: "",
            );
            $json = json_encode($json);

            unset($dataInsert);
            $dataInsert = array(
                'import_data_id' => $import_id,
                'data' => $json,
                'processed' => "1",
                'status' => $status,
                'error_message' => $reason ?: "",
            );
            $import_details_id = $db->insert('xun_import_data_details', $dataInsert);

            if (empty($import_details_id)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert import details fail.");
            }

        }

        $dataUpdate = array(
            'total_records' => $recordCount,
            'total_processed' => $processedCount,
            'total_failed' => $failedCount,
        );
        $db->where('id', $import_id);
        $db->update('xun_import_data', $dataUpdate);

        $handle = fclose($handle);

        // remove open file in temp dir
        unlink($tmp_handle);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00258') /*Import Cash Token Success.*/);

    }

    public function send_cash_token_import($params)
    {

        global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency, $country;
        $db = $this->db;
        $general = $this->general;
        $partnerDB = $this->partnerDB;

        $business_id = $params['business_id'];
        $mobile = $params['mobile'];
        $reward_amount = $params['reward_amount'];
        $description = $params['description'];
        $country_name = $params['country_name'];
        $transaction_method = 'company_pool';
        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty*/);
        }

        if ($reward_amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00317') /*Reward amount cannot be empty*/);
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('a.user_id', $business_id);
        $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
        $xun_business_account = $db->getOne('xun_business_account a', 'b.id');

        $owner_user_id = $xun_business_account['id'];

        $business_coin_result = $this->getBusinessCoinDetails($business_id, 'cash_token');
        $business_coin_id = $business_coin_result['id'];
        $business_wallet_type = $business_coin_result['wallet_type'];

        $db->where("user_id", $business_id);
        $db->where('wallet_type', $business_wallet_type);
        $reward_setting = $db->getOne("xun_business_reward_setting");

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
        }

        $currency_info = $xunCurrency->get_currency_info($business_wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];
        $coin_symbol = strtoupper($currency_info["symbol"]);
        // if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $reward_amount, (string) $business_sending_limit, 18) > 0) {
        //     $translation_message = $this->get_translation_message('E00329'); /*You're only allowed to send a maximum of %%business_sending_limit%% %%coin_symbol%%*/
        //     $error_message = str_replace("%%business_sending_limit%%", $business_sending_limit, $translation_message);
        //     $error_message = str_replace("%%coin_symbol%%", $coin_symbol, $error_message);

        //     return array("code" => 0,
        //         "message" => "FAILED",
        //         "message_d" => $error_message);
        // }

        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($reward_amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);
            $error_message = $this->get_translation_message('E00325') /*A maximum of %%no_of_decimals decimals%% is allowed for reward amount.*/;
            $error_message = str_replace("%%no_of_decimals%%", $no_of_decimals, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $business_cp_address = $crypto_user_address['address']; //business company pool address
        $business_cp_address_id = $crypto_user_address["id"];

        $user_id_arr = [];
        $failed_request_list = [];
        $successful_request_list = [];

        $phone_number = $mobile;

        $mobileFirstChar = $phone_number[0];
        $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
        $mobileCountryCode = $mobileNumberInfo['countryCode'];
        $is_valid = $mobileNumberInfo['isValid'];
        if ($mobileFirstChar != '+') {
            $phone_number = str_replace("-", "", $mobileNumberInfo["phone"]);

        }

        if ($is_valid == 0) {
            $failed_request_arr = array(
                "mobile" => $phone_number,
                "error_message" => "Phone Number is not valid.",
            );
            $error_message = "%$phone_number% - Phone Number is not valid.";
            $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');

            $failed_request_list[] = $failed_request_arr;

        } else {
            $mobile_list[] = $phone_number;
        }

        if ($mobile_list) {

            $db->where('username', $mobile_list, 'IN');
            // $db->where('email', '');
            $db->where('register_site', '');
            $db->where('type', 'user');
            $mobile_arr_data = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

            if ($mobile_arr_data) {
                foreach ($mobile_list as $mobile_value) {

                    if ($mobile_arr_data[$mobile_value]) {
                        $user_id_arr[] = $mobile_arr_data[$mobile_value]['id'];
                    } else {
                        $unregistered_user[] = $mobile_value;
                    }

                }
            } else {
                $unregistered_user = $mobile_list;
            }
        }

        foreach ($unregistered_user as $value) {
            $failed_request_arr = array(
                "mobile" => $value,
                "error_message" => "Phone Number not registered in thenux",
            );
            $error_message = "%$value% - Phone Number not registered in thenux";
            $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');

            $failed_request_list[] = $failed_request_arr;
            //  add unregistered users to business partner table

            $insert_business_user_data = array(
                "business_id" => $business_id,
                "mobile" => $value,
                "is_registered" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $update_columns = array(
                "is_registered",
                "updated_at",
            );

            $partnerDB->onDuplicate($update_columns);

            $ids = $partnerDB->insert("business_user", $insert_business_user_data);

        }

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
        $decimal_place = $decimal_place_setting['decimal_places'];

        $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);

        if ($user_id_arr) {
            $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);
            $total_user = count($user_id_arr);
            $total_amount = bcmul($total_user, $reward_amount, $decimal_place);

            if ($total_amount > $wallet_balance) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00338') /*Insufficient Balance"*/);
            }

            $db->where('id', $user_id_arr, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            //  $db->where('email', '');
            $copyDb = $db->copy();
            $xun_user_list = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

            $mapped_user_id_list = $copyDb->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
            $db->where('user_id', $user_id_arr, 'IN');
            $db->where('active', 1);
            $db->where('address_type', 'personal');
            $recipient_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

            $user_id_with_wallet_arr = [];
            foreach ($recipient_user_address as $address_key => $address_value) {
                $user_id_with_wallet_arr[] = $address_value['user_id'];
            }

            $user_without_wallet = array_diff($user_id_arr, $user_id_with_wallet_arr);

            foreach ($user_without_wallet as $value) {
                $failed_mobile = $mapped_user_id_list[$value]['username'];
                $error_message = 'User did not have a wallet';
                $failed_request_list[] = array(
                    "mobile" => $failed_mobile,
                    "error" => $error_message,
                );
                $wallet_tx_error_message = "%$failed_mobile% - $error_message";

                $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $wallet_tx_error_message, 'cash_token');
            }
            $insert_cash_token = array(
                "business_id" => $business_id,
                "transaction_method" => $transaction_method,
                "created_at" => $date,
            );

            $cash_token_id = $db->insert('xun_cash_token_transaction', $insert_cash_token);

            if (!$cash_token_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00485') /*Insert Cash Token Failed.*/, 'developer_message' => $db->getLastError());
            }

            $insert_wallet_tx_list = [];
            $reward_details_list = [];
            foreach ($user_id_with_wallet_arr as $value) {
                $receiver_user_id = $value;
                $recipient_address = $recipient_user_address[$receiver_user_id]['address'];

                if($reward_setting['max_amount'] < $reward_amount){
                    $xunUserService = new XunUserService($db);
                    $receiver_user_result = $xunUserService->getUserByID($receiver_user_id, 'id, username');

                    $recipient_username = $receiver_user_result['username'];
                    $failed_request_arr = array(
                        "mobile" => $recipient_username,
                        "error_message" => "Exceeded the maximum sending amount.",
                    );
                    $error_message = "%$recipient_username% - Exceeded the maximum sending amount.";
                    $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'cash_token');
                    $failed_request_list[] = $failed_request_arr;
                    return array('code' => -102, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00484') /*Send Cash Token Failed.*/, 'data' => $returnData);
                }

                $insert_wallet_tx = array(
                    "user_id" => $business_id,
                    "sender_address" => $business_cp_address,
                    "recipient_address" => $recipient_address,
                    "sender_user_id" => $business_id,
                    "recipient_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "wallet_type" => $business_wallet_type,
                    "fee" => '',
                    "fee_unit" => '',
                    "transaction_hash" => '',
                    "transaction_token" => '',
                    "status" => "pending",
                    "transaction_type" => 'send',
                    "escrow" => '0',
                    "escrow" => '',
                    "reference_id" => '',
                    "batch_id" => '',
                    "message" => '',
                    "expires_at" => '',
                    "address_type" => 'cash_token',
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);

                if (!$wallet_tx_id) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                }

                unset($insert_cash_token_details);
                $insert_cash_token_details = array(
                    "cash_token_transaction_id" => $cash_token_id,
                    "receiver_user_id" => $receiver_user_id,
                    "business_id" => $business_id,
                    "wallet_type" => $business_wallet_type,
                    "amount" => $reward_amount,
                    "status" => "pending",
                    "wallet_transaction_id" => $wallet_tx_id,
                    'description' => '',
                    "country_id" => 0,
                    "created_at" => $date,
                    "updated_at" => $date,

                );

                $cash_token_details_id = $db->insert('xun_cash_token_transaction_details', $insert_cash_token_details);

                if (!$cash_token_details_id) {

                    $update_wallet_tx = array(
                        "status" => 'failed',
                        "updated_at" => $date,
                    );

                    $db->where('id', $wallet_tx_id);
                    $db->update('xun_wallet_transaction', $update_wallet_tx);

                }

                $success_mobile = $mapped_user_id_list[$receiver_user_id]['username'];
                $successful_request_list[] = $success_mobile;

                $crypto_user_address_id = $recipient_user_address[$receiver_user_id]['id'];
                $insert_wallet_sending_queue = array(
                    "sender_crypto_user_address_id" => $business_cp_address_id,
                    "receiver_crypto_user_address_id" => $crypto_user_address_id,
                    "receiver_user_id" => $receiver_user_id,
                    "amount" => $reward_amount,
                    "amount_satoshi" => $amount_satoshi,
                    "wallet_type" => $business_wallet_type,
                    "status" => 'pending',
                    "wallet_transaction_id" => $wallet_tx_id,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

            }
        }

        if ($successful_request_list) {
            $db->where('username', $successful_request_list, 'IN');
            $db->where('type', 'user');
            $db->where('register_site', '');
            $success_send_user = $db->get('xun_user', null, 'id');

            $db->where('user_id', $success_send_user, 'IN');
            $db->where('business_coin_id', $business_coin_id);
            $existing_follower = $db->map('user_id')->ArrayBuilder()->get('xun_user_coin');

            foreach ($success_send_user as $success_user_key => $success_user_value) {
                $follower_user_id = $success_user_value['id'];
                if (!$existing_follower[$follower_user_id]) {
                    $insert_user_coin = array(
                        "user_id" => $follower_user_id,
                        "business_coin_id" => $business_coin_id,
                        "created_at" => $date,
                    );
                    $inserted = $db->insert('xun_user_coin', $insert_user_coin);
                }
            }
        }

        $returnData['success_request_list'] = $successful_request_list;
        $returnData['failed_request_list'] = $failed_request_list;

        if (!$successful_request_list && $failed_request_list) {
            return array('code' => -102, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00484') /*Send Cash Token Failed.*/, 'data' => $returnData);
        } else {
            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00257') /*Send Cash Token Successful.*/, 'data' => $returnData);
        }

    }

    public function cash_token_transaction_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = trim($params['business_id']);
        $follower_mobile = trim($params['follower_mobile']);
        $status = trim($params['status']);
        $from_datetime = $params["date_from"];
        $to_datetime = $params["date_to"];
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($status) {
            $status_check = array('success', 'failed', 'pending');
            $status = strtolower($status);
            if (!in_array($status, $status_check)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00386') /*Invalid status.*/);
            }
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $business_coin_info = $this->getBusinessCoinDetails($business_id, 'reward');
        if (!$business_coin_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['wallet_type']);

        $date = date("Y-m-d H:i:s");

        if ($follower_mobile) {
            $follower_mobile = "%$follower_mobile%";
            $db->where("username", $follower_mobile, "LIKE");
            $user = $db->get("xun_user", null, "id, username as phone, nickname");

            // get user_ids
            foreach ($user as $x) {
                $user_ids[] = $x['id'];
            }
            if (!empty($user_ids)) {
                $db->where("recipient_user_id", $user_ids, "IN");
            }
        }
        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where("created_at", $from_datetime, ">=");
        }
        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where("created_at", $to_datetime, "<=");
        }
        if ($status) {
            if ($status == 'success') {
                $status = 'completed';
            }
            $status_condition = $status;
            $db->where("status", "%$status_condition%", 'LIKE');
        }
        //else{
        //     $status_condition = array("completed", "pending", "failed");
        //     $db->where("status", $status_condition, "IN");
        // }
        $db->where("address_type", "cash_token");
        $db->where("wallet_type", $business_coin_wallet_type);
        $db->where('sender_user_id', $business_id);
        $db->orderBy("created_at", $order);
        $copyDb = $db->copy();
        $cash_token = $db->get("xun_wallet_transaction", $limit, "id, user_id, sender_user_id, recipient_user_id, amount, status, message,  reference_id, created_at");

        if (!$cash_token) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }

        $totalRecord = $copyDb->getValue("xun_wallet_transaction", "count(id)");

        // print_r($redeem_info);
        foreach ($cash_token as $token) {
            $recipient_ids[] = $token['recipient_user_id'];
        }
        if ($recipient_ids) {
            //get submerchant info
            $db->where("id", $recipient_ids, "IN");
            $user_info = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username as phone, nickname");
        }
        // if (!$user_info){
        //     return array("code" => 1, "message" => "SUCCESS", "message_d" => "No Users Found In Reward.", 'data' => []);
        // }

        unset($token);
        foreach ($cash_token as $token) {

            $recipient_user_id = $token['recipient_user_id'];
            $name = $user_info[$recipient_user_id]['nickname'];
            $mobile = $user_info[$recipient_user_id]['phone'];
            $status = $token['status'];
            if ($status == 'completed') {
                $status = "success";
            } elseif ($status == 'wallet_success') {
                $status = "pending";
            }

            $error_message = $token['message'];
            $msg_arr = explode("%", $error_message);
            if ($mobile) {
                $user_mobile = $mobile;
            } elseif ($msg_arr) {
                $user_mobile = $msg_arr[1];
            } else {
                $user_mobile = '';
            }
            $strreplace_message = str_replace("%", "", $error_message);

            $cash_token_array['name'] = $name ? $name : '';
            $cash_token_array['mobile'] = $user_mobile ? $user_mobile : '';
            $cash_token_array['amount'] = $token['amount'];
            $cash_token_array['status'] = ucfirst($status);
            $cash_token_array['error_message'] = $strreplace_message ? $strreplace_message : '';
            $cash_token_array['created_at'] = $token['created_at'];

            $cash_token_list[] = $cash_token_array;
            $total_amount = bcadd($total_amount, $token['amount'], 8);
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        $returnData["data"] = $cash_token_list;
        $returnData["total_amount"] = $total_amount;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $num_record;
        $returnData["totalPage"] = $total_page;
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00260') /*Cash Token Transaction List*/, 'data' => $returnData);

    }

    public function web_get_country_list($params)
    {
        $db = $this->db;

        $country_list = $db->get('country', null, 'id, name, iso_code2, iso_code3, country_code, currency_code, image_url');

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00259') /*Country List*/, 'data' => $country_list);
    }


    public function set_cash_reward_setting($params){

        $db= $this->db;

        $business_id = $params['business_id'];
        $max_amount = $params['max_amount'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if(!$business_result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('business_id', $business_id);
        $db->where('type', 'cash_token');
        $business_coin =  $db->getOne('xun_business_coin');

        $wallet_type = $business_coin['wallet_type'] ?  $business_coin['wallet_type'] : '';

        $db->where('user_id', $business_id);
        $db->where('wallet_type', $wallet_type);
        $db->where('type', 'cash_token');
        $business_reward_setting = $db->getOne('xun_business_reward_setting');
        
        $business_reward_setting_id = $business_reward_setting['id'];

        if($business_reward_setting){
            $update_reward_setting = array(
                "max_amount" => $max_amount,
                "wallet_type" => $wallet_type,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->where('id', $business_reward_setting_id);
            $db->update('xun_business_reward_setting', $update_reward_setting);
        }
        else{
            $insert_business_reward_setting = array(
                "user_id" => $business_id,
                "wallet_type" => $wallet_type,
                "type" => 'cash_token',
                "max_amount" => $max_amount,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );
            
            $inserted = $db->insert('xun_business_reward_setting', $insert_business_reward_setting);
        }
      
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00261') /*Cash Reward Setting Successful.*/);
        
    }

    public function get_cash_reward_setting($params){
        $db= $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if(!$business_result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('business_id', $business_id);
        $db->where('type', 'cash_token');
        $business_coin =  $db->getOne('xun_business_coin');

        $wallet_type = $business_coin['wallet_type'];

        $db->where('user_id', $business_id);
       // $db->where('wallet_type', $wallet_type);
        $db->where('type', 'cash_token');
        $business_reward_setting = $db->getOne('xun_business_reward_setting');
        
        if(!$business_reward_setting){
            $max_amount = $setting->systemSetting['cashRewardMaxAmountDefaultSetting'];
        }else{
            $max_amount = $business_reward_setting['max_amount'];
        }

        $data['wallet_type'] = $wallet_type ? $wallet_type : '';
        $data['max_amount'] = $max_amount;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00262') /*Get Cash Reward Setting Successful.*/, 'data' => $data);

    }

    public function generate_transaction_token($business_id){
        $db= $this->db;
        $general = $this->general;
 
        while(1){
            $transaction_token = $general->generateApiKey($business_id);
            $db->where('transaction_token', $transaction_token);
            $coin_supply_tx = $db->getOne('xun_custom_coin_supply_transaction'); 
           
            if(!$coin_supply_tx){
                break;
            }

        }

        return $transaction_token;
    }

    public function insert_custom_coin_transaction_token($business_id, $amount, $wallet_type, $transaction_token, $transaction_hash){
        $general = $this->general;
        $db= $this->db;

        $insert_tx_token = array(
            "business_id" => $business_id,
            "transaction_hash" => $transaction_hash,
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "transaction_token" => $transaction_token,
            "is_verified" => 0,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $custom_coin_supply_transaction_id = $db->insert('xun_custom_coin_supply_transaction', $insert_tx_token);

        $data['custom_coin_supply_transaction_id'] = $custom_coin_supply_transaction_id;
        $data['transaction_token'] = $transaction_token;
        return $data;
    }

    public function import_reward_point($params){
        global $country;
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $general = $this->general;

        $business_id = $params['business_id'];
        $attachment_name = $params["attachment_name"];
        $attachment_data = $params["attachment_data"];
        $attachment_type = $params["attachment_type"];

        if ($business_id == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        if ($attachment_name == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00473') /*Attachment name cannot be empty.*/);
        }

        if ($attachment_type == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment type cannot be empty.*/);
        }

        if ($attachment_data == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment data cannot be empty.*/);
        }

        include_once 'PHPExcel.php';

        $tmp_file_name = "reward_point" . $business_id . "_" . time();
        $file_data = explode(",", $attachment_data);
        $file = base64_decode($file_data[1]);
        $tmp_handle = tempnam(sys_get_temp_dir(), $tmp_file_name);
        $handle = fopen($tmp_handle, 'r+');

        fwrite($handle, $file);
        rewind($handle);

        $file_type = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($file_type);

        $excel_obj = $objReader->load($tmp_handle);
        $worksheet = $excel_obj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if ($lastRow <= 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00470') /*File content empty.*/);
        }

        // get creator type
        $db->where("id", $business_id);
        $accountType = $db->getValue("xun_user", "type");

        // insert import_data
        $dataInsert = array(
            'type' => "rewardPoint",
            'file_name' => $attachment_name,
            'creator_id' => $business_id,
            'creator_type' => $accountType,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $import_id = $db->insert('xun_import_data', $dataInsert);
        if (empty($import_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "insert import fail. Error:" . $db->getLastError());
        }

        // insert upload table
        $dataInsert2 = array(
            'file_type' => $attachment_type,
            'file_name' => $attachment_name,
            'data' => $file,
            'type' => "rewardPoint",
            'reference_id' => $import_id,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $upload_id = $db->insert('uploads', $dataInsert2);
        if (empty($upload_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert upload fail. Error:" . $db->getLastError());
        }

        //update upload file id
        $db->where("id", $import_id);
        $db->update("xun_import_data", array("upload_id" => $upload_id, 'status' => 'scheduled'));

        // Loop file content
        $recordCount = 0;
        $processedCount = 0;
        $failedCount = 0;

        for ($row = 2; $row <= $lastRow; $row++) {
            $recordCount++;

            $mobile = $worksheet->getCell('A' . $row)->getValue();
            $amount = $worksheet->getCell('B' . $row)->getValue();

            $reward_point_arr = array(
                "mobile" => array($mobile),
                "reward_amount" => $amount,
                "description" => '',
                "country_code" => ''
            );

            $reward_point_list[] = $reward_point_arr;
            $mobileWithPlusSign = "+".$mobile;
            $mapped_reward_point[$mobileWithPlusSign] = $reward_point_arr;
        }

            $errorMessage = "";

            // if (empty($mobile)) {
            //     continue;
            // }

            // unset($sales_info);
            // $sales_info[] = array(
            //     "mobile" => $mobile,
            //     "amount" => $amount,
            // );

            unset($params2);
            $params2["business_id"] = $business_id; 
            $params2["reward_point_info"] = $reward_point_list;
            $params2["transaction_method"] = 'company_pool';

            unset($temp_res);
            $temp_res = $this->send_reward_import($params2);
            $status = $temp_res["message"];
            $data = $temp_res["data"];
            $failed_request_list = $data['failed_request_list'];
            $failed_mobile_arr = array_column($data['failed_request_list'], 'mobile');
            $success_request_list = $data['success_request_list'];

            $merge_list = array_merge($failed_mobile_arr, $success_request_list);
            $processedCount = count($success_request_list);
            $failedCount = count($failed_mobile_arr);

            if ($temp_res["code"] != 1) {
                $debug_msg = $temp_res["developer_msg"];

            }

            foreach($merge_list as $merge_mobile){
                $mobileWithoutPlusSign = substr($merge_mobile, 1);
                $amount = $mapped_reward_point[$merge_mobile]['reward_amount'];

                if(in_array($merge_mobile, $success_request_list)){
                    $status = "SUCCESS";
                    $reason = "";
                }
                else{
                    $status = "FAILED";
                    $reason = 'Send Reward Failed.';
                  
                    
                }
                unset($json);
                $json = array(
                    'mobile' => $merge_mobile,
                    'amount' => $amount,
                    'status' => $status,
                    'reason' => $reason ?: "",
                    'developer_msg' => $data ?: "",
                );
                $json = json_encode($json);

                unset($dataInsert);
                $dataInsert = array(
                    'import_data_id' => $import_id,
                    'data' => $json,
                    'processed' => "1",
                    'status' => $status,
                    'error_message' => $reason ?: "",
                );
                $import_details_id = $db->insert('xun_import_data_details', $dataInsert);

                if (empty($import_details_id)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert import details fail.");
                }
            }
                

        
        $dataUpdate = array(
            'total_records' => $recordCount,
            'total_processed' => $processedCount,
            'total_failed' => $failedCount,
            'status' => 'completed',
        );
        $db->where('id', $import_id);
        $db->update('xun_import_data', $dataUpdate);

        $handle = fclose($handle);

        // remove open file in temp dir
        unlink($tmp_handle);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00263') /*Import Reward Point Success.*/);
        
    }

    public function send_reward_import($params){
         //global $xunPhoneApprove;
         global $xunCompanyWalletAPI, $xunCrypto, $xunCurrency ,$country;
         $db = $this->db;
         $general = $this->general;
         $setting = $this->setting;
         $partnerDB = $this->partnerDB;
 
         $language = $this->general->getCurrentLanguage();
         $translations = $this->general->getTranslations();
 
         $reward_point_info = $params['reward_point_info'];
         $transaction_method = $params['transaction_method'];
         $send_all_followers = $params['send_all_followers'];
         $business_id = $params['business_id'];
 
         if($send_all_followers){
             $reward_amount = $reward_point_info[0]['reward_amount'];
             $description = $reward_point_info[0]['description'];
         }
 
         $date = date("Y-m-d H:i:s");

         if ($business_id == '') {
             return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
         }
 
         $db->where("user_id", $business_id);
         $xun_business = $db->getOne("xun_business", "id, user_id");
 
         if (!$xun_business) {
             return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00028'][$language]/*Business does not exist.*/);
         }
 
         $db->where('a.user_id', $business_id);
         $db->join('xun_user b', 'b.username = a.main_mobile', 'LEFT');
         $xun_business_account = $db->getOne('xun_business_account a', 'b.id');
 
         $owner_user_id = $xun_business_account['id'];
 
         $db->where("user_id", $business_id);
         $db->where("type", "reward");
         $reward_setting = $db->getOne("xun_business_reward_setting");
         $business_sending_limit = $reward_setting["reward_sending_limit"];
 
         $business_coin = $this->getBusinessCoinDetails($business_id, 'reward');
 
         if (!$business_coin) {
             return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
         }
 
         $business_coin_id = $business_coin['id'];
         $business_wallet_type = $business_coin['wallet_type'];
 
         $db->where('user_id', $business_id);
         $db->where('active', 1);
         $db->where('address_type', 'reward');
         $crypto_user_address = $db->getOne('xun_crypto_user_address');
 
         if (!$crypto_user_address) {
             return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
         }
 
         $currency_info = $xunCurrency->get_currency_info($business_wallet_type);
         $unit_conversion = $currency_info["unit_conversion"];
         $coin_symbol = strtoupper($currency_info["symbol"]);
         if (bccomp((string) $business_sending_limit, "0", 18) > 0 && bccomp((string) $reward_amount, (string) $business_sending_limit, 18) > 0) {
             $translation_message = $this->get_translation_message('E00329'); /*You're only allowed to send a maximum of %%business_sending_limit%% %%coin_symbol%%*/
             $error_message = str_replace("%%business_sending_limit%%", $business_sending_limit, $translation_message);
             $error_message = str_replace("%%coin_symbol%%", $coin_symbol, $error_message);
 
             return array("code" => 0,
                 "message" => "FAILED",
                 "message_d" => $error_message);
         }
         //  add checking for amount decimal places
         $check_decimal_places_ret = $general->checkDecimalPlaces($reward_amount, $unit_conversion);
         if (!$check_decimal_places_ret) {
             $no_of_decimals = log10($unit_conversion);
             $error_message = $this->get_translation_message('E00325') /*A maximum of %%no_of_decimals decimals%% is allowed for reward amount.*/;
             $error_message = str_replace("%%no_of_decimals%%", $no_of_decimals, $error_message);
             return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
         }
 
         $business_cp_address = $crypto_user_address['address']; //business company pool address
         $business_cp_address_id = $crypto_user_address["id"];
 
         $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $business_wallet_type);

        $db->where('currency_id', $business_wallet_type);
        $marketplaceCurrencies = $db->getOne('xun_marketplace_currencies');
 
        $total_supply = $marketplaceCurrencies['total_supply'];
 
         $user_id_arr = [];
         $failed_request_list = [];
         $successful_request_list = [];
         if ($send_all_followers == 1) {
             $db->where('user_id', array($business_id, $owner_user_id), 'NOT IN');
             $db->where('business_coin_id', $business_coin_id);
             $user_coin_list = $db->get('xun_user_coin');
 
             foreach ($user_coin_list as $key => $value) {
                 $follower_user_id = $value['user_id'];
 
                 $user_id_arr[] = $follower_user_id;
             }
 
         }
 
         if ($send_all_followers != 1 && $reward_point_info) {
 
             unset($unregistered_user);
             foreach ($reward_point_info as $key => $value) {
                 unset($mobile_arr);
                 unset($mobile_list);
                 $reward_amount = $value['reward_amount'];
                 $description = $value['description'];
                 $mobile_arr = $value['mobile'];
                 
                 foreach ($mobile_arr as $mobile) {
 
                     $mobileFirstChar = $mobile[0];
                     $phone_number = $country_code ."".$mobile;
                     $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
                     $mobileCountryCode = $mobileNumberInfo['countryCode'];
                     $phone_number = str_replace("-", "", $mobileNumberInfo["phone"]);
                     $isValid = $mobileNumberInfo['isValid'];
 
                     if($isValid != 1){
                         $failed_request_arr = array(
                             "mobile" => $phone_number,
                             "error_message" => "Phone Number is not valid",
                         );
                         $error_message = "%$phone_number% - Phone Number is not valid";
                         $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message, 'reward');
 
                         $failed_request_list[] = $failed_request_arr;
                         continue;
                     }
                     
                    $mobile_list[] = $phone_number;
                    
                 }
 
                 if($mobile_list){
                     $db->where('username', $mobile_list, 'IN');
                     // $db->where('email', '');
                     $db->where('register_site', '');
                     $db->where('type', 'user');
                     $mobile_arr_data = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
 
                     if ($mobile_arr_data) {
                         foreach ($mobile_list as $mobile_value) {
 
                             if ($mobile_arr_data[$mobile_value]) {
                                 $user_id_arr[] = $mobile_arr_data[$mobile_value]['id'];
 
                                 $reward_point_array = array(
                                     "user_id" => $mobile_arr_data[$mobile_value]['id'],
                                     "description" => $description,
                                     "reward_amount" => $reward_amount,
                                     "country_code" => $country_code,
                                 );
 
                                 $mapped_reward_point_info[$mobile_arr_data[$mobile_value]['id']] = $reward_point_array;
                             } else {
                                 $unregistered_user[] = $mobile_value;
                             }
 
                         }
                     } else {
                         $unregistered_user = $mobile_list;
                     }
                 }
             }
         }
 
         foreach ($unregistered_user as $value) {
             $failed_request_arr = array(
                 "mobile" => $value,
                 "error_message" => "Phone Number not registered in thenux",
             );
             $error_message = "%$value% - Phone Number not registered in thenux";
             $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $error_message);
 
             $failed_request_list[] = $failed_request_arr;
 
             //  add unregistered users to business partner table
 
             $insert_business_user_data = array(
                 "business_id" => $business_id,
                 "mobile" => $value,
                 "is_registered" => 0,
                 "created_at" => $date,
                 "updated_at" => $date,
             );
 
             $update_columns = array(
                 "is_registered",
                 "updated_at",
             );
 
             $partnerDB->onDuplicate($update_columns);
 
             $ids = $partnerDB->insert("business_user", $insert_business_user_data);
         }
 
         $decimal_place_setting = $xunCurrency->get_currency_decimal_places($business_wallet_type, true);
         $decimal_place = $decimal_place_setting['decimal_places'];
 
         $amount_satoshi = $xunCrypto->get_satoshi_amount($business_wallet_type, $reward_amount);
 
         // $total_user = count($user_id_arr);
         // $total_amount = bcmul($total_user, $reward_amount, $decimal_place);
 
         // if ($total_amount > $wallet_balance) {
         //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00338') /*Insufficient Balance"*/);
         // }
 
         if ($user_id_arr) {
 
             $db->where('id', $user_id_arr, 'IN');
             $db->where('type', 'user');
             $db->where('register_site', '');
             //  $db->where('email', '');
             $copyDb = $db->copy();
             $xun_user_list = $db->map('username')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
 
             $mapped_user_id_list = $copyDb->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');
             $db->where('user_id', $user_id_arr, 'IN');
             $db->where('active', 1);
             $db->where('address_type', 'personal');
             $recipient_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');
 
             $user_id_with_wallet_arr = [];
             foreach ($recipient_user_address as $address_key => $address_value) {
                 $user_id_with_wallet_arr[] = $address_value['user_id'];
             }
 
             $user_without_wallet = array_diff($user_id_arr, $user_id_with_wallet_arr);
 
             foreach ($user_without_wallet as $value) {
                 $failed_mobile = $mapped_user_id_list[$value]['username'];
                 $error_message = 'User did not have a wallet';
                 $failed_request_list[] = array(
                     "mobile" => $failed_mobile,
                     "error" => $error_message,
                 );
                 $wallet_tx_error_message = "%$failed_mobile% - $error_message";
 
                 $this->insert_wallet_transaction($business_id, $business_cp_address, '', '', $reward_amount, $business_wallet_type, 'failed', $wallet_tx_error_message);
             }
 
             $insert_reward = array(
                 "business_id" => $business_id,
                 "transaction_method" => $transaction_method,
                 "reference_id" => '',
                 "created_at" => $date,
             );
             $reward_tx_id = $db->insert('xun_business_reward_transaction', $insert_reward);
 
             if (!$reward_tx_id) {
                 return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00339') /*Insert Reward Transaction Failed.*/, 'developer_message' => $db->getLastError());
             }
 
             $insert_wallet_tx_list = [];
             $reward_details_list = [];
             foreach ($user_id_with_wallet_arr as $value) {
                 $receiver_user_id = $value;
                 if($reward_point_info && $send_all_followers != 1){
                     $reward_amount = $mapped_reward_point_info[$receiver_user_id]['reward_amount'];
                     $total_amount = $total_amount + $reward_amount;
 
                 }
                 else{
                     $total_amount = $total_amount + $reward_amount;
                 }
             }
 
             if($total_amount > $wallet_balance){
                 return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
                 // $request_amount = bcsub($total_amount, $wallet_balance, 8);
                 
                 // $thenuxRewardTotalSupply = $setting->systemSetting['theNuxRewardTotalSupply'];
                 // $updated_total_supply = bcadd($total_supply, $request_amount, 8);
 
                 // if(bcadd($total_supply, $request_method, 8) >= $thenuxRewardTotalSupply ){
                 //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00492') /*Exceeded Total Supply Amount.*/, 'developer_message' => "Exceeded Total Supply Amount.");
                 // }
 
                 // $transaction_token = $this->generate_transaction_token($business_id);
                 
                 // $request_credit_params = array(
                 //     "walletType" => $business_wallet_type,
                 //     "receiverAddress" => $business_cp_address,
                 //     "amount" => $request_amount,
                 //     "transactionToken" => $transaction_token,
                 // );
 
                 // $crypto_result = $xunCrypto->request_credit_transfer_pool($request_credit_params);
                 // if($crypto_result['status'] == 'error'){
                 //     return $crypto_result;
                 // }
                 // $transaction_hash = $crypto_result['data']['transactionHash'];
                 // $custom_coin_tx_arr  = $this->insert_custom_coin_transaction_token($business_id, $request_amount,  $business_wallet_type,$transaction_token, $transaction_hash);
 
                 // $custom_coin_supply_tx_id = $custom_coin_tx_arr['custom_coin_supply_transaction_id'];
 
 
  
             }
 
             foreach ($user_id_with_wallet_arr as $value) {
                 $receiver_user_id = $value;
                 $recipient_address = $recipient_user_address[$receiver_user_id]['address'];
                 if($reward_point_info && $send_all_followers != 1){
                     $description = $mapped_reward_point_info[$receiver_user_id]['description'];
                     $reward_amount = $mapped_reward_point_info[$receiver_user_id]['reward_amount'];
                     $country_id = $mapped_reward_point_info[$receiver_user_id]['country_id'];
 
                 }
 
                 $insert_wallet_tx = array(
                     "user_id" => $business_id,
                     "sender_address" => $business_cp_address,
                     "recipient_address" => $recipient_address,
                     "sender_user_id" => $business_id,
                     "recipient_user_id" => $receiver_user_id,
                     "amount" => $reward_amount,
                     "wallet_type" => $business_wallet_type,
                     "fee" => '',
                     "fee_unit" => '',
                     "transaction_hash" => '',
                     "transaction_token" => '',
                     "status" => "pending",
                     "transaction_type" => 'send',
                     "escrow" => '0',
                     "escrow" => '',
                     "reference_id" => '',
                     "batch_id" => '',
                     "message" => '',
                     "expires_at" => '',
                     "address_type" => 'reward',
                     "created_at" => $date,
                     "updated_at" => $date,
                 );
 
                 $wallet_tx_id = $db->insert('xun_wallet_transaction', $insert_wallet_tx);
 
                 if (!$wallet_tx_id) {
                     return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                 }
 
                 unset($insert_reward_details);
                 $insert_reward_details = array(
                     "reward_transaction_id" => $reward_tx_id,
                     "receiver_user_id" => $receiver_user_id,
                     "business_id" => $business_id,
                     "wallet_type" => $business_wallet_type,
                     "amount" => $reward_amount,
                     "transaction_type" => 'reward',
                     "status" => "pending",
                     "wallet_transaction_id" => $wallet_tx_id,
                     "business_reference" => '',
                     "created_at" => $date,
                     "updated_at" => $date,
 
                 );
 
                 $reward_details_id = $db->insert('xun_business_reward_transaction_details', $insert_reward_details);
 
                 if (!$reward_details_id) {
                     // $failed_mobile = $mapped_user_id_list[$receiver_user_id];
                     // $error_message = "Something went wrong. : ".$db->getLastError();
 
                     // $failed_request_list[] = array(
                     //     "mobile" => $failed_mobile,
                     //     "error" => $error_message,
                     // );
 
                     $update_wallet_tx = array(
                         "status" => 'failed',
                         "updated_at" => $date,
                     );
 
                     $db->where('id', $wallet_tx_id);
                     $db->update('xun_wallet_transaction', $update_wallet_tx);
 
                 }
 
                 $success_mobile = $mapped_user_id_list[$receiver_user_id]['username'];
                 $successful_request_list[] = $success_mobile;
 
                 $crypto_user_address_id = $recipient_user_address[$receiver_user_id]['id'];
 
                 $insert_wallet_sending_queue = array(
                     "sender_crypto_user_address_id" => $business_cp_address_id,
                     "receiver_crypto_user_address_id" => $crypto_user_address_id,
                     "receiver_user_id" => $receiver_user_id,
                     "amount" => $reward_amount,
                     "amount_satoshi" => $amount_satoshi,
                     "wallet_type" => $business_wallet_type,
                     "status" => 'pending',
                     "wallet_transaction_id" => $wallet_tx_id,
                     "custom_coin_supply_transaction_id" => $custom_coin_supply_tx_id ? $custom_coin_supply_tx_id : 0,
                     "created_at" => $date,
                     "updated_at" => $date,
                 );
 
                 $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);
 
             }
         }
 
         if ($successful_request_list) {
             $db->where('username', $successful_request_list, 'IN');
             $db->where('type', 'user');
             $db->where('register_site', '');
             $success_send_user = $db->get('xun_user', null, 'id');
 
             $db->where('user_id', $success_send_user, 'IN');
             $db->where('business_coin_id', $business_coin_id);
             $existing_follower = $db->map('user_id')->ArrayBuilder()->get('xun_user_coin');
 
             foreach ($success_send_user as $success_user_key => $success_user_value) {
                 $follower_user_id = $success_user_value['id'];
                 if (!$existing_follower[$follower_user_id]) {
                     $insert_user_coin = array(
                         "user_id" => $follower_user_id,
                         "business_coin_id" => $business_coin_id,
                         "created_at" => $date,
                     );
                     $inserted = $db->insert('xun_user_coin', $insert_user_coin);
                 }
             }
         }
 
         $returnData['success_request_list'] = $successful_request_list;
         $returnData['failed_request_list'] = $failed_request_list;
 
         if (!$successful_request_list && $failed_request_list) {
             return array('code' => -101, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00343') /*Send Reward Failed.*/, 'data' => $returnData);
         } else {
             return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00200') /*Send Reward Successful.*/, 'data' => $returnData);
         }
    }

    public function app_coin_image_upload($params){
        $db = $this->db;

        $username = trim($params['username']);
        $business_id = trim($params['business_id']);

        if (!$username){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty.*/);
        }
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        return $this->coin_image_upload($params);
    }

    public function web_coin_image_upload($params){
        $db = $this->db;
        $business_id = trim($params['business_id']);
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        return $this->coin_image_upload($params);
    }

    private function coin_image_upload($params){
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $coin_image = trim($params["coin_image"]);
        $username = trim($params["username"]);

        if (is_null($coin_image)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00497') /*Coin Image field is required*/);
        }

        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');
        if (!$xun_business){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        $business_name = $xun_business['name'];

        $db->where("business_id", $business_id);
        $business_coin = $db->get("xun_business_coin");
        if (!$business_coin){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        foreach($business_coin as $coin){
            if ($coin['wallet_type'] != ''){
                $wallet_type_arr[] = $coin['wallet_type'];
            }
        }

        $db->where("currency_id", $wallet_type_arr, "IN");
        $db->where("name", $business_name);
        $coin_details = $db->getOne("xun_marketplace_currencies");
        $coin_image_url_check = $coin_details['image'];

        if ($coin_image){
            $title = "coin/image0";
            $xun_user_service = new XunUserService($db);
            //upload and get url from s3
            $coin_image_result = $xun_user_service->uploadPictureBase64($business_id, $coin_image, "", $title);
    
            if ($coin_image_result["object_url"] == $coin_image_url_check){
                $title = 'coin/image0';
                $coin_image_result = $xun_user_service->uploadPictureBase64($business_id, $coin_image, "", $title);
            } 
            if(!$coin_image_result['error']){

                $coin_image_url = $coin_image_result["object_url"];    
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $coin_image_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $coin_aws_data = curl_exec($ch);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($coin_aws_data, 0, $header_size);
                curl_close($ch);
                $headers_arr = explode("\r\n", $header); // The seperator used in the Response Header is CRLF (Aka. \r\n)
                $headers_arr = array_filter($headers_arr);

                foreach ($headers_arr as $header_string) {
                    if (strpos($header_string, 'ETag: "') === 0) {
                        // It starts with 'http'
                        $header_string = trim($header_string);
                        // echo "\n $header_string";
                        $len = strlen($header_string) - 8;
                        $etag = substr($header_string, 7, $len);
                        // echo "\n etag = $etag \n";
                        $update_data = [];
                        $update_data["image"] = $coin_image_url;
                        $update_data["image_md5"] = $etag;
                    }
                }
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $coin_image_result['error']);
            }
            if (empty($update_data)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00494') /*Coin Image Update Failed.*/);
            }
        }

        if (!empty($wallet_type_arr) && !empty($update_data)){
            $db->where("currency_id", $wallet_type_arr, "IN");
            $update_id = $db->update("xun_marketplace_currencies", $update_data);
    
            if (!$update_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00494') /*Coin Image Update Failed.*/);
            }
        }

        if ($coin_image_url == ''){
            $coin_image_url = $coin_details['image'];
            $etag = $coin_details['image_md5'];
        }

        $returnData['coin_image_url'] = $coin_image_url ? $coin_image_url : "";
        if ($username){
            $returnData['coin_image_md5'] = $etag ? $etag : "";
        }
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00264') /*Coin Image Updated.*/, "data" => $returnData);
    }

    public function app_wallet_background_upload($params){
        $db = $this->db;

        $username = trim($params['username']);
        $business_id = trim($params['business_id']);

        if (!$username){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty.*/);
        }
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        return $this->wallet_background_upload($params);
    }

    public function web_wallet_background_upload($params){
        $db = $this->db;
        $business_id = trim($params['business_id']);
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        return $this->wallet_background_upload($params);
    }

    private function wallet_background_upload($params){
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $wallet_background = trim($params["wallet_background"]);
        $username = trim($params["username"]);
        
        if (is_null($wallet_background)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00496') /*Wallet Background Image field is required*/);
        }

        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');
        if (!$xun_business){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        $business_name = $xun_business['name'];

        $db->where("business_id", $business_id);
        $business_coin = $db->get("xun_business_coin");
        if (!$business_coin){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        foreach($business_coin as $coin){
            if ($coin['wallet_type'] != ''){
                $wallet_type_arr[] = $coin['wallet_type'];
            }
        }

        $db->where("currency_id", $wallet_type_arr, "IN");
        $db->where("name", $business_name);
        $coin_details = $db->getOne("xun_marketplace_currencies");
        $wallet_background_url_check = $coin_details['bg_image_url'];

        if (!is_null($wallet_background)){
            $title = "wallet/bg0";
            $xun_user_service = new XunUserService($db);
            //upload and get url from s3
            $wallet_background_result = $xun_user_service->uploadPictureBase64($business_id, $wallet_background, "", $title);
    
            if ($wallet_background_result["object_url"] == $wallet_background_url_check){
                $title = 'wallet/bg1';
                $wallet_background_result = $xun_user_service->uploadPictureBase64($business_id, $wallet_background, "", $title);
            }

            if(!$wallet_background_result['error']){

                $wallet_background_url = $wallet_background_result["object_url"];    
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $wallet_background_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $coin_aws_data = curl_exec($ch);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($coin_aws_data, 0, $header_size);
                curl_close($ch);
                $headers_arr = explode("\r\n", $header); // The seperator used in the Response Header is CRLF (Aka. \r\n)
                $headers_arr = array_filter($headers_arr);

                foreach ($headers_arr as $header_string) {
                    if (strpos($header_string, 'ETag: "') === 0) {
                        // It starts with 'http'
                        $header_string = trim($header_string);
                        // echo "\n $header_string";
                        $len = strlen($header_string) - 8;
                        $etag = substr($header_string, 7, $len);
                        // echo "\n etag = $etag \n";
                        $update_data = [];
                        $update_data["bg_image_url"] = $wallet_background_url;
                        $update_data["bg_image_md5"] = $etag;
                    }
                }
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $wallet_background_result['error']);
            }
            if (empty($update_data)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00495') /*Wallet Background Update Failed.*/);
            }
        }

        if (!empty($wallet_type_arr) && !empty($update_data)){
            $db->where("currency_id", $wallet_type_arr, "IN");
            $update_id = $db->update("xun_marketplace_currencies", $update_data);
    
            if (!$update_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00495') /*Wallet Background Update Failed.*/);
            }
        }

        if ($wallet_background_url == ''){
            $wallet_background_url = $coin_details['bg_image_url'];
            $etag = $coin_details['bg_image_md5'];
        }

        $returnData['bg_image_url'] = $wallet_background_url ? $wallet_background_url : "";
        if ($username){
            $returnData['bg_image_md5'] = $etag ? $etag : "";
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00265') /*Wallet Background Image Updated.*/);
    }

    public function app_card_font_color_update($params){
        $db = $this->db;

        $username = trim($params['username']);
        $business_id = trim($params['business_id']);

        if (!$username){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty.*/);
        }
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        return $this->card_font_color_update($params);
    }

    public function web_card_font_color_update($params){
        $db = $this->db;
        $business_id = trim($params['business_id']);
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');
        if (!$xun_business){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        return $this->card_font_color_update($params);
    }

    private function card_font_color_update($params){
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $font_color = trim($params["font_color"]);

        if (!$font_color) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00498') /*Font Color need to be either black or white only.*/);
        }

        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where("business_id", $business_id);
        $business_coin = $db->get("xun_business_coin");
        if (!$business_coin){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        foreach($business_coin as $coin){
            if ($coin['wallet_type'] != ''){
                $wallet_type_arr[] = $coin['wallet_type'];
            }
        }

        $font_color = strtolower($font_color);

        if ($font_color != 'black' && $font_color != 'white'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00498') /*Font Color need to be either black or white only.*/);
        }

        $update_data['font_color'] = $font_color;
        if (empty($update_data)){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00499') /*Card Font Color Update Failed.*/);
        }

        if (!empty($wallet_type_arr) && !empty($update_data)){
            $db->where("currency_id", $wallet_type_arr, "IN");
            $update_id = $db->update("xun_marketplace_currencies", $update_data);
    
            if (!$update_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00499') /*Card Font Color Update Failed.*/);
            }
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00266') /*Card Font Color Updated.*/);
    }


    public function app_card_design_update($params){
        $db = $this->db;

        $username = trim($params['username']);
        $business_id = trim($params['business_id']);

        if (!$username){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty.*/);
        }
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        return $this->card_design_update($params);
    }

    public function web_card_design_update($params){
        $db = $this->db;
        $business_id = trim($params['business_id']);
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        return $this->card_design_update($params);
    }

    private function card_design_update($params){
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $font_color = trim($params["font_color"]);
        $wallet_background = trim($params["wallet_background"]);
        $coin_image = trim($params["coin_image"]);

        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');
        if (!$xun_business){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        $business_name = $xun_business['name'];

        $db->where("business_id", $business_id);
        $business_coin = $db->get("xun_business_coin");
        if (!$business_coin){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        foreach($business_coin as $coin){
            if ($coin['wallet_type'] != ''){
                $wallet_type_arr[] = $coin['wallet_type'];
            }
        }
        $xun_user_service = new XunUserService($db);

        $update_data = [];
        if ($font_color != ''){
            $font_color = strtolower($font_color);
    
            if ($font_color != 'black' && $font_color != 'white'){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00498') /*Font Color need to be either black or white only.*/);
            }
    
            $update_data['font_color'] = $font_color;
            if (empty($update_data)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00499') /*Card Font Color Update Failed.*/);
            }
        }

        if (!is_null($wallet_background) || !is_null($coin_image)){
            $db->where("currency_id", $wallet_type_arr, "IN");
            $db->where("name", $business_name);
            $coin_details = $db->getOne("xun_marketplace_currencies");

            $coin_image_url_check = $coin_details['image'];
            $wallet_background_url_check = $coin_details['bg_image_url'];
        }

        if (!is_null($wallet_background)){
            $wallet_bg_title = 'wallet/bg0';
            //upload and get url from s3
            $wallet_background_result = $xun_user_service->uploadPictureBase64($business_id, $wallet_background, "", $wallet_bg_title);
    
            if ($wallet_background_result["object_url"] == $wallet_background_url_check){
                $wallet_bg_title = 'wallet/bg1';
                $wallet_background_result = $xun_user_service->uploadPictureBase64($business_id, $wallet_background, "", $wallet_bg_title);
            } 
            if(!$wallet_background_result['error']){

                $wallet_background_url = $wallet_background_result["object_url"]; 
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $wallet_background_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $wallet_bg_aws_data = curl_exec($ch);
                $bg_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $bg_header = substr($wallet_bg_aws_data, 0, $bg_header_size);
                curl_close($ch);
                $bg_headers_arr = explode("\r\n", $bg_header); // The seperator used in the Response Header is CRLF (Aka. \r\n)
                $bg_headers_arr = array_filter($bg_headers_arr);

                foreach ($bg_headers_arr as $bg_header_string) {
                    if (strpos($bg_header_string, 'ETag: "') === 0) {
                        // It starts with 'http'
                        $bg_header_string = trim($bg_header_string);
                        // echo "\n $header_string";
                        $wallet_bg_len = strlen($bg_header_string) - 8;
                        $wallet_bg_etag = substr($bg_header_string, 7, $wallet_bg_len);
                        // echo "\n etag = $etag \n";
                        $update_data["bg_image_url"] = $wallet_background_url;
                        $update_data["bg_image_md5"] = $wallet_bg_etag;
                    }
                }
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $wallet_background_result['error']);
            }
            if (empty($update_data)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00495') /*Wallet Background Update Failed.*/);
            }
        }

        if (!is_null($coin_image)){
            $coin_title = "coin/image0";
            //upload and get url from s3
            $coin_image_result = $xun_user_service->uploadPictureBase64($business_id, $coin_image, "", $coin_title);
    
            if ($coin_image_result["object_url"] == $coin_image_url_check){
                $coin_title = 'coin/image1';
                $coin_image_result = $xun_user_service->uploadPictureBase64($business_id, $coin_image, "", $coin_title);
            } 

            if(!$coin_image_result['error']){

                $coin_image_url = $coin_image_result["object_url"];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $coin_image_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $coin_aws_data = curl_exec($ch);
                $coin_img_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $coin_img_header = substr($coin_aws_data, 0, $coin_img_header_size);
                curl_close($ch);
                $coin_img_headers_arr = explode("\r\n", $coin_img_header); // The seperator used in the Response Header is CRLF (Aka. \r\n)
                $coin_img_headers_arr = array_filter($coin_img_headers_arr);

                foreach ($coin_img_headers_arr as $coin_img_header_string) {
                    if (strpos($coin_img_header_string, 'ETag: "') === 0) {
                        // It starts with 'http'
                        $coin_img_header_string = trim($coin_img_header_string);
                        // echo "\n $header_string";
                        $coin_img_len = strlen($coin_img_header_string) - 8;
                        $coin_img_etag = substr($coin_img_header_string, 7, $coin_img_len);
                        // echo "\n etag = $etag \n";
                        $update_data["image"] = $coin_image_url;
                        $update_data["image_md5"] = $coin_img_etag;
                    }
                }
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $coin_image_result['error']);
            }
            if (empty($update_data)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00494') /*Coin Image Update Failed.*/);
            }
        }

        if (!empty($wallet_type_arr) && !empty($update_data)){
            $update_data['updated_at'] = date('Y-m-d H:i:s');
            
            $db->where("currency_id", $wallet_type_arr, "IN");
            $update_id = $db->update("xun_marketplace_currencies", $update_data);
    
            if (!$update_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00500') /*Card Design Update Failed.*/);
            }
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00267') /*Card Design Updated.*/);
    }

    public function web_get_card_design($params){
        $db = $this->db;
        $business_id = trim($params['business_id']);
        if (!$business_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');
        if (!$xun_business){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }
        $business_name = $xun_business['name'];

        $db->where("business_id", $business_id);
        $db->where("business_name", $business_name);
        $business_coin = $db->getOne("xun_business_coin");
        if (!$business_coin){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        $wallet_type = $business_coin['wallet_type'];
        
        $db->where("currency_id", $wallet_type);
        $coin_details = $db->getOne("xun_marketplace_currencies");

        $returnData['coin_image_url'] = $coin_details['image'];
        $returnData['wallet_bg_image_url'] = $coin_details['bg_image_url'];
        $returnData['font_color'] = $coin_details['font_color'];
        $returnData['company_name'] = $business_name;
        $returnData['coin_symbol'] = $coin_details['symbol'];

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00268') /*Get Card Design Success.*/, "data" => $returnData);
    }

    public function business_reward_details($params){
        global $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $company_acc_address = $setting->systemSetting['marketplaceCompanyAccWalletAddress'];

        $reward_point_info = $this->getBusinessCoinDetails($business_id, 'reward');
        $reward_cash_info = $this->getBusinessCoinDetails($business_id, 'cash_token');

        $reward_point_wallet_type = $reward_point_info['wallet_type'];
        if($reward_cash_info['wallet_type']){
            $reward_cash_wallet_type = $reward_cash_info['wallet_type'];

        }

        $db->where('currency_id', $reward_point_wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');

        $logo_image = $marketplace_currencies['image'];
        $background_image = $marketplace_currencies['bg_image_url'];
        $symbol = strtoupper($marketplace_currencies['symbol']);

        $db->where('business_id', $business_id);
        $copyDb = $db->copy();
        $business_user = $db->get('thenuxPartner.business_user');

        $totalCustomer = $copyDb->getValue('thenuxPartner.business_user', 'count(id)');

        $mobile_arr = [];
        foreach($business_user as $key => $value){
            $is_registered = $value['is_registered'];
            $mobile = $value['mobile'];
            if($is_registered == 1){
                if(!in_array($mobile, $mobile_arr)){
                    array_push($mobile_arr, $mobile);
                }
            }
        }

        $db->where('username', $mobile_arr, 'IN');
        $xun_user = $db->get('xun_user', null, 'id');
     
        $xun_user = array_column($xun_user, 'id');

        $db->where('user_id', $xun_user, 'IN');
        $crypto_user_address = $db->get('xun_crypto_user_address');

        $total_reward_point_balance = '0.0000';
        $total_cash_point_balance = '0.0000';
        foreach($crypto_user_address as $key => $value){
            $internal_address = $value['address'];

            $reward_point_wallet_info = $xunCrypto->get_wallet_info($internal_address, $reward_point_wallet_type);
            $reward_point_balance = $reward_point_wallet_info[$reward_point_wallet_type]['balance'];
            $reward_point_unit_conversion = $reward_point_wallet_info[$reward_point_wallet_type]['unitConversion'];
            $reward_point_converted_balance = bcdiv($reward_point_balance, $reward_point_unit_conversion, 4);
            $total_reward_point_balance = bcadd($total_reward_point_balance, $reward_point_converted_balance, 4);

            if($reward_cash_wallet_type){
                $reward_cash_wallet_info = $xunCrypto->get_wallet_info($internal_address, $reward_cash_wallet_type);
                $reward_cash_balance = $reward_cash_wallet_info[$reward_cash_wallet_type]['balance'];
                $reward_cash_unit_conversion = $reward_cash_wallet_info[$reward_cash_wallet_type]['unitConversion'];
                $reward_cash_converted_balance = bcdiv($reward_cash_balance, $reward_cash_unit_conversion, 4);
                $total_cash_point_balance = bcadd($total_cash_point_balance, $reward_cash_converted_balance, 4);
            }
          

        }

        $db->where('wallet_type', $reward_point_wallet_type);
        $reward_point_transaction = $db->get('xun_wallet_transaction');

        if($reward_cash_wallet_type){
            $db->where('wallet_type', $reward_cash_wallet_type);
            $reward_cash_transaction = $db->get('xun_wallet_transaction');
    
        }
      
        $total_reward_point_send = '0.0000';
        $total_reward_point_used = '0.0000';
        $total_reward_cash_send = '0.0000';
        $total_reward_cash_used  = '0.0000';
        foreach($reward_point_transaction as $point_key => $point_value){
            $sender_user_id = $point_value['sender_user_id'];
            $receiver_user_id = $point_value['receiver_user_id'];
            $amount = $point_value['amount'];

            if($business_id == $sender_user_id){
                $total_reward_point_send = bcadd($total_reward_point_send, $amount, 4);
            }

            if($$business_id == $receiver_user_id){
                $total_reward_point_used = bcadd($total_reward_point_used, $amount, 4);
            }
        }

        if($rewad_cash_transaction){
            foreach($reward_cash_transaction as $cash_key => $cash_value){
                $sender_user_id = $cash_value['sender_user_id'];
                $recipient_address = $cash_value['recipient_address'];
    
                if($business_id == $sender_user_id){
                    $total_reward_cash_send = bcadd($total_reward_cash_send, $amount, 4);
                }
    
                if($company_acc_address == $recipient_address){
                    $total_reward_cash_used = bcadd($total_reward_cash_used, $amount, 4);
                }
            }
    
        }
       
        $reward_point_data = array(
            "total_amount_send" => $total_reward_point_send,
            "total_amount_used" => $total_reward_point_used,
            "total_customer_holding" => $total_reward_point_balance,
        );

        $reward_cash_data = array(
            "total_amount_send" => $total_reward_cash_send,
            "total_amount_used" => $total_reward_cash_used,
            "total_customer_holding" => $total_cash_point_balance,
        );

        $data = array(
            "symbol" => $symbol,
            "logo_image_url" => $logo_image,
            "bg_image_url" =>  $background_image, 
            "total_customers" => $totalCustomer,
            "reward_point_data" => $reward_point_data,
            "reward_cash_data" => $reward_cash_data,

        );

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00284') /*Business Reward Details.*/, "data" => $data);
        
    }

    public function business_reward_dashboard_listing($params){
        global $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $reward_type = $params['reward_type'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($reward_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00511') /*Reward Type cannot be empty*/);
        }

        if($reward_type == 'reward_point'){
            $type = 'reward';
        }
        elseif($reward_type == 'reward_cash'){
            $type = 'cash_token';
        }else{
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00512') /*Invalid Reward Type*/);
        }

        $company_acc_address = $setting->systemSetting['marketplaceCompanyAccWalletAddress'];
        $reward_info = $this->getBusinessCoinDetails($business_id, $type);

        $latest_send_list = [];
        $latest_used_list = [];
        $top_customer_holding_list = [];

        $wallet_type = $reward_info['wallet_type'];

        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'id, currency_id, symbol');
        $symbol = strtoupper($marketplace_currencies['symbol']);

        $db->where('wallet_type', $wallet_type);
        $copyDb= $db->copy();
        $db->orderBy('id', 'desc');
        $latest_transaction = $db->get('xun_wallet_transaction', 5);

        $copyDb->where('recipient_address', $company_acc_address);
        $copyDb->orderBy('id', 'desc');
        $latest_used_transaction = $copyDb->get('xun_wallet_transaction', 5);

        $user_id_arr = [];
        foreach($latest_transaction as $key => $value){
            $user_id = $value['user_id'];

            if(!in_array($user_id, $user_id_arr)){
                array_push($user_id_arr, $user_id);
            }
        }

        foreach($latest_used_transaction as $key  => $value){
            $user_id = $value['user_id'];

            if(!in_array($user_id, $user_id_arr)){
                array_push($user_id_arr, $user_id);
            }
        }

        $crypto_user_address = $this->get_user_reward_address($business_id);


        foreach($crypto_user_address as $key => $value){
            $user_id = $value['user_id'];

                if(!in_array($user_id, $user_id_arr)){
                    array_push($user_id_arr, $user_id);
                }
        }

        $db->where('id', $user_id_arr, 'IN');
        $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user');

        foreach($latest_transaction as $key => $value){
            $user_id = $value['user_id'];
            $amount = $value['amount'];
            $created_at = $value['created_at'];

            $name = $xun_user[$user_id]['nickname'];
            $phone_number = $xun_user[$user_id]['username'];

            $latest_send_list[$key] = array(
                "name" => $name,
                "phone_number" => $phone_number,
                "amount" => $amount,
                "created_at" => $created_at,
                "symbol" => $symbol,
            );
        }

        foreach($latest_used_transaction as $key => $value){
            $user_id = $value['user_id'];
            $amount = $value['amount'];
            $created_at = $value['created_at'];

            $name = $xun_user[$user_id]['nickname'];
            $phone_number = $xun_user[$user_id]['username'];

            $latest_used_list[$key] = array(
                "name" => $name,
                "phone_number" => $phone_number,
                "amount" => $amount,
                "created_at" => $created_at,
                "symbol" => $symbol,
            );
        }

        foreach($crypto_user_address as $key => $value){
            $internal_address = $value['address'];
            $user_id = $value['user_id'];

            $wallet_info = $xunCrypto->get_wallet_info($internal_address, $wallet_type);

            $balance = $wallet_info[$wallet_type]['balance'];
            $unit_conversion = $wallet_info[$wallet_type]['unitConversion'];
            $converted_balance = bcdiv($balance, $unit_conversion, 4);

            if($converted_balance > 0){
                $customer_holdings_arr = array(
                    "amount" => $converted_balance,
                    "name" => $xun_user[$user_id]['nickname'],
                    "phone_number" => $xun_user[$user_id]['username'],
                );
    
                $customer_holding_list[] = $customer_holdings_arr;
            }
   
        }

        usort($customer_holding_list, function($a, $b) {
            return $a['amount'] < $b['amount'];
        });

        $top_customer_holding_list = array_splice($customer_holding_list, 0, 5);

        $returnData = array(
            "latest_send_list" => $latest_send_list,
            "latest_used_list" => $latest_used_list,
            "top_customer_holdings" => $top_customer_holding_list,
        );


        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00285') /*Business Reward Top and Latest Listing.*/, "data" => $returnData);
    }

    public function dashboard_statistic_v1($params){
        global $xunCurrency, $xunCrypto;
        $db= $this->db;

        $business_id = $params['business_id'];
        $to_date = $params["date_to"];
        $from_date = $params["date_from"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $reward_point_info = $this->getBusinessCoinDetails($business_id, 'reward');
        $reward_cash_info = $this->getBusinessCoinDetails($business_id, 'cash_token');

        $reward_point_wallet_type = $reward_point_info['wallet_type'];
        if($reward_cash_info['wallet_type']){
            $reward_cash_wallet_type = $reward_cash_info['wallet_type'];
        }

        $db->where('currency_id', $reward_point_wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');

        $coin_fiat_currency_id = $marketplace_currencies['fiat_currency_id'];
        $coin_reference_price = $marketplace_currencies['reference_price'];
        $symbol = strtoupper($marketplace_currencies['symbol']);

        $wallet_type_arr = array($reward_cash_wallet_type, $reward_point_wallet_type);
        if ($from_date) {
            $from_datetime = date("Y-m-d H:i:s", $from_date);
            $db->where("created_at", $from_datetime, ">=");
        }
        if ($to_date) {
            $to_datetime = date("Y-m-d H:i:s", $to_date);
            $db->where("created_at", $to_datetime, "<=");
        }
        $copyDb2 = $db->copy();
        $db->where('status', 'completed');
        $db->where('wallet_type', $wallet_type_arr, 'IN');
        $copyDb= $db->copy();
        $db->where('user_id', $business_id);
        $send_transaction = $db->get('xun_wallet_transaction');

        $copyDb->where('recipient_address', $company_acc_address);
        $used_transaction = $copyDb->get('xun_wallet_transaction');

        $copyDb2->where('business_id', $business_id);
        $sales_transaction = $copyDb2->get('xun_sales_transaction');

        $total_sales_amount = '0.00';
        $len = count($sales_transaction);
        $end = $len-1;
        $is_all_time = 0;//is all time or filter by days
        foreach($sales_transaction as $sales_key => $sales_value){
            $sales_amount = $sales_value['amount'];
            if(!$from_datetime && !$to_datetime){
                $is_all_time = 1;
                if($sales_key == 0){
                    $start_created_at = $sales_value['created_at'];
                    $from_date = strtotime($start_created_at);
                }
    
                if($sales_key == $end){
                    $end_created_at = $sales_value['created_at'];
                    $to_date = strtotime($end_created_at);
                }
            }
            $total_sales_amount = bcadd($total_sales_amount, $sales_amount, 2);
        }

        if($is_all_time == 1){

            $dateFrom = date("Y-m-d 00:00:00", $from_date);
            $dateTo = date("Y-m-d 00:00:00", $to_date);
            
            $d1 = strtotime($dateFrom);
            $d2 = strtotime($dateTo);

            $diff = $d2 - $d1;
    
            $days = $diff / (60 * 60 * 24); //get the difference in days

            $chart_data = [];
            $sales_list = [];

            //loop the days and push each day into the date arr
            for($i = 0; $i <= $days; $i++){
                if($i == 0){
                    $date_time = $dateFrom;
                }
                else{

                    $date_time = date('Y-m-d 00:00:00', strtotime('+1 days', strtotime($date_time)));
                }
            
                $sales_arr = array(
                    "date" => $date_time,
                    "value" => strval(0)
                );

                $sales_list[$date_time] = $sales_arr;
            }

        }
        else{
            $dateFrom = date("Y-m-d H:00:00", $from_date);
            $dateTo = date("Y-m-d H:00:00", $to_date);

            $d1 = strtotime($from_datetime);
            $d2 = strtotime($to_datetime);

            
            $diff = $d2 - $d1;
            $hours = $diff / (60 *60); //get the difference in hours

            $chart_data = [];
            $sales_list = [];

            //loop the hours and push each hour into the date arr
            for($i = 0; $i <= $hours; $i++){
                if($i == 0){
                    $date_time = $dateFrom;
                }
                else{

                    $date_time = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($date_time)));
                }
            
                $sales_arr = array(
                    "date" => $date_time,
                    "value" => strval(0)
                );

                $sales_list[$date_time] = $sales_arr;
            }
        }

        foreach($sales_transaction as $sales_key => $sales_value){
            $created_at = $sales_value['created_at'];
            $sales_amount = $sales_value['amount'];
            if($is_all_time == 1){
                $date = date("Y-m-d 00:00:00", strtotime($created_at));
            }
            else{
                $date = date("Y-m-d H:00:00",strtotime($created_at));
            }

            if($sales_list[$date]){
                $current_sales_amount = $sales_list[$date]['value'];
                $new_total_sales = $current_sales_amount + $sales_amount;
                $sales_list[$date]["value"] = strval($new_total_sales);
            
            }
        }

        $total_reward_cash_send = '0.0000';
        $total_reward_cash_used = '0.0000';
        $total_reward_point_send = '0.0000';
        $total_reward_point_used = '0.0000';

        foreach($send_transaction as $key => $value){
            $wallet_type = $value['wallet_type'];
            $amount = $value['amount'];

            if($wallet_type == $reward_point_wallet_type){
                $total_reward_point_send = bcadd($total_reward_point_send, $amount, 4);
            }

            if($wallet_type == $reward_cash_wallet_type){
                $total_reward_cash_send = bcadd($total_reward_cash_send, $amount, 4);
            }
        }

        foreach($used_transaction as $key => $value){
            $wallet_type = $value['wallet_type'];
            $amount = $value['amount'];

            if($wallet_type == $reward_point_wallet_type){
                $total_reward_point_used = bcadd($total_reward_point_used, $amount, 4);
            }

            if($wallet_type == $reward_cash_wallet_type){
                $total_reward_cash_used = bcadd($total_reward_cash_used, $amount, 4);
            }
        }

        $db->where('business_id', $business_id);
        $db->where('status', 'success');
        $cashpool_topup = $db->get('xun_cashpool_topup');

        $currency_rate = $xunCurrency->get_currency_rate(array($coin_fiat_currency_id));

        $total_topup_amount = '0.0000';
        foreach($cashpool_topup as $topup_key => $topup_value){
            $fiat_currency_id = $topup_value['fiat_currency_id'];
            $fiat_amount = $topup_value['amount'];

            $fiat_currency_rate = $currency_rate[$fiat_currency_id];

            $crypto_amount = bcmul($fiat_amount, $fiat_currency_rate, 4);
            $total_topup_amount = bcadd($total_topup_amount, $crypto_amount, 4);

        }

        $pool_balance = '0.0000';
        $balance_percentage = '0.00';
        if($reward_cash_wallet_type){
            $db->where('user_id', $business_id);
            $db->where('address_type', 'reward');
            $reward_company_pool = $db->getOne('xun_crypto_user_address');
    
            $reward_company_pool_address = $reward_company_pool['address'];
    
            $company_wallet_info = $xunCrypto->get_wallet_info($reward_company_pool_address, $reward_cash_wallet_type);
    
            $balance = $company_wallet_info[$reward_cash_wallet_type]['balance'];
            $unit_conversion = $company_wallet_info[$reward_cash_wallet_type]['unitConversion'];
            $pool_balance = bcdiv($balance, $unit_conversion, 4);
    
            $balanceOverTopupAmount = bcdiv($pool_balance, $total_topup_amount, 8);
            $balance_percentage = bcmul($balanceOverTopupAmount, 100, 2);
        }

        $piechart_data[] = array(
            "amount" => $pool_balance,
            "percentage" => $balance_percentage,
            "name" => 'Pool Balance',
        );
        $user_crypto_adddress = $this->get_user_reward_address($business_id);

        $total_customer_holding = '0.0000';
        $customerHoldingPercentage = '0.00';
        if($reward_cash_wallet_type){
            foreach($user_crypto_adddress as $key => $value){
                $internal_address = $value['address'];
    
                $user_wallet_info = $xunCrypto->get_wallet_info($internal_address, $reward_cash_wallet_type);
    
                $customer_balance = $user_wallet_info[$reward_cash_wallet_type]['balance'];
                $unit_conversion = $user_wallet_info[$reward_cash_wallet_type]['unitConversion'];
                $converted_balance = bcdiv($customer_balance, $unit_conversion, 4);    
                $total_customer_holding = bcadd($total_customer_holding, $converted_balance, 4);
    
            }

            $customerHoldingOverTopupAmount = bcdiv($total_customer_holding, $total_topup_amount, 8);
            $customerHoldingPercentage = bcmul($customerHoldingOverTopupAmount, 100, 2);
        
        }


        $piechart_data[] = array(
            "amount" => $total_customer_holding,
            "percentage" => $customerHoldingPercentage,
            "name" => 'Customer Holdings',
        );

        $chart_data = array_values($sales_list);

        $returnData = array(
            "total_sales_amount" => $total_sales_amount,
            "chart_data" => $chart_data,
            "total_reward_point_send" => $total_reward_point_send,
            "total_reward_point_used" => $total_reward_point_used,
            "total_reward_cash_send" => $total_reward_cash_send,
            "total_reward_cash_used" => $total_reward_cash_used,
            "symbol" => $symbol,
            "total_topup_amount" => $total_topup_amount,
            "piechart_data" => $piechart_data,
  
        );

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00286') /*Business Dashboard Statistics V1*/, "data" => $returnData);
        
    }

    private function get_user_reward_address($business_id){
        $db= $this->db;

        $db->where('business_id', $business_id);
        $db->where('is_registered', 1);
        $business_user = $db->get('thenuxPartner.business_user', null, 'mobile');

        $mobile_arr = array_column($business_user, 'mobile');
      
        $db->where('username', $mobile_arr, 'IN');
        $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, 'id, username, nickname');

        $user_id_arr = array_keys($xun_user);

        $db->where('user_id', $user_id_arr, 'IN');
        $db->where('address_type', 'personal');
        $crypto_user_address = $db->get('xun_crypto_user_address');

        return $crypto_user_address;
    }

    public function customer_purchase_history_listing($params){
        $db= $this->db;

        $business_id = $params['business_id'];
        $name = $params['name'];
        $phone_number = $params['phone_number'];
        $status = $params['status'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }


        $reward_point_info = $this->getBusinessCoinDetails($business_id, 'cash_token');

        for($i = 0; $i < 10; $i++){
            $purchase_history = array(
                "name" => 'John',
                "phone_number" => '+60123456789',
                "amount" => '10.0000',
                "status" => 'Success',
                "product_name" => "Touch n Go",
                "created_at" => "2020-02-18 07:36:34"
            );

            $purchase_listing[] = $purchase_history;
        }

        $returnData['purchase_listing'] = $purchase_listing;
        $returnData['total_amount']= '100.0000';
        $returnData["totalRecord"] = 10;
        $returnData["numRecord"] = 10;
        $returnData["totalPage"] = 1;
        $returnData["pageNumber"] = 1;

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00287') /*Customer Purchase History Listing.*/, "data" => $returnData);
        
    }
    
}
