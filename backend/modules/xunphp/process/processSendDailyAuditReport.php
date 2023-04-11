<?php
    $currentPath = __DIR__;
    include_once $currentPath."/../include/config.php";
    include_once $currentPath."/../include/class.database.php";
    include_once $currentPath . "/../include/class.setting.php";
    include_once $currentPath . "/../include/class.general.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.xun_crypto.php";

    $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $post = new post();
    $xunCrypto = new XunCrypto($db, $post, $general);

    $startDate = date("Y-m-d", strtotime("- 1 day"))." 00:00:00";
    $endDate = date("Y-m-d", strtotime("- 1 day"))." 23:59:59";
    $endDateDisplay = date("Y-m-d"). " 12AM";//" 00:00:00";

    $accDate = date("Ymd", strtotime($startDate));

    //get wallet transaction records
    $db->where("updated_at", $startDate, ">=");
    $db->where("updated_at", $endDate, "<=");
    $db->where('status', 'completed');
    $xun_wallet_transaction = $db->get('xun_wallet_transaction');

    if (!$xun_wallet_transaction){
        $content = $accDate . "\n" . "\nNo Records Found\n";
        $sc_content = $content;
        $cf_content = $content;
    }else{

        $total_records = count($xun_wallet_transaction);
        $i = 0;
        //categorized all transaction types into array
        foreach ($xun_wallet_transaction as $wallet_transaction){
            $transaction_category[$wallet_transaction['address_type']][] = $wallet_transaction;
            $transaction_wallet_type[] = strtolower($wallet_transaction['wallet_type']);
            $transaction_address_type[] = strtolower($wallet_transaction['address_type']);
            //service charge tally checking
            $transaction_wallet_category[$wallet_transaction['wallet_type']][$wallet_transaction['address_type']][$i] = $wallet_transaction['amount'];
            $i++;
        }
    
        //rearrange wallet type and address type
        foreach(array_unique($transaction_wallet_type) as $wallet_type){
            $tx_wallet_type[] = $wallet_type;
        }
        foreach(array_unique($transaction_address_type) as $address_type){
            $tx_address_type[] = $address_type;
        }
    
        //address needed for service charge checking
        foreach($tx_address_type as $address_type){
            if ($address_type == 'service_charge' || $address_type == 'company_pool' || $address_type == 'company_acc' || $address_type == 'upline' || $address_type == 'master_upline')
            $sc_add_check[] = $address_type;
        }
    
        //sum up all the amount in a address based on coin for service charge tally checking
        foreach($sc_add_check as $address_type){
            foreach($tx_wallet_type as $wallet_type){
                foreach($transaction_wallet_category[$wallet_type][$address_type] as $amount){
                    $service_charge_list[$wallet_type][$address_type] += $amount;
                }
            }
        }
    
        //story total amount
        foreach($transaction_category['story'] as $story){
            $story_amount[strtolower($story['wallet_type'])] += $story['amount'];
        }
        //prepaid total amount
        foreach($transaction_category['prepaid'] as $prepaid){
            $prepaid_amount[strtolower($prepaid['wallet_type'])] += $prepaid['amount'];
        }
    
        //escrow total amount
        foreach($transaction_category['escrow'] as $escrow){
            $escrow_amount[strtolower($escrow['wallet_type'])] += $escrow['amount'];
        }
    
        //internal_transfer total amount
        foreach($transaction_category['internal_transfer'] as $internal_transfer){
            $internal_transfer_amount[strtolower($internal_transfer['wallet_type'])] += $internal_transfer['amount'];
        }
        //external_transfer total amount
        foreach($transaction_category['external_transfer'] as $external_transfer){
            if ($external_transfer['transaction_type'] == 'receive')
                $external_transfer_amount_in[strtolower($external_transfer['wallet_type'])] += $external_transfer['amount'];
            else if($external_transfer['transaction_type'] == 'send')
                $external_transfer_amount_out[strtolower($external_transfer['wallet_type'])] += $external_transfer['amount'];
        }
    
        //service_charge total amount
        foreach($transaction_category['service_charge'] as $service_charge){
            $service_charge_amount[strtolower($service_charge['wallet_type'])] += $service_charge['amount'];
        }
        //company_pool total amount
        foreach($transaction_category['company_pool'] as $company_pool){
            $company_pool_amount[strtolower($company_pool['wallet_type'])] += $company_pool['amount'];
        }
        //company_acc total amount
        foreach($transaction_category['company_acc'] as $company_acc){
            $company_acc_amount[strtolower($company_acc['wallet_type'])] += $company_acc['amount'];
        }
        //master_upline total amount
        foreach($transaction_category['master_upline'] as $master_upline){
            $master_upline_amount[strtolower($master_upline['wallet_type'])] += $master_upline['amount'];
        }
        //upline total amount
        foreach($transaction_category['upline'] as $upline){
            $upline_amount[strtolower($upline['wallet_type'])] += $upline['amount'];
        }
    
        //pay total amount total amount
        foreach($transaction_category['pay'] as $pay){
            $pay_amount[strtolower($pay['wallet_type'])] += $pay['amount'];
        }
        //payment_gateway_fund_out total amount
        foreach($transaction_category['payment_gateway_fund_out'] as $payment_gateway_fund_out){
            $payment_gateway_fund_out_amount[strtolower($payment_gateway_fund_out['wallet_type'])] += $payment_gateway_fund_out['amount'];
        }
        //payCallbackRefund total amount
        foreach($transaction_category['payCallbackRefund'] as $payCallbackRefund){
            $payCallbackRefund_amount[strtolower($payCallbackRefund['wallet_type'])] += $payCallbackRefund['amount'];
        }
    
        $decimal = 8;
        $cf_content = "Credit Flow " . $accDate . "\n";
        //story
        if ($story_amount){
            $story_message .= "\nStory\n";
            $story_message .= "======================\n";
            foreach($story_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $story_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $story_message .= "======================\n";
        }
    
        //prepaid
        if ($prepaid_amount){
            $prepaid_message .= "\nPrepaid\n";
            $prepaid_message .= "======================\n";
            foreach($prepaid_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $prepaid_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $prepaid_message .= "======================\n";
        }
    
        //escrow
        if ($escrow_amount){
            $escrow_message .= "\nEscrow\n";
            $escrow_message .= "======================\n";
            foreach($escrow_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $escrow_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $escrow_message .= "======================\n";
        }
    
        //internal_transfer
        if ($internal_transfer_amount){
            $internal_transfer_message .= "\nInternal Transfer\n";
            $internal_transfer_message .= "======================\n";
            foreach($internal_transfer_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $internal_transfer_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $internal_transfer_message .= "======================\n";
        }
    
        //external_transfer
        //in
        if ($external_transfer_amount_in){
            $external_transfer_in_message .= "\nExternal Transfer In\n";
            $external_transfer_in_message .= "======================\n";
            foreach($external_transfer_amount_in as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $external_transfer_in_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $external_transfer_in_message .= "======================\n";
        }
        //out
        if ($external_transfer_amount_out){
            $external_transfer_out_message .= "\nExternal Transfer Out\n";
            $external_transfer_out_message .= "======================\n";
            foreach($external_transfer_amount_out as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $external_transfer_out_message .= " - ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $external_transfer_out_message .= "======================\n";
        }
    
        //pay
        if ($pay_amount){
            $pay_message .= "\nPay\n";
            $pay_message .= "======================\n";
            foreach($pay_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $pay_message .= " + ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $pay_message .= "======================\n";
        }
    
        //payment_gateway_fund_out
        if ($payment_gateway_fund_out_amount){
            $payment_gateway_fund_out_message .= "\nPayment Gateway Fund Out\n";
            $payment_gateway_fund_out_message .= "======================\n";
            foreach($payment_gateway_fund_out_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $payment_gateway_fund_out_message .= " - ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $payment_gateway_fund_out_message .= "======================\n";
        }
    
        //payCallbackRefund
        if ($payCallbackRefund_amount){
            $paymentCallbackReturn_message .= "\nPay's Return\n";
            $paymentCallbackReturn_message .= "======================\n";
            foreach($payCallbackRefund_amount as $key => $value){
                $value = number_format($value, $decimal, ".", "");
                $paymentCallbackReturn_message .= " - ". ucfirst($key) . " \t | " . $value . "\n";
            }
            $paymentCallbackReturn_message .= "======================\n";
        }
        
        //service_charge
        $sc_content = "Service Charge " . $accDate . "\n";
        // service_charge_list
        foreach($tx_wallet_type as $wallet_type){
            if ($service_charge_list[$wallet_type]){
                $sc_content .= "\n" . ucfirst($wallet_type) . "\n";
                $sc_content .= "==========================\n";
                
                $service_charge = 0;
                $partition_charge = 0;
                foreach($service_charge_list[$wallet_type] as $key => $value){
                    $key_tab = "\t";
                    if ($key == "service_charge"){
                        $sc_content .= " + ";
                        $service_charge = $value;
                    }else if ($key == "company_pool" || $key == "upline"){
                        $sc_content .= " - ";
                        $partition_charge += $value;
                    }else{
                        $sc_content .= " : ";
                        $company_pool_charge += $value;
                    }
                    if ($key == "upline"){
                        $key_tab = "\t\t\t";
                    }
                    $value = number_format($value, $decimal, ".", "");
                    $sc_content .= $key . " $key_tab | " . $value . "\n";
                }
                $sc_content .= "==========================\n";
                $diff_value = bcsub($service_charge, $partition_charge, 8);
                if ($diff_value != 0){
                    $sc_content .= "Not Tally : " . $diff_value . " (Diff) \n";
                }else{
                    $sc_content .= "Tally\n";
                }
            }
        }

        foreach($tx_wallet_type as $wallet_type){
            $total_cf[$wallet_type] += $story_amount[$wallet_type];
            $total_cf[$wallet_type] += $prepaid_amount[$wallet_type];
            $total_cf[$wallet_type] += $escrow_amount[$wallet_type];
            $total_cf[$wallet_type] += $internal_transfer_amount[$wallet_type];
            $total_cf[$wallet_type] += $external_transfer_amount_in[$wallet_type];
            $total_cf[$wallet_type] += $pay_amount[$wallet_type];
            $total_cf[$wallet_type] += $service_charge_amount[$wallet_type];
            
            $total_cf[$wallet_type] -= $external_transfer_amount_out[$wallet_type];
            $total_cf[$wallet_type] -= $payment_gateway_fund_out_amount[$wallet_type];
            $total_cf[$wallet_type] -= $payCallbackRefund_amount[$wallet_type];
            $total_cf[$wallet_type] -= $upline_amount[$wallet_type];
            $total_cf[$wallet_type] -= $master_upline_amount[$wallet_type];
            $total_cf[$wallet_type] -= $company_acc_amount[$wallet_type];
        }

        $total_cf_message = "\nTotal Credit Flow\n";
        $total_cf_message .= "======================\n";
        foreach($total_cf as $key => $value){
            if ($value < 0){
                $total_cf_message .= " - ";
            }else{
                $total_cf_message .= " + ";
            }
            $value = number_format($value, $decimal, ".", "");
            $total_cf_message .= ucfirst($key) . " \t | " . $value . "\n";
        }
        $total_cf_message .= "======================\n";
    
        // print_r($total_cf);
        $cf_content .= $story_message;
        $cf_content .= $prepaid_message;
        $cf_content .= $escrow_message;
        $cf_content .= $internal_transfer_message;
        $cf_content .= $external_transfer_in_message;
        $cf_content .= $external_transfer_out_message;
        $cf_content .= $pay_message;
        $cf_content .= $payment_gateway_fund_out_message;
        $cf_content .= $paymentCallbackReturn_message;    
        $cf_content .= $total_cf_message;    
    }

    $params["tag"] = "Daily Audit Report (CF)";
    $params["message"] = $cf_content;
    $params["api_key"] = $config["thenux_wallet_transaction_API"];
    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
    $params["mobile_list"] = $xun_numbers;
    // $params["mobile_list"] = array("+60162637873");
    $url_string = $config["broadcast_url_string"];
    $result = $post->curl_post($url_string, $params, 0);

    $params["tag"] = "Daily Audit Report (SC)";
    $params["message"] = $sc_content;
    $params["api_key"] = $config["thenux_wallet_transaction_API"];
    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
    // $params["mobile_list"] = array("+60162637873");
    $params["mobile_list"] = $xun_numbers;
    $url_string = $config["broadcast_url_string"];
    $result = $post->curl_post($url_string, $params, 0);

    $company_acc_wallet_address = $setting->systemSetting["marketplaceCompanyAccWalletAddress"];

    $wallet_info = $xunCrypto->get_wallet_info($company_acc_wallet_address);

    $msg = date("Y-m-d H:i:s");
    $msg .= "\nTheNux Service Charge Profit\n\n";

    foreach($wallet_info as $wallet_type => $data){
        $unit_conversion = $data["unitConversion"];
        $balance = $data["balance"];

        $log10 = log10($unit_conversion);
        $balance_decimal = bcdiv((string)$balance, (string)$unit_conversion, $log10);
        $today_earning = $company_acc_amount[$wallet_type];
        $today_earning = $today_earning ? $today_earning : 0;
        $msg .= "$wallet_type | $balance_decimal\n";
        $msg .= "+ " . $today_earning;
        $msg .= "\n--------------------\n";
    }

    $params["tag"] = "Daily Commission Report";
    $params["message"] = $msg;
    $params["api_key"] = $config["thenux_wallet_transaction_API"];
    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
    $params["mobile_list"] = $xun_numbers;
    // $params["mobile_list"] = array("+60124466833");
    $url_string = $config["broadcast_url_string"];
    $result = $post->curl_post($url_string, $params, 0);
?>
