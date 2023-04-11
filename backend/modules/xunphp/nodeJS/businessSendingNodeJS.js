const config = require("./config.js").settings;

const xmpp = require('node-xmpp');
const request_helper = require("request");
const uuidv1 = require('uuid/v1');
const xmpp_helper = require('./xmpp.js');

const { spawn } = require('child_process')
var php_monitoring_path = "/var/www/xunMonitoring/backend/server_xmpp_record.php";
var connectionPool = new Object();
var businessSendingQueue = new Object();
var cryptoSendingQueue = [];
var cryptoConnection;
var storySendingQueue = [];
var storyConnection;


var mysql      = require('mysql');
var connection = mysql.createConnection(config.mysql_conf);

connection.connect();

setInterval( pooling,1000,connection);

var idAry = [];

function pooling(connection) {

	if(idAry.length)return;

	if(businessSendingQueue.length){
		if(Object.keys(businessSendingQueue).length>0){
			console.log("sendingQueue KEY");
			Object.keys(businessSendingQueue).forEach(function(businessID, i){
				checkConnection(businessID);
			});
		}
		return;
	}
		

	connection.query('SELECT * from `xun_business_sending_queue` WHERE `processed` = "0" ORDER BY ID ASC limit 200', function (error, results, fields) {
		if (error) {
			console.log("error = "+error);
		}
		var businessIDAry = {};

		results.forEach(function(data, i){
			var jsonData = JSON.parse(data.data);
			

			if(data.message_type == "business" || data.message_type == "business_employee"){

				if (typeof businessSendingQueue[jsonData.business_id] == "undefined") {
					businessSendingQueue[jsonData.business_id] = [];
				}

				if (typeof businessIDAry[jsonData.business_id] == "undefined") {
					businessIDAry[jsonData.business_id] = jsonData.business_id;
				}
			
				businessSendingQueue[jsonData.business_id].push(jsonData);
			}
			else if(data.message_type == "story"){

				storySendingQueue.push(jsonData);

			}
			else{
				cryptoSendingQueue.push(jsonData);

			}
			//businessIDAry.push(jsonData.business_id);
			idAry.push(data.id);
		});

		console.log(Object.keys(businessSendingQueue).length);
		if(Object.keys(businessSendingQueue).length > 0){
			Object.keys(businessSendingQueue).forEach(function(businessID, i){
				console.log("businessIDAry");
			console.log(businessID);
				checkConnection(businessID);
			});
		}
		if(Object.keys(storySendingQueue).length > 0){
			storySendingQueue.forEach(function(arrayItem){
				checkStoryConnection(arrayItem.sender);
			})
			

		}
		if(cryptoSendingQueue.length > 0){
			checkCryptoConnection();
			
		}
		/*if(Object.keys(businessIDAry).length>0){
			console.log("businessIDAry");
			console.log(businessIDAry);
			Object.keys(businessIDAry).forEach(function(businessID, i){
				checkConnection(businessID);
			});
		}*/
		//console.log("idAry");
		//console.log(idAry);
		if(idAry.length){
			connection.query('UPDATE `xun_business_sending_queue` SET `processed`=1 WHERE id IN ('+idAry.join(",")+')', function (error, results, fields) {
			});
			idAry = [];
		}

		
	});
}
function checkCryptoConnection(){
	console.log(checkCryptoConnection);
	if (typeof cryptoConnection == "undefined" || cryptoConnection == "") {
		var client =  config.cryptoXmppUser;

		var conn = new xmpp.Client(client);
		
		conn.addListener('online', function(){
			cryptoConnection = conn;
			
			cryptoSending();
			
		});
		conn.addListener("disconnect", function() {
			console.log(Date(Date.now()).toString()+": crypto Disconnected : "+this.jid.user);
			cryptoConnection = "";
		    
		});
		conn.on('offline', function () {
			console.log(Date(Date.now()).toString()+": crypto offline : ");
			cryptoConnection = "";
			//console.log(connectionPool);
		});
		conn.on(Date(Date.now()).toString()+': error', console.error)

	

	}else{
		cryptoSending();
	}
}

