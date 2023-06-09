<?php

$repository = array(
    /* -- Example -- */
    //"timestamp" => "Descriptions.",
    
    /*-- by Eng --*/
    "1609390732" => "Add provider settings for binance. Created new xun_swap_history table to keep track of all swap coins record. Created new xun_swap_setting table to store the coins flag.",
    "1609560864" => "Create new xun_swap_request table.",
    "1609732195" => "Add new columns from_tx_id and to_tx_id into xun_swap_history table.",
    "1612693764" => "Add new settings for Binance provider in provider_setting table.",
    "1612853923" => "Add new column is_exchange_miner into xun_coins table.",

    /*-- by zack --*/
    "1597660529" => "created xun_request_fund_withdrawal table",
    "1599197309" => "Add new column received_transaction_id in xun_crypto_history.",
    "1599213872" => "Alter xun_payment_gateway_invoice_detail table payment_description column change from varchar 255 to varchar 1000",

    /*-- by huiwen --*/
    "1597804725" => "add xun_payment_gateway_invoice_transaction table and add consolidate wallet address in system setting",
    "1597827661" => "add transaction_type column in xun_payment_gateway_invoice_transaction",
    "1599207479" => "add miner fee top up sender address in system setting",
    "1599712169" => "add merchant listing permission",
    "1599808767" => "update create nuxpay user permission name to register merchant",
    "1600358690" => "add wallet_name column in xun_crypto_destination_address",
    "1601262683" => "change transaction history permission name to fund in listing",
    "1601546996" => "add xun_payment_gateway_withdrawal table",
    "1601556678" => "add transaction_type column in xun_payment_gateway_withdrawal",
    "1601903723" => "add edit merchant details permission",
    "1602058256" => "add status column in reseller table",
    "1602067311" => "add reseller application listing permission",
    "1602135554" => "add reseller_website column in site table",
    "1602220125" => "add edit reseller details permission table",
    "1602223224" => "patch reseller table status if password has set before",
    "1602672705" => "add image_url_path column in site table",
    "1602690682" => "add theme_color column in site table",
    "1602747530" => "add source columm in xun_user_verification",
    "1602770680" => "add short_code column in short_url_table",
    "1602782253" => "add device_model table",
    "1603271938" => "add short_url column in site table",
    "1603439720" => "insert reseller timeout in system setting",
    "1603685968" => "add remark column in xun_crypto_fund_out_details",
    "1603780853" => "add type column in xun_user_verification table",
    "1604070643" => "add withdrawal_id column in xun_crypto_history",
    "1604378517" => "add referral code column in reseller table",
    "1604993867" => "add reseller_id column in xun_marketer_commission_transaction table",
    "1605168242" => "add landing page table , add nuxpay bucket and add indexes for landing page table",
    "1605699938" => "update system system landing page default image value",
    "1606054611" => "add xun_payment_gatway_fund_in table",
    "1606293118" => "add type in xun_payment_gateway_fund_in and compensate fee amount setting",
    "1607314617" => "add gateway_type column in xun_crypto_fund_out_details",
    "1607393507" => "add deleted column in xun_payment_gateway_invoice_transaction table",
    "1607579493" => "add pool_transferred column in xun_crypto_history and pool threshold setting",
    "1607670238" => "update existing order_processed to 1 in xun_crypto_history",
    "1607914085" => "add HR account roles and permissions",
    "1607947684" => "add exchange_rate and miner_fee_exchange_rate colum  in xun_crypto_fund_out_details",
    "1608019164" => "add ftag provider and sites setting",
    "1608613402" => "add pool_transferred columm in xun_crypto_fund_out_details table",
    "1608715564" => "add xun_payment_gateway_send_fund table",
    "1609299382" => "add sender_name, sender_mobile_number, sender_email_address in xun_payment_gateway_send_fund",
    "1609908086" => "change account type default to premium and upgrade existing account to premium",
    "1611329935" => "add processed column in xun_payment_gateway_invoice_transaction",
    "1612071872" => "tune processes table file path and output path",
    "1612353047" => "add http_code column in xun_webservices table",
    "1612765425" => "add tron and tron usdt coin and add display_symbol column in xun_marketplace_currencies",
    "1612936838" => "add crypto_transaction_token and payment_type in xun_payment_gateway_payment_transaction",
    "1612944587" => "change USDT(TRON) to USDT(TRC20)",
    "1613634968" => "add service charge fund out address in system setting",
    "1613659517" => "add xun_payment_transaction, xun_payment_method, xun_payment_details, xun_payment_transaction_history table",
    "1613711233" => "add wallet_type in xun_payment_transaction and transaction token in xun_payment_details",
    "1613820658" => "add fund_out_transaction_id, tx_exchange_rate and fiat_currency_id in xun_payment_details",
    "1613971288" => "add bc external company pool address for tronusdt",
    "1614427736" => "add miner pool address in system setting and add pool transferred",
    "1614935261" => "add processCheckOrder in processes table",
    "1614946026" => "add huatcoin",
    "1615389343" => "add transaction_history_table and transaction_history_id in xun_wallet_transaction",
    "1615879584" => "change user_id column to varchar data type",
    "1616664885" => "add bc_reference_id in xun_wallet_transaction",
    "1616823883" => "add xun_crypto_wallet_offset",
    "1617004412" => "add received_transaction_hash column in xun_service_charge_audit table",
    "1617600526" => "add bc_reference_id in xun_crypto_transaction_hash",
    "1618376021" => "add actual miner fee and actual miner fee wallet type in xun_payment_gateway_withdrawal",
    "1618458528" => "add filecoin",
    "1620400489" => "change filecoin image",
    "1619409570" => "add xun_crypto_payment_transaction table, add simplex and xanpool",
    "1619445014" => "add fiatCurrencyList in provider_setting",
    "1619579425" => "add simplex margin percentage",
    "1619684902" => "add min amount setting for simplex",
    "1619697886" => "add max amount setting for simplex",
    "1619753165" => "add dailyAmount, monthlyAmount and maxDailyTransaction provider setting",
    "1619757160" => "add fee_amount and fee currency column",
    "1619776575" => "add destination_address column in xun_crypto_payment_transaction",
    "1619778774" => "change payment amount and payment currency column name",
    "1619789256" => "add reference_id column in xun_crypto_payment_transaction",
    "1620006136" => "add processCheckXanpoolOrder in processes table",
    "1620012442" => "add is enabled setting in provider_setting",
    "1620275560" => "add minCryptoAmount in provider setting",
    "1618458528" => "add filecoin",
    "1620400489" => "change filecoin image",
    "1622601580" => "add filecoin external company profit setting",
    "1622704157" => "add auto fund out multi token wallet type setting",
    "1622774237" => "add xun_crypto_paymenr_request table",
    "1622775758" => "add buy sell callback url",
    "1622804938" => "add tron usdt min and max amount setting for simplex",
    "1622806260" => "change tron usdt wallet name in xun_marketplace_currencies",
    "1622814212" => "add reference_id column in xun_crypto_payment_request table",
    "1624426217" => "add filecoin swap setting",
    "1624510135" => "tuned filecoin swap provider_setting",
    "1624529357" => "add buysell crypto redirect url",

    /*-- by bryan --*/
    "1601525559" => "Add xun_request_fund_item_detail table and index",
    "1603763484" => "Reorder priority of permissions table for Reseller site",
    "1603789688" => "Removed roles_permissions resellerListing.php and resellerApplicationListing.php page for reseller",
    "1603791084" => "Corrected query to remove resellerListing.php page for reseller",
    "1603944694" => "Added is_auto_fund_out column to xun_coins. Added status column to blockchain_external_address",
    "1603952785" => "Set all addresses in blockchain_external_address' status to 1",
    "1604309768" => "Added table xun_crypto_history_summary",
    "1604375428" => "Added transaction_date column to xun_crypto_history_summary",
    "1604376146" => "Tune query to add transaction_date column to xun_crypto_history_summary",
    "1604379811" => "Changed transaction_date data type from datetime to date",
    "1604465893" => "Added createUser.php page to permissions and roles_permission table",
    "1604897377" => "Added createDistributor.php page to permissions and roles_permission table",
    "1605147924" => "Set some xun_coins' is_payment_gateway value to 0",
    "1605239029" => "Add reference column to xun_crypto_apikey",
    "1605771283" => "Add landingPageListing.php to permissions and role_permission",
    "1605837544" => "Add editLandingPage.php to permissions and role_permission",
    
    /*-- by ong --*/
    "1602002567" => "Add new column email in xun_user_verification",
    "1602003251" => "Add no-reply email setting in provider table",
    "1602041483" => "Add new column company_name, company_address, support_email in site table",
    "1606113057" => "Add column gw_type to table xun_payment_gateway_payment_transaction, xun_payment_gateway_invoice_detail, xun_crypto_history, and xun_payment_gateway_invoice_transaction. Add column transaction_hash to table xun_payment_gateway_invoice_transaction",
    "1606118169" => "Add column invoice_detail_id to xun_payment_gateway_invoice_transaction table",
    "1606193588" => "SET gw_type to PG for all the record in xun_payment_gateway_invoice_transaction, xun_payment_gateway_payment_transaction, xun_payment_gateway_invoice_detail and xun_crypto_history",
    "1610688629" => "Add is_buy_sell column in xun_coins table",
    "1610690408" => "Create buy sell table",
    "1611658380" => "Create crypto_callback_queue table",

    /*-- by Gregory --*/
    "1601982900" => "add withdrawalHistory.php to permission table",
    "1602502592" => "update withdrawalhistory.php permission for superadmin",
    "1602770679" => "create table for campaign module",
    "1602859426" => "create indexes to short_url and short_url_details",
    "1603162552" => "add permissions for campaign module",
    "1604493074" => "correct sub menu for createReseller and change naming for distributor listing on sidebar",
    "1604900174" => "add permissions for daily sales, top agent, top reseller",
    "1605760394" => "add account_type to xun_business_account",
    "1605767026" => "add upgraded_date column to xun_business_account",

    /*-- by Law --*/
    "1603078952" => "add createCampaign.php to permission table",
    "1605005947" => "add commissionListing on sidebar for Reseller",
    "1605006301" => "xun_marketer_commission_transaction convert to utf8_general from unicode",
    "1605070869" => "add path for commission module, withdrawal, history",
    "1605541407" => "add landing page at the side bar, with create landing page as sub menu",
    "1606122457" => "add default coin column into xun_coins",
    
    /*-- by Nelson --*/
    "1606442187" => "Create table xun_exchange_order",
    "1606444315" => "Alter table xun_crypto_history, added exchange_order_id, order_processed with indexing",
    "1606450723" => "Alter table xun_crypto_history, added miner_fee_exchange_rate",
    "1606814197" => "Alter table xun_crypto_history, added service_charge_transaction_id",
    "1609321696" => "Add escrowInternalAddress to system_settings",
    "1609379049" => "Create table xun_escrow_table",
    "1609379323" => "Create table xun_escrow_chat",    
    "1609556400" => "Alter table xun_payment_gateway_send_fund, add column escrow",
    "1609742050" => "Alter table xun_payment_gateway_fund_in, add column status",
    "1609742949" => "Alter table xun_payment_gateway_fund_in, add escrow_id",
    "1609772152" => "Alter table xun_payment_gateway_withdrawal, add escrow_id",
    "1610681059" => "Create table graph_data_1m, graph_data_5m, graph_data_1h, graph_data_1d, graph_data_1w, graph_data_1mt",    

    /*-- by YF --*/
    "1609842422" => "Create table buynsell_table",
    "1609926173" => "Insert buy sell advestiment setting",
    "1612412415" => "Add sms123Email2way to system_settings",
    "1612414330" => "Add sms1232wayApiKey API key to system_settings",
    "1612421707" => "Add sms123Email2way URL key to system_settings",
    "1616589296" => "Insert new user limit timestamp setting",
    "1619675014" => "Add new currency at xun_marketplace_currencies",
    "1619679710" => "update IDR image at xun_marketplace_currencies",
    "1620032573" => "Add new supported currency for Xanpool at provider_setting",
    "1620032680" => "Add new supported currency for Simplex at provider_setting",
    "1620723076" => "Remove record at provider_setting when name = 'supportedCurrencies'",
    "1620723168" => "Add new supported currency for Xanpool at provider_setting (use subquery)",
    "1620723463" => "Add new supported currency for Simplex at provider_setting (use subquery)",

    /*-- by joe --*/
    "1613444826" => "create index for xun_crypto_history , xun_payment_gateway_fund_in",


    /*-- by wentin --*/
    "1621399393" => "add column to xun_payment_gateway_fund_in, column name = transaction_target",
    "1622521967" => "strtolower on the xun_payment_gateway_fund_in, column name = transaction_target",
    "1625195768" => "add buy and sell crypto => permissions table",
    "1626404201" => "add provider markup percentage => provider_setting table",
    "1626944349" => "add autoselling column => xun_crypto_payment_transaction",

    /*== by david --*/
    "1627650639" => "Add transaction_transfer_id column into xun_crypto_payment_transaction table",
    
    "1627621075" => "Add new coin Livepeer",
    "1628042039" => "Add developer_activity_log table",
    "1628172930" => "Add is_direct, direct_detail, and payment_channel column to xun_crypto_payment_transaction",
    "1628231181" => "Add markdownPercentage for xanpool and simplex provider in provider_setting table",
    "1628820106" => "Insert defaultCurrency setting for simplex and xanpool into provider_setting table",
    "1630654528" => "Insert minAmount and maxAmount for filecoin into provider_setting table",

    /*== by Soh --*/
    "1629276395" => "add aax provider to provider table",
    "1629276399" => "Change provider binance type from exchange to exchange_swap",
    "1629276400" => "add provider setting of aax in provider_setting",
    "1629947481" => "add provider paymentGateway",
    "1629947482" => "add new column swap_provider_id",
    "1629947483" => "add message code for swap provider",
    "1631611781" => "add provider poloniex",
    "1637910636" => "add switchCurrencyMarkup in system_settings",

    /*== by Yap --*/
    "1629947478" => "Create developer_activity_daily_summary table",
    "1630321873" => "Create xun_user_payments_summary table",
    "1632719255" => "Insert coins2 swap settings",
    "1632731367" => "Add transaction_token into xun_swap_history table",
    "1632796806" => "Add coins2 setting into provider_setting",
    "1632907075" => "Add tron-coins2 credit settings into xun_coins table",
    "1632908796" => "Add tron-coins2 credit settings into xun_marketplace_currencies table",
    "1633787963" => "Add swap_history_id into xun_payment_details table",
    "1634291281" => "Add pg_address into xun_crypto_payment_request table",
    "1634297720" => "Add bypass_buysell_service_charge into xun_business table",
    "1634532722" => "Add fiat_currency_exchange_rate into xun_payment_transaction table",
    "1634612381" => "Add end_user_id into xun_crypto_payment_request table",
    "1636088122" => "Add destination_address into xun_crypto_address table",
    
    /*== by KahFai --*/
    "1637910634" => "Add new row to store default_wallet_type, default_symbol, default_currency_rate into provider_setting table",

    /*== by Keong --*/
    "1661327656" => "Add new column to store the sent out notification respond",
    "1677467761" => "create xun_delegate_transaction",
    
    /*== by TeckLoong --*/
    "1667300443" => "Add new column to store ip",
    "1674032488" => "Add new column to store otp_prefix",
);

ksort($repository);
