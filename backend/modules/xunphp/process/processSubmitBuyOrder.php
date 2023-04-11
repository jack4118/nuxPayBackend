<?php

    $currentPath = __DIR__;
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.post.php');        
    include($currentPath.'/../include/class.log.php');
    include($currentPath.'/../include/class.binance.php');
    include($currentPath.'/../include/class.aax.php'); 
    include($currentPath.'/../include/class.poloniex.php');
    include($currentPath.'/../include/class.provider.php');

    $exchangeOrderTable = "xun_exchange_order"; // same structure pg and nuxpay
    $poolTable = "xun_crypto_history"; // different, previously was pg_pool_transaction_usdt
    $cutOffTime = '2020-11-10 17:40:00';

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $webservice = new Webservice($db, "", "");
    $setting    = new Setting($db);
    $general    = new General($db, $setting);
    $post       = new Post($db, $webservice, $msgpack);
    $provider        = new Provider($db);
    $message         = new Message($db, $general, $provider);
    $binance = new Binance(
        $config['binanceAPIKey'],
        $config['binanceAPISecret'],
        $config['binanceAPIURL'], //"https://api.binance.com/api/v3/",
        $config['binanceWAPIURL'] //"https://api.binance.com/wapi/v3/"
    );

    $aax = new AAX(
        $config['aaxAPIKey'],
        $config['aaxAPISecret'],
        $config['aaxAPIURL']
    );
    $poloniex = new Poloniex(
        $config['poloniexAPIKey'],
        $config['poloniexAPISecret'],
        $config['poloniexAPIURL']
    );
    // validAAXStatus is designed for aax
    $validAAXStatus = array(
        0=>'NEW',//PENDING-NEW
        1=>'NEW',
        2=>'PARTIALLY_FILLED',
        3=>'FILLED',
        4=>'REJECTED',//CANCEL-REJECT
        5=>'CANCELED',
        6=>'REJECTED',
        7=>'EXPIRED',
        8=>'REJECTED');//BUSINESS-REJECT
    
    $wallet_parent_coin     = array('tetherusd' => "ETH", 'tronusdt' => "TRX",'livepeer'=>'ETH');
    $exchange_parent_coin   = array('tetherusd' => 0.1, 'tronusdt' => 200,'livepeer'=>0.1);
    //if you want to edit $exchange_symbol, please look at poloniex condition too, becaucase I write another variable to overwrite $exchange_symbol variable.
    $exchange_symbol        = array('tetherusd' => "ETHUSDT", 'tronusdt' => "TRXUSDT",'livepeer'=>'LPTUSDT');
    
    $logPath = $currentPath.'/log/';
    $logBaseName = basename(__FILE__, '.php');
    $log = new Log($logPath, $logBaseName);

    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Message - start processSubmitBuyOrder" );
    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - New Process" );
     
    // $db->where("disabled",0);
    // $db->where("type","exchange_swap");
    // $getProvider=$db->get("provider");

    // if(empty($getProvider)){
    //     $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Please enable the right provider in provider table" );
    //     exit;
    // }
    // if(count($getProvider)>1){
    //     $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Please check the provider, type=exchange_swap, only allow one provider enable at a times" );
    //     exit;
    // }
    // $providerName=$getProvider[0]['name'];
    // echo $providerName."\n";

    $db->where('is_exchange_miner', 1);
    $wallet_type_res = $db->get('xun_coins');
    foreach ($wallet_type_res as $wallet_type_row) {
        $wallet_type_array[] = $wallet_type_row['currency_id'];
        $wallet_swap_provider_id_array[] = $wallet_type_row['swap_provider_id'];
        
        if($wallet_type_row['swap_provider_id']==0){
            notify($message,$wallet_type_row['currency_id']);
        }else if(empty($wallet_type_row['swap_provider_id'])){
            notify($message,$wallet_type_row['currency_id']);
        }
        echo json_encode($wallet_type_array);
        echo json_encode($wallet_swap_provider_id_array);
     
        $db->where("id",$wallet_type_row['swap_provider_id']);
        $getProvider=$db->getOne("provider");
        $providerName=$getProvider['name'];
        var_dump($getProvider);

        if($providerName=='binance'){
            $accountInfo = $binance->getAccountInfo();   
            logJSON($accountInfo, "accountInfo");
            //echo "Account Info:\n";
            //print_r($accountInfo);
        
            // Set the wallet types to handle for submitting buy order
            //$wallet_type = 'tetherusd';
        
            // Output the current account balance for our supported coins
            foreach ($accountInfo['balances'] as $balanceRow) {
        
                $balanceArray[$balanceRow['asset']] = array('free' => $balanceRow['free'], 'locked' => $balanceRow['locked']);
        
            }
        
            echo date("Y-m-d H:i:s")." ETH Info\n";
            print_r($balanceArray['ETH']);
        
            echo date("Y-m-d H:i:s")." TRX Info\n";
            print_r($balanceArray['TRX']);
        
            echo date("Y-m-d H:i:s")." USDT Info\n";
            print_r($balanceArray['USDT']);
        
            // Loop through the wallet_type to perform the order
            foreach ($wallet_type_array as $wallet_type) {
        
                echo "\n".date("Y-m-d H:i:s")." Processing $wallet_type(".$exchange_symbol[$wallet_type].") now...\n";
        
                $db->where('wallet_type', $wallet_type);
                $db->where('order_processed', 0);
                $db->where('status', "success");    
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->orderBy('id', "ASC");
        
                // previously was pg_pool_transaction_usdt table, now moved to nuxpay side using xun_crypto_history
                // note: exchange rate maybe incorrect, please double confirm
                // exchange_rate is the     TOKEN USD
                // miner_fee_exchange_rate is     ETH USD
        
                unset($poolTransactionIDs);
                unset($fundOutTransactionIDs);
        
                $totalAmount = 0;
                $totalExchangeAmount = 0;
        
        
                $poolRes = $db->get($poolTable, null, "id, miner_fee, exchange_rate, miner_fee_exchange_rate");
                foreach ($poolRes as $poolRow) {
        
                    $exRate = bcmul($poolRow['exchange_rate'], $poolRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $poolRow['miner_fee'];
                    $totalExchangeAmount += bcdiv($poolRow['miner_fee'], $exRate, 8);
        
                    $poolTransactionIDs[] = $poolRow['id'];
        
                }
        
                $db->where('status', 'confirmed');
                $db->where('order_processed', 0);
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->where('wallet_type', $wallet_type);
                $db->orderBy('id', "ASC");
                $fund_out_res = $db->get('xun_crypto_fund_out_details', null, 'id, pool_amount, exchange_rate, miner_fee_exchange_rate');
                foreach ($fund_out_res as $fundOutRow){
                    $exRate = bcmul($fundOutRow['exchange_rate'], $fundOutRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $fundOutRow['pool_amount'];
                    $totalExchangeAmount += bcdiv($fundOutRow['pool_amount'], $exRate, 8);
        
                    $fundOutTransactionIDs[] = $fundOutRow['id'];
                }
        
                //echo "$totalEthAmount\n";
                // Get our average pricing
                $averagePrice = bcdiv($totalAmount, $totalExchangeAmount, 2);
                // Number format to 5 decimals based on the LOT SIZE
                $totalExchangeAmount = number_format($totalExchangeAmount, 5, ".", "");
                // Get the actual used USDT
                $actualUSDTUsed = bcmul($totalExchangeAmount, $averagePrice, 8);
        
                echo date("Y-m-d H:i:s")." Total USDT: $totalAmount\n";
                echo date("Y-m-d H:i:s")." Total ".$wallet_parent_coin[$wallet_type]." to buy: $totalExchangeAmount\n";
                echo date("Y-m-d H:i:s")." Actual USDT used: $actualUSDTUsed\n";
        
                if ($totalExchangeAmount < $exchange_parent_coin[$wallet_type]) {
                    // Check if total exchange amount is enought to exchange
                    echo date("Y-m-d H:i:s")." Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n";
                    continue;
                }
        
        
                echo date("Y-m-d H:i:s")." Processing ".$exchange_symbol[$wallet_type]."\n";
        
                // Start to submit order
                $price = $binance->getPrice($exchange_symbol[$wallet_type])['price'];
        
                echo date("Y-m-d H:i:s")." Our average price: $averagePrice\n";
        
                echo date("Y-m-d H:i:s")." Current ".$exchange_symbol[$wallet_type]." price: $price\n";
        
                $marketConverted = bcdiv($totalAmount, $price, 5);
        
                echo date("Y-m-d H:i:s")." Current market conversion: $marketConverted ".$wallet_parent_coin[$wallet_type]."\n";
        
                $orderRes = $binance->order("BUY", $exchange_symbol[$wallet_type], $totalExchangeAmount, $averagePrice, "LIMIT");
        
                echo date("Y-m-d H:i:s")." OrderRes:\n";
                print_r($orderRes);
        
                if ($orderRes['code'] < 0) {
        
                    echo date("Y-m-d H:i:s")." Error...\n";
        
                }
                else if ($orderRes['orderId']) {
        
                    // insert order records into order table
                    $insertData = array(
                        'reference_id' => $orderRes['orderId'],
                        'from_symbol' => 'USDT',
                        'to_symbol' => $wallet_parent_coin[$wallet_type],
                        'price' => $averagePrice,
                        'quantity' => $marketConverted,
                        'amount' => $actualUSDTUsed,
                        'status' => $orderRes['status'],
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s")
                    );
                    // updated from $result to $exchangeID so wont be overwritten
                    $exchangeID = $db->insert($exchangeOrderTable, $insertData);
                    echo date("Y-m-d H:i:s")." Insert result: $exchangeID.\n";
        
                    $updateData = array(
                        'exchange_order_id' => $exchangeID,
                        'order_processed'   => 1,
                    );
        
                    if (count($poolTransactionIDs) > 0) {
                        // Update pool transactions usdt to processed
                        $db->where('id', $poolTransactionIDs, "IN");
                        $result = $db->update($poolTable, $updateData);
                        echo date("Y-m-d H:i:s")." Update result: $result\n";
                    }
        
                    if(count($fundOutTransactionIDs) > 0){
                        $db->where('id', $fundOutTransactionIDs, "IN");
                        $result = $db->update('xun_crypto_fund_out_details', $updateData);
                        echo date("Y-m-d H:i:s")." Update xun_crypto_fund_out_details result: $result\n";
                    }
                }
                else {
        
                    echo date("Y-m-d H:i:s")." Unhandled response\n";
        
                }
        
            }
        }else if($providerName=='aax'){
            $accountInfo = json_decode($aax->getAccountBalances(),true);
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - config : ".json_encode($config['aaxAPIKey']) );
          
            $accountInfo = $accountInfo['data'];
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - accountInfo : ".json_encode($accountInfo) );
          
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_res : ".json_encode($wallet_type_res) );
          
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_array : ".json_encode($wallet_type_array) );
          
            // Output the current account balance for our supported coins
            foreach ($accountInfo as $balanceRow) { 
                if ($balanceRow['purseType'] != 'SPTP') continue;
        
                $balanceArray[$balanceRow['currency']] = array('free' => $balanceRow['available'], 'locked' => $balanceRow['unavailable']);
    
          
            }
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - balanceArray : ".json_encode($balanceArray) );
          
            echo date("Y-m-d H:i:s")." ETH Info\n";
            print_r($balanceArray['ETH']);
        
            echo date("Y-m-d H:i:s")." TRX Info\n";
            print_r($balanceArray['TRX']);
        
            echo date("Y-m-d H:i:s")." USDT Info\n";
            print_r($balanceArray['USDT']);
        
            // Loop through the wallet_type to perform the order
            foreach ($wallet_type_array as $wallet_type) {
        
                echo "\n".date("Y-m-d H:i:s")." Processing $wallet_type(".$exchange_symbol[$wallet_type].") now...\n";
        
                $db->where('wallet_type', $wallet_type);
                $db->where('order_processed', 0);
                $db->where('status', "success");    
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->orderBy('id', "ASC");
        
                // previously was pg_pool_transaction_usdt table, now moved to nuxpay side using xun_crypto_history
                // note: exchange rate maybe incorrect, please double confirm
                // exchange_rate is the     TOKEN USD
                // miner_fee_exchange_rate is     ETH USD
        
                unset($poolTransactionIDs);
                unset($fundOutTransactionIDs);
        
                $totalAmount = 0;
                $totalExchangeAmount = 0;
        
        
                $poolRes = $db->get($poolTable, null, "id, miner_fee, exchange_rate, miner_fee_exchange_rate");
                // var_dump($poolRes);
                foreach ($poolRes as $poolRow) {
        
                    $exRate = bcmul($poolRow['exchange_rate'], $poolRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $poolRow['miner_fee'];
                    $totalExchangeAmount += bcdiv($poolRow['miner_fee'], $exRate, 8);
        
                    $poolTransactionIDs[] = $poolRow['id'];
        
                }
        
                $db->where('status', 'confirmed');
                $db->where('order_processed', 0);
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->where('wallet_type', $wallet_type);
                $db->orderBy('id', "ASC");
                $fund_out_res = $db->get('xun_crypto_fund_out_details', null, 'id, pool_amount, exchange_rate, miner_fee_exchange_rate');
                foreach ($fund_out_res as $fundOutRow){
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - fundOutRow : ".json_encode($fundOutRow) );
          
           
                    $exRate = bcmul($fundOutRow['exchange_rate'], $fundOutRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $fundOutRow['pool_amount'];
                    $totalExchangeAmount += bcdiv($fundOutRow['pool_amount'], $exRate, 8);
        
                    $fundOutTransactionIDs[] = $fundOutRow['id'];
                }
        
                //echo "$totalEthAmount\n";
                // Get our average pricing
                $averagePrice = bcdiv($totalAmount, $totalExchangeAmount, 2);
                // Number format to 5 decimals based on the LOT SIZE
                $totalExchangeAmount = number_format($totalExchangeAmount, 5, ".", "");
                // Get the actual used USDT
                $actualUSDTUsed = bcmul($totalExchangeAmount, $averagePrice, 8);
        
                echo date("Y-m-d H:i:s")." Total USDT: $totalAmount\n";
                echo date("Y-m-d H:i:s")." Total ".$wallet_parent_coin[$wallet_type]." to buy: $totalExchangeAmount\n";
                echo date("Y-m-d H:i:s")." Actual USDT used: $actualUSDTUsed\n";
        
                if ($totalExchangeAmount < $exchange_parent_coin[$wallet_type]) {
                    // Check if total exchange amount is enought to exchange
                    echo date("Y-m-d H:i:s")." Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n");
          
                    continue;
                }
        
        
                echo date("Y-m-d H:i:s")." Processing ".$exchange_symbol[$wallet_type]."\n";
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Processing ".$exchange_symbol[$wallet_type] );
               
                // Start to submit order
                // $price = $binance->getPrice($exchange_symbol[$wallet_type])['price'];
                
                // $price=$aax->getCurrentMarkPrice($exchange_symbol[$wallet_type]."FP");
                // $price=json_decode($price,true)['p'];
    
                $price=$aax->getRecentTrades($exchange_symbol[$wallet_type].'FP',1);
                $price=abs(json_decode(($price),true)['trades'][0]['p']);
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
          
                if($price==0){
                    $db->where("cryptocurrency_id",$wallet_type);
                    $price=$db->getOne("xun_cryptocurrency_rate");
                    if(empty($price)){
                        echo "cannot getPrice\n";
                        $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - cannot getPrice" );
                        continue;
                    }
                    $price=$price['value'];
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price db : ".json_encode($price) );
          
                }
                echo $price."\n";
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type : ".json_encode($wallet_type) );
                
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
                echo date("Y-m-d H:i:s")." Our average price: $averagePrice\n";
        
                echo date("Y-m-d H:i:s")." Current ".$exchange_symbol[$wallet_type]." price: $price\n";
        
                $marketConverted = bcdiv($totalAmount, $price, 5);
        
                echo date("Y-m-d H:i:s")." Current market conversion: $marketConverted ".$wallet_parent_coin[$wallet_type]."\n";
                $totalExchangeAmount=number_format($totalExchangeAmount,3,".","");
                $orderRes=$aax->createANewSpotOrder("LIMIT",$exchange_symbol[$wallet_type],$averagePrice,$totalExchangeAmount,strtoupper("BUY"),"AAX");
                $orderRes=json_decode(($orderRes),true);
                
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - orderRes : ".json_encode($orderRes) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - averagePrice : ".json_encode($averagePrice) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - totalExchangeAmount : ".json_encode($totalExchangeAmount) );
                
                echo "orderRes".json_encode($orderRes)."\n";
                echo "exchange_symbol wallet_type".json_encode($$exchange_symbol[$wallet_type])."\n";
                echo "averagePrice".json_encode($averagePrice)."\n";
                echo "totalExchangeAmount".json_encode($totalExchangeAmount)."\n";
                //$orderRes = $binance->order("BUY", $exchange_symbol[$wallet_type], $totalExchangeAmount, $averagePrice, "LIMIT");
        
                if ($orderRes['code'] != 1) {
        
                    echo date("Y-m-d H:i:s")." Error...".json_encode($orderRes)."\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - orderRes : ".json_encode($orderRes) );
                    continue;
                }
                else if ($orderRes['data']['orderID']) {
        
                    // insert order records into order table
                    $insertData = array(
                        'reference_id' => $orderRes['data']['orderID'],
                        'from_symbol' => 'USDT',
                        'to_symbol' => $wallet_parent_coin[$wallet_type],
                        'price' => $averagePrice,
                        'quantity' => $marketConverted,
                        'amount' => $actualUSDTUsed,
                        'status' => $validAAXStatus[$orderRes['data']['orderStatus']],
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s")
                    );
                    // updated from $result to $exchangeID so wont be overwritten
                    $exchangeID = $db->insert($exchangeOrderTable, $insertData);
                    echo date("Y-m-d H:i:s")." Insert result: $exchangeID.\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Insert result: $exchangeID" );
                   
                    $updateData = array(
                        'exchange_order_id' => $exchangeID,
                        'order_processed'   => 1,
                    );
        
                    if (count($poolTransactionIDs) > 0) {
                        // Update pool transactions usdt to processed
                        $db->where('id', $poolTransactionIDs, "IN");
                        $result = $db->update($poolTable, $updateData);
                        echo date("Y-m-d H:i:s")." Update result: $result\n";
                    }
        
                    if(count($fundOutTransactionIDs) > 0){
                        $db->where('id', $fundOutTransactionIDs, "IN");
                        $result = $db->update('xun_crypto_fund_out_details', $updateData);
                        echo date("Y-m-d H:i:s")." Update xun_crypto_fund_out_details result: $result\n";
                    }
                }
                else {
        
                    echo date("Y-m-d H:i:s")." Unhandled response\n";
        
                }
        
            }
        }else if($providerName=='paymentgateway'){
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - paymentgateway" );
            $rpc=array(
                "command"   => "getUSDTBalanceOf",
                "name"      => $config['cryptoPartnerName'],
                "apiKey"    => $config['cryptoApiKey'],
                "site"      => $config['cryptoSite'],
                "params"    => array()
            );
            $getUSDTBalanceOf = curlPrivateNode($rpc,$config['cryptoUrl']);
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Debug - ".json_encode($config['cryptoUrl']) );
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Debug - ".json_encode($rpc) );
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Debug - ".json_encode($getUSDTBalanceOf));
            
            if($getUSDTBalanceOf['code']!=0){
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - getUSDTBalanceOf failed" );
                continue;
            }else if($getUSDTBalanceOf['data']==0){
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - Not enough balance" );
                continue;
            }
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_res : ".json_encode($wallet_type_res) );
          
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_array : ".json_encode($wallet_type_array) );
          

            // Output the current account balance for our supported coins
            $balanceArray['USDT']=bcdiv($getUSDTBalanceOf['data'],1000000,6);
              $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - balance : ".json_encode($balanceArray['USDT']) );
          
            echo date("Y-m-d H:i:s")." USDT Info\n";
            print_r($balanceArray['USDT']);
            if($balanceArray['USDT']==0){
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - Not enough balance" );
                continue;
            }
            // Loop through the wallet_type to perform the order
            foreach ($wallet_type_array as $wallet_type) {
        
                echo "\n".date("Y-m-d H:i:s")." Processing $wallet_type(".$exchange_symbol[$wallet_type].") now...\n";
        
                $db->where('wallet_type', $wallet_type);
                $db->where('order_processed', 0);
                $db->where('status', "success");    
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->orderBy('id', "ASC");
        
                // previously was pg_pool_transaction_usdt table, now moved to nuxpay side using xun_crypto_history
                // note: exchange rate maybe incorrect, please double confirm
                // exchange_rate is the     TOKEN USD
                // miner_fee_exchange_rate is     ETH USD
        
                unset($poolTransactionIDs);
                unset($fundOutTransactionIDs);
        
                $totalAmount = 0;
                $totalExchangeAmount = 0;
        
        
                $poolRes = $db->get($poolTable, null, "id, miner_fee, exchange_rate, miner_fee_exchange_rate");
                // var_dump($poolRes);
                foreach ($poolRes as $poolRow) {
        
                    $exRate = bcmul($poolRow['exchange_rate'], $poolRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $poolRow['miner_fee'];
                    $totalExchangeAmount += bcdiv($poolRow['miner_fee'], $exRate, 8);
        
                    $poolTransactionIDs[] = $poolRow['id'];
        
                }
        
                $db->where('status', 'confirmed');
                $db->where('order_processed', 0);
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->where('wallet_type', $wallet_type);
                $db->orderBy('id', "ASC");
                $fund_out_res = $db->get('xun_crypto_fund_out_details', null, 'id, pool_amount, exchange_rate, miner_fee_exchange_rate');
                foreach ($fund_out_res as $fundOutRow){
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - fundOutRow : ".json_encode($fundOutRow) );
          
           
                    $exRate = bcmul($fundOutRow['exchange_rate'], $fundOutRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $fundOutRow['pool_amount'];
                    $totalExchangeAmount += bcdiv($fundOutRow['pool_amount'], $exRate, 8);
        
                    $fundOutTransactionIDs[] = $fundOutRow['id'];
                }
        
                //echo "$totalEthAmount\n";
                // Get our average pricing
                $averagePrice = bcdiv($totalAmount, $totalExchangeAmount, 2);
                // Number format to 5 decimals based on the LOT SIZE
                // $totalExchangeAmount = number_format($totalExchangeAmount, 5, "", "");
                // Get the actual used USDT
                $actualUSDTUsed = bcmul($totalExchangeAmount, $averagePrice, 8);
        
                echo date("Y-m-d H:i:s")." Total USDT: $totalAmount\n";
                echo date("Y-m-d H:i:s")." Total ".$wallet_parent_coin[$wallet_type]." to buy: $totalExchangeAmount\n";
                echo date("Y-m-d H:i:s")." Actual USDT used: $actualUSDTUsed\n";
        
                if ($actualUSDTUsed < $exchange_parent_coin[$wallet_type]) {
                    // Check if total exchange amount is enought to exchange
                    echo date("Y-m-d H:i:s")." Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n");
          
                    continue;
                }
        
        
                echo date("Y-m-d H:i:s")." Processing ".$exchange_symbol[$wallet_type]."\n";
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Processing ".$exchange_symbol[$wallet_type] );
               
    
                // $price=$aax->getRecentTrades($exchange_symbol[$wallet_type].'FP',1);
                // $price=abs(json_decode(($price),true)['trades'][0]['p']);
                // $rpcPrice=array(
                //     "command"   => "getUSDTBalanceOf",
                //     "name"      =>"TheNux",
                //     "apiKey"    => "b983f5b4-63cc-a330-3681-febe41fa77e3",
                //     "site"      => "crypto.thenux.com",
                //     "params"    => array()
                // );
                // $price = curlPrivateNode("","http://pg");
                // $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
          
                // if($price==0){
                //     $db->where("cryptocurrency_id",$wallet_type);
                //     $price=$db->getOne("xun_cryptocurrency_rate");
                //     if(empty($price)){
                //         echo "cannot getPrice\n";
                //         $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - cannot getPrice" );
                //         continue;
                //     }
                //     $price=$price['value'];
                //     $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price db : ".json_encode($price) );
          
                // }
                // echo $price."\n";
                // $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type : ".json_encode($wallet_type) );
                
                // $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
                // echo date("Y-m-d H:i:s")." Our average price: $averagePrice\n";
        
                // echo date("Y-m-d H:i:s")." Current ".$exchange_symbol[$wallet_type]." price: $price\n";
        
                // $marketConverted = bcdiv($totalAmount, $price, 5);
        
                // echo date("Y-m-d H:i:s")." Current market conversion: $marketConverted ".$wallet_parent_coin[$wallet_type]."\n";
                // $totalExchangeAmount=number_format($totalExchangeAmount,3,".","");
                // $orderRes=$aax->createANewSpotOrder("LIMIT",$exchange_symbol[$wallet_type],$averagePrice,$totalExchangeAmount,strtoupper("BUY"),"AAX");
                // $orderRes=json_decode(($orderRes),true);
                
                    $db->where("cryptocurrency_id","tron");
                    $price=$db->getOne("xun_cryptocurrency_rate");
                    if(empty($price)){
                        echo "cannot getPrice\n";
                        $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - cannot getPrice" );
                        continue;
                    }
                    $price=$price['value'];
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price db : ".json_encode($price) );
                          
                    $toAmount=bcmul($actualUSDTUsed,$price,6);
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - actualUSDTUsed : ".json_encode($actualUSDTUsed) );
                  
                $rpcPrice=array(
                    "command"   => "swapCoin",
                    "name"      => $config['cryptoPartnerName'],
                    "apiKey"    => $config['cryptoApiKey'],
                    "site"      => $config['cryptoSite'],
                    "params"    => array(
                        "businessName"      => "NuxPay",
                        "fromCreditName"    => "tronUSDT",
                        "fromSymbol"        => "TRX-USDT",
                        "toCreditName"      => "tron",
                        "toSymbol"          => "TRX",
                        "fromAmount"        => $actualUSDTUsed,
                        "toAmount"          => $toAmount,
                        "priceMarket"       => $price,
                        "providerName"      => "paymentgateway",
                    )
                );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - orderRes : ".json_encode($rpcPrice) );
               
                $orderRes = curlPrivateNode($rpcPrice,$config['cryptoUrl']);

                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - orderRes : ".json_encode($orderRes) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - averagePrice : ".json_encode($averagePrice) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - totalExchangeAmount : ".json_encode($totalExchangeAmount) );
                
                echo "orderRes".json_encode($orderRes)."\n";
                echo "exchange_symbol wallet_type".json_encode($$exchange_symbol[$wallet_type])."\n";
                echo "averagePrice".json_encode($averagePrice)."\n";
                echo "totalExchangeAmount".json_encode($totalExchangeAmount)."\n";
                //$orderRes = $binance->order("BUY", $exchange_symbol[$wallet_type], $totalExchangeAmount, $averagePrice, "LIMIT");
        
                if ($orderRes['code'] != 0) {
        
                    echo date("Y-m-d H:i:s")." Error...".json_encode($orderRes)."\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - orderRes : ".json_encode($orderRes) );
                    continue;
                }
        
                    // insert order records into order table
                    $insertData = array(
                        'from_symbol' => 'USDT',
                        'to_symbol' => $wallet_parent_coin[$wallet_type],
                        'price' => $averagePrice,
                        'quantity' => $marketConverted,
                        'amount' => $actualUSDTUsed,
                        'status' => 'success',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s")
                    );
                    // updated from $result to $exchangeID so wont be overwritten
                    $exchangeID = $db->insert($exchangeOrderTable, $insertData);
                    echo date("Y-m-d H:i:s")." Insert result: $exchangeID.\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Insert result: $exchangeID" );
                   
                    $updateData = array(
                        'exchange_order_id' => $exchangeID,
                        'order_processed'   => 1,
                    );
        
                    if (count($poolTransactionIDs) > 0) {
                        // Update pool transactions usdt to processed
                        $db->where('id', $poolTransactionIDs, "IN");
                        $result = $db->update($poolTable, $updateData);
                        echo date("Y-m-d H:i:s")." Update result: $result\n";
                    }
        
                    if(count($fundOutTransactionIDs) > 0){
                        $db->where('id', $fundOutTransactionIDs, "IN");
                        $result = $db->update('xun_crypto_fund_out_details', $updateData);
                        echo date("Y-m-d H:i:s")." Update xun_crypto_fund_out_details result: $result\n";
                    }
            
        
        
            }
        }else if($providerName=='poloniex'){
            $exchange_symbol        = array('tetherusd' => "USDT_ETH", 'tronusdt' => "USDT_TRX",'livepeer'=>'USDT_LPT'); 
            // $accountInfo = json_decode($poloniex->returnCompleteBalances(),true);
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - config : ".json_encode($config['poloniexAPIKey']) );
            // $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - accountInfo : ".json_encode($accountInfo) );
          
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_res : ".json_encode($wallet_type_res) );
          
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type_array : ".json_encode($wallet_type_array) );
          
            // Output the current account balance for our supported coins
            // foreach ($getBalance as $key =>$value) { 
            //     $balanceArray[$key] = array('free' => $value['available'], 'locked' => $value['onOrders']);          
            // }
            // $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - balanceArray : ".json_encode($balanceArray) );
          
            // echo date("Y-m-d H:i:s")." ETH Info\n";
            // print_r($balanceArray['ETH']);
        
            // echo date("Y-m-d H:i:s")." TRX Info\n";
            // print_r($balanceArray['TRX']);
        
            // echo date("Y-m-d H:i:s")." USDT Info\n";
            // print_r($balanceArray['USDT']);
        
            // Loop through the wallet_type to perform the order
            foreach ($wallet_type_array as $wallet_type) {
        
                echo "\n".date("Y-m-d H:i:s")." Processing $wallet_type(".$exchange_symbol[$wallet_type].") now...\n";
        
                $db->where('wallet_type', $wallet_type);
                $db->where('order_processed', 0);
                $db->where('status', "success");    
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->orderBy('id', "ASC");
        
                // previously was pg_pool_transaction_usdt table, now moved to nuxpay side using xun_crypto_history
                // note: exchange rate maybe incorrect, please double confirm
                // exchange_rate is the     TOKEN USD
                // miner_fee_exchange_rate is     ETH USD
        
                unset($poolTransactionIDs);
                unset($fundOutTransactionIDs);
        
                $totalAmount = 0;
                $totalExchangeAmount = 0;
        
        
                $poolRes = $db->get($poolTable, null, "id, miner_fee, exchange_rate, miner_fee_exchange_rate");
                // var_dump($poolRes);
                foreach ($poolRes as $poolRow) {
        
                    $exRate = bcmul($poolRow['exchange_rate'], $poolRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $poolRow['miner_fee'];
                    $totalExchangeAmount += bcdiv($poolRow['miner_fee'], $exRate, 8);
        
                    $poolTransactionIDs[] = $poolRow['id'];
        
                }
        
                $db->where('status', 'confirmed');
                $db->where('order_processed', 0);
                $db->where('created_at', $cutOffTime, ">="); // Cut off time
                $db->where('wallet_type', $wallet_type);
                $db->orderBy('id', "ASC");
                $fund_out_res = $db->get('xun_crypto_fund_out_details', null, 'id, pool_amount, exchange_rate, miner_fee_exchange_rate');
                foreach ($fund_out_res as $fundOutRow){
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - fundOutRow : ".json_encode($fundOutRow) );
          
           
                    $exRate = bcmul($fundOutRow['exchange_rate'], $fundOutRow['miner_fee_exchange_rate']);
        
                    $totalAmount += $fundOutRow['pool_amount'];
                    $totalExchangeAmount += bcdiv($fundOutRow['pool_amount'], $exRate, 8);
        
                    $fundOutTransactionIDs[] = $fundOutRow['id'];
                }
        
                //echo "$totalEthAmount\n";
                // Get our average pricing
                $averagePrice = bcdiv($totalAmount, $totalExchangeAmount, 2);
                // Number format to 5 decimals based on the LOT SIZE
                $totalExchangeAmount = number_format($totalExchangeAmount, 5, ".", "");
                // Get the actual used USDT
                $actualUSDTUsed = bcmul($totalExchangeAmount, $averagePrice, 8);
        
		        $price=json_decode($poloniex->returnTicker(),true);
                $price=$price[$exchange_symbol[$wallet_type]]['last'];

		        $marketConverted = bcdiv($totalAmount, $price, 5);
                $totalAmount = number_format($totalAmount, 2);
                // echo date("Y-m-d H:i:s")." Total USDT: $totalAmount\n";
                // echo date("Y-m-d H:i:s")." Total ".$wallet_parent_coin[$wallet_type]." to buy: $totalAmount\n";
                //echo date("Y-m-d H:i:s")." Actual USDT used: $actualUSDTUsed\n";

                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Total USDT : ".$totalAmount."\n");
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Market Converted : ".$marketConverted."\n");
        
                if ($marketConverted < $exchange_parent_coin[$wallet_type]) {
                    // Check if total exchange amount is enought to exchange
                    echo date("Y-m-d H:i:s")." Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - Total amount of USDT not enough to buy at least ".$exchange_parent_coin[$wallet_type]." ".$wallet_parent_coin[$wallet_type]." yet\n");
          
                    continue;
                }
        
        
                echo date("Y-m-d H:i:s")." Processing ".$exchange_symbol[$wallet_type]."\n";
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Processing ".$exchange_symbol[$wallet_type] );
               
                // Start to submit order
                // $price = $binance->getPrice($exchange_symbol[$wallet_type])['price'];
                
                // $price=$aax->getCurrentMarkPrice($exchange_symbol[$wallet_type]."FP");
                // $price=json_decode($price,true)['p'];
    
                //$price=json_decode($poloniex->returnTicker(),true);
                //$price=$price[$exchange_symbol[$wallet_type]]['last'];
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
          
                if($price==0){
                    $db->where("cryptocurrency_id",$wallet_type);
                    $price=$db->getOne("xun_cryptocurrency_rate");
                    if(empty($price)){
                        echo "cannot getPrice\n";
                        $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - cannot getPrice" );
                        continue;
                    }
                    $price=$price['value'];
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price db : ".json_encode($price) );
          
                }
                echo "Price : ".$price."\n";
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - wallet_type : ".json_encode($wallet_type) );
                
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - price : ".json_encode($price) );
                echo date("Y-m-d H:i:s")." Our average price: ".$averagePrice."\n";
        
                echo date("Y-m-d H:i:s")." Current ".$exchange_symbol[$wallet_type]." price: ".$price."\n";
        
                //$marketConverted = bcdiv($totalAmount, $price, 5);
       		    $marketConverted = bcdiv($marketConverted, 1, 3); 
                echo date("Y-m-d H:i:s")." Current market conversion: $marketConverted ".$wallet_parent_coin[$wallet_type]."\n";
                $totalExchangeAmount=number_format($totalExchangeAmount,3,".","");
                
		        $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - buy: ".$exchange_symbol[$wallet_type].", $averagePrice, $totalExchangeAmount");
		        // echo date( 'Y-m-d H:i:s' ) . " Log - buy: ".$exchange_symbol[$wallet_type].", $averagePrice, $totalExchangeAmount\n";
		        // echo date( 'Y-m-d H:i:s' ) . " Log - buy2: ".$exchange_symbol[$wallet_type].", $price, $marketConverted\n";
		
		        // $orderRes=json_decode($poloniex->buy($exchange_symbol[$wallet_type],$price,$marketConverted),true);
                $orderRes = $poloniex->newPlaceOrder($exchange_symbol[$wallet_type],$totalAmount);
                $orderRes_status = json_decode($orderRes)->status;
                $orderRes_data_id = json_decode($orderRes)->data->id;

                
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - orderRes : ".json_encode($orderRes) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - averagePrice : ".json_encode($averagePrice) );
                $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - totalExchangeAmount : ".json_encode($totalExchangeAmount) );
                
                echo "orderRes".json_encode($orderRes)."\n";
                echo "exchange_symbol wallet_type".json_encode($exchange_symbol[$wallet_type])."\n";
                echo "averagePrice".json_encode($averagePrice)."\n";
                echo "totalExchangeAmount".json_encode($totalExchangeAmount)."\n";
                //$orderRes = $binance->order("BUY", $exchange_symbol[$wallet_type], $totalExchangeAmount, $averagePrice, "LIMIT");
        
                if ($orderRes_status == 'error') {
        
                    echo date("Y-m-d H:i:s")." Error...".json_encode($orderRes)."\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - orderRes : ".json_encode($orderRes) );
                    continue;
                }
                else if ($orderRes_data_id) {
        
                    $orderStatus = $poloniex->newOrderStatus($orderRes_data_id);
                    $status = json_decode($orderStatus)->status;
                    $state = json_decode($orderStatus)->data->state;


                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Message - orderStatus1 : ".json_encode($orderStatus));
                    // $orderStatus=$orderStatus['result'][$orderRes['orderNumber']]['status'];

                    echo "orderStatus1 : ".json_encode($orderStatus)."\n";

                    echo "orderStatus_int : ".$state."\n";
                    $orderStatusTemp = $state;
                    //foreach($orderStatus as $key=>$value){
                        //$orderStatusTemp=$value['status'];
                        //break;
                    //}
                    $orderStatus =$orderStatusTemp;
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Message - orderStatus2 : ".json_encode($orderStatus) );
                    
                    echo "orderStatus2 : ".json_encode($orderStatus)."\n";
                    if(empty($orderStatus)){
                        $log->write("\n". date( 'Y-m-d H:i:s' ) . " Error - orderStatus3 : ".json_encode($orderStatus) );
                        echo "orderStatus3 : ".json_encode($orderStatus)."\n";
                        $orderStatus="unknown";
                    }

                    // insert order records into order table
                    $insertData = array(
                        'reference_id' => $orderRes_data_id,
                        'from_symbol' => 'USDT',
                        'to_symbol' => $wallet_parent_coin[$wallet_type],
                        'price' => $price,
                        'quantity' => $marketConverted,
                        'amount' => $totalAmount,
                        'status' => $orderStatus,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s")
                    );
                    // updated from $result to $exchangeID so wont be overwritten
                    $exchangeID = $db->insert($exchangeOrderTable, $insertData);
                    echo date("Y-m-d H:i:s")." Insert result: $exchangeID.\n";
                    $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log -  Insert result: $exchangeID" );
                   
                    $updateData = array(
                        'exchange_order_id' => $exchangeID,
                        'order_processed'   => 1,
                    );
        
                    if (count($poolTransactionIDs) > 0) {
                        // Update pool transactions usdt to processed
                        $db->where('id', $poolTransactionIDs, "IN");
                        $result = $db->update($poolTable, $updateData);
                        echo date("Y-m-d H:i:s")." Update result: $result\n";
                    }
        
                    if(count($fundOutTransactionIDs) > 0){
                        $db->where('id', $fundOutTransactionIDs, "IN");
                        $result = $db->update('xun_crypto_fund_out_details', $updateData);
                        echo date("Y-m-d H:i:s")." Update xun_crypto_fund_out_details result: $result\n";
                    }
                }
                else {
        
                    echo date("Y-m-d H:i:s")." Unhandled response\n";
        
                }
        
            }
        }
        unset($wallet_type_array);
        unset($wallet_swap_provider_id_array);
    }
    



    function logJSON($var, $message) {
        global $log;
        foreach($var as $i => $v) {
            $log->write("\n". date( 'Y-m-d H:i:s' ) . " Log - $message ".$i." ".json_encode($v) );
        }
    }
    function curlPrivateNode($data, $url, $decode=true) {

        $data = json_encode($data);
    
        // prepare new cURL resource
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
        // set HTTP header for POST request 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: '.strlen($data))
        );
    
        // submit the POST request
        $result = curl_exec($ch);
        if (curl_error($ch))
            $res['error']['message'] = curl_error($ch);
        else
            $res = $decode ? json_decode($result, true) : $result;
    
        // close cURL
        curl_close($ch);
    
        return $res;
    }
    function notify($message,$walletType){
        $find = array("%%subject%%", "%%errorMessage%%", '%%walletType%%');
        $replace = array("Swap provider error", "Please enable the right swap provider.", "$walletType");
        $message->createMessageOut('10036', NULL, NULL, $find, $replace);

    }

    
?>