function checkConnection(business_id){
	if (typeof connectionPool[business_id] == "undefined") {
		console.log("no connection");
		connectXmpp(business_id);

	}else{
		console.log("existing connection");
		business_sending(business_id);
	}


}

function checkStoryConnection(){

	if (typeof storyConnection == "undefined" || storyConnection == '') {
		console.log("no connection");
		storyConnectXmpp();

	}else{
		console.log("existing connection");
		story_sending();
	}
}

 function connectXmpp(business_id){

 	connection.query('SELECT password from xun_passwd WHERE username="'+business_id+'" AND `server_host`="'+config.jid_host+'"', function (error, results, fields) {
		console.log("connectXmpp  ID : "+business_id+" password : "+results[0].password);
		if (error) console.log(error);

		if(typeof results[0].password === "undefined" ||  results[0].password === null){
			console.log("connectXmpp NULL  ID : "+business_id+" password : "+results[0].password);
			return;
		}
		var password = results[0].password;
		//console.log('The password is 2222: ', results[0].password);

		var client =  {
			jid: business_id+"@"+config.jid_host,
			password: password,
			host: config.host,
			groupname: ""
		}

		var conn = new xmpp.Client(client);

		conn.on('online', function(){
			connectionPool[business_id] = conn;
			var temp = this.jid;
			console.log('online');
			console.log(this.jid.user);
			business_sending(business_id);
			
		});
		conn.addListener("disconnect", function(business_id) {
			console.log("business Disconnected : "+this.jid.user);
			//delete connectionPool[this.jid.user];
			//console.log(connectionPool);
		    
		});
		conn.on('offline', function () {
			console.log("business offline : "+this.jid.user);
			//delete connectionPool[this.jid.user];
			//console.log(connectionPool);
		});
		conn.on('error', console.error)		
	});
 }

 function storyConnectXmpp(){
	connection.query('SELECT password from xun_passwd WHERE username="story" AND `server_host`="'+config.jid_host+'"', function (error, results, fields) {
	   console.log("connectXmpp  ID : story password : "+results[0].password);
	   if (error) console.log(error);

	   if(typeof results[0].password === "undefined" ||  results[0].password === null){
		   console.log("connectXmpp NULL  ID : story + password : "+results[0].password);
		   return;
	   }
	   var password = results[0].password;
	   console.log('The password is story: ', results[0].password);

	   var client =  {
		   jid: "story@"+config.jid_host,
		   password: results[0].password,
		   host: config.host,
		   groupname: ""
	   }

	   var conn = new xmpp.Client(client);


	   conn.addListener('online', function(){
		   storyConnection = conn;
		   var temp = this.jid;
		   console.log(this.jid.user);
		   story_sending();

		   
	   });
	   conn.addListener("disconnect", function(business_id) {
		   console.log("story Disconnected : "+this.jid.user);
		   storyConnection = [];
		   //console.log(connectionPool);
		   
	   });
	   conn.on('offline', function () {
		   console.log("story offline : "+this.jid.user);
		   storyConnection = [];
		   //console.log(connectionPool);
	   });
	   conn.on('error', console.error)		
   });
}

function cryptoSending(){
	
	cryptoSendingQueue.forEach(function(msgData, i){
		//console.log("cryptoSending = ");
		//console.log(msgData);

		if(msgData.crypto_callback == "wallet"){
			var callback_attributes = {"account_address":msgData.account_address,
								"amount":msgData.amount,
								"wallet_type":msgData.wallet_type,
								"recipient":msgData.recipient,
								"fee":msgData.fee,
								"transaction_hash":msgData.transaction_hash,
								"reference_address": msgData.reference_address,
								"confirmation":msgData.confirmation,
								"exchange_rate":msgData.exchange_rate,
								"status":msgData.status,
								"xun_sender":msgData.xun_sender,
								"xun_recipient":msgData.xun_recipient,
								"id":msgData.id,
								"target":msgData.target,
								"type":msgData.type,
								"time":msgData.time,
								"timestamp":msgData.timestamp,
								"sender_type": msgData.sender_type,
								"sender_name": msgData.sender_name,
								"recipient_type": msgData.recipient_type,
								"recipient_name": msgData.recipient_name,
								"recipient_jid": msgData.recipient_jid,
								"recipient_employee_name": msgData.recipient_employee_name,
								"tag": msgData.tag,
								"chatroom_jid": msgData.chatroom_jid,
								"is_contract": msgData.is_contract,
								"contract_name": msgData.contract_name,
								"contract_reference_id": msgData.contract_reference_id,
								"contract_status": msgData.contract_status,
								"contract_address": msgData.contract_address,
								"escrow_address": msgData.escrow_address,
								"escrow_nounce": msgData.escrow_nounce,
								"nounce": msgData.nounce,
								"address_type": msgData.address_type
								};
		}else{
			var callback_attributes = {"account_address":msgData.account_address,
								"address":msgData.address,
								"amount":msgData.amount,
								"wallet_type":msgData.wallet_type,
								"amount_receive": msgData.amount_receive,
								"amount_receive_unit": msgData.amount_receive_unit,
								"service_charge": msgData.service_charge,
								"service_charge_unit": msgData.service_charge_unit,
								"recipient":msgData.recipient,
								"transaction_id":msgData.transaction_id,
								"exchange_rate":msgData.exchange_rate,
								"status":msgData.status,
								"xun_sender":msgData.xun_sender,
								"xun_recipient":msgData.xun_recipient,
								"reference_id":msgData.reference_id,
								"target":msgData.target,
								"type":msgData.type,
								"timestamp":msgData.timestamp,
								"time":msgData.time,
								"sender_type": msgData.sender_type,
								"sender_name": msgData.sender_name,
								"recipient_type": msgData.recipient_type,
								"recipient_name": msgData.recipient_name,
								"recipient_jid": msgData.recipient_jid,
								"tag": msgData.tag,
								"chatroom_jid": msgData.chatroom_jid
								}
		}

		var message_elem = new xmpp.Element('message', {id:uuidv1(),from: "crypto@"+config.jid_host, to: "crypto."+config.jid_host,type: 'chat'})
							.c(msgData.crypto_callback, callback_attributes)
							.up()
							.c('recipient')
							.t(msgData.target_username)
							.up();

		//console.log("message_elem = ");
		console.log(message_elem.toString());
		//console.log(Date(Date.now()).toString() );
		//console.log(message_elem.toString());
		cryptoConnection.send(message_elem);
	});
	cryptoSendingQueue = [];
}



function business_sending(business_id){
	console.log("business_sending = "+businessSendingQueue[business_id]);

	var ping_server_iq = new xmpp.Element('iq', { id:uuidv1(), from:business_id+"@"+config.jid_host, to: config.jid_host,type: 'get'})
	.c('ping', {'xmlns':'urn:xmpp:ping'});

	connectionPool[business_id].send(ping_server_iq);

	var pingResponse = 0;
	connectionPool[business_id].on("stanza", function(ping_server_iq){
		pingResponse = 1;
	});

	setTimeout(() => { 
		console.log('pingResponse' + pingResponse);

		if(pingResponse == 1){
			if(businessSendingQueue[business_id] != undefined){
				businessSendingQueue[business_id].forEach(function(msgData, i){
					if (typeof msgData.mobile_list != "undefined") {
						
						var mobile_list = msgData.mobile_list;
						mobile_list.forEach(function(mobile, z){
						var message_elem = new xmpp.Element('message', {id:uuidv1(),from: business_id+"@"+config.jid_host, to: mobile+"@"+config.jid_host,type: 'chat'})
						.c('body')
						.t(msgData.message)
						.up()
						.c('tag')
						.t(msgData.tag)
						.up()
						.c('subject')
						.t('business#$'+msgData.tag)
						.up()
						.c('hidden-body')
						.t(msgData.hidden_message)
						.up()
						.c('encrypt')
						.t('false')
						.up();
		
						console.log("business_sending = ");
						//console.log(Date(Date.now()).toString() );
						//console.log(message_elem.toString());
					
						connectionPool[business_id].send(message_elem);
						});
					}else{
						var recipients = msgData.recipients;
						recipients.forEach(function(data, z){
							var message_elem = new xmpp.Element('message', {id:uuidv1(),from: business_id+"@"+config.jid_host, to: data.username+"@"+config.jid_host,type: 'chat'})
							.c('event', {'xmlns':'http://jabber.org/protocol/pubsub#event'})
							.c('items', {'node':'urn:xmpp:xun:business:employee:message'})
							.c('message',{id:uuidv1(),from: business_id+"@"+config.jid_host, to: data.username+"@"+config.jid_host,type: 'chat'})
							.c('body').t(msgData.message).up()
							.c('tag').t(msgData.tag).up()
							.c('subject').t('business#$'+msgData.tag).up()
							.c('hidden-body').t(msgData.hidden_message).up()
							.c('sender_jid').t(business_id+"@"+config.jid_host).up()
							.c('recipient_jid').t(data.recipient_jid);
			
							console.log("business_sending = ");
							console.log(Date(Date.now()).toString() );
							console.log(message_elem.toString());
							connectionPool[business_id].send(message_elem);
						});
					}
					
				});
				delete businessSendingQueue[business_id];
			}
		}
		else{
			console.log('connectionPool' + JSON.stringify(connectionPool[business_id], null, 2));
			//delete connectionPool[business_id];
			connectXmpp(business_id);
		}
		
	
	 }, 500);
	
}

function story_sending(){

	storySendingQueue.forEach(function(msgData, i){
		if (typeof msgData.mobile_list != "undefined") {

			var mobile_list = msgData.mobile_list;
			mobile_list.forEach(function(mobile, z){
				var message_elem = new xmpp.Element('message', {id:uuidv1(),from: "story"+"@"+config.jid_host, to: mobile+"@"+config.jid_host,type: 'chat'})
				.c('body')
				.t(msgData.message)
				.up()
				.c('tag')
				.t(msgData.tag)
				.up()
				.c('subject')
				.t('business#$'+msgData.tag)
				.up()
				.c('hidden-body')
				.t(msgData.hidden_message)
				.up()
				.c('encrypt')
				.t('false')
				.up();

				console.log("story_sending = ");
				//console.log(Date(Date.now()).toString() );
				//console.log(message_elem.toString());
				storyConnection.send(message_elem);
			});
		}
		else{

			console.log('story sending');
			var recipients = msgData.recipients;

			recipients.forEach(function(data,z){
				var random_number = Math.floor(Math.random()*10000000000000000);
				var message_elem = new xmpp.Element('message', {id:uuidv1(),from: "story@"+config.jid_host, to: data.recipient_username+"@"+config.jid_host ,type: 'chat'})
				.c('event', {'xmlns':'http://jabber.org/protocol/pubsub#event'})
				.c('items', {'node':'urn:xmpp:xun:story:nodes:events'})
				.c('item', {'id': random_number})
				.c('story',{'xmlns':'urn:xmpp:xun:story'})
				.c("story_id").t(msgData.story_id).up()
				.c("type").t(msgData.notification_type).up()
				.c("user_id").t(msgData.user_id).up()
				.c("username").t(msgData.username).up()
				.c("user_type").t(msgData.user_type).up()
				.c("nickname").t(msgData.nickname).up()
				.c("value").t(msgData.value).up()
				.c("currency_name").t(msgData.currency_name).up()
				.c("story_username").t(msgData.story_username).up()
				.c("message").t(msgData.message).up()
				.c('recipient').t(data.recipient_jid).up();
				storyConnection.send(message_elem);
			});
			
		}

	});
	storySendingQueue = [];
	
	
}