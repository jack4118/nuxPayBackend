const config = require("./config.js").settings;

const xmpp = require('node-xmpp');
const request = require("request");
const uuidv1 = require('uuid/v1');

const util = require("util");
// const conn = new xmpp.Client(conn.options);

var v1 = "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";

var v2 = "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";

var v3 = "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";

function t() {
	for (i = 1; i <= 5; ++i) {
		setDelay(i);
	}
}

function setDelay(i) {
	setTimeout(function(){
		console.log(i + " " + new Date());
	}, 500);
}

function t2(){
	var totalMessages = 10;
	var i = 0;

	var interval = setInterval(function () {
		print_date(i);
		i++
		if (i >= totalMessages) {
			clearInterval(interval);
		}
	}, 500);


}

function print_stanza(stanza){
	// console.log(stanza);
	console.log(new Date());
  	// console.log(stanza);
  	// console.log(stanza.children);
  	console.log(stanza.toString());
  	console.log('\n');
  }

  function enable_sm(conn){
	// <enable xmlns='urn:xmpp:sm:3' resume=\"true\" max=\"100/>

	console.log("testing 3");
	var elem = new xmpp.Element('enable', {'xmlns': 'urn:xmpp:sm:3', 'resume': 'true', 'max': '1000'});

	console.log(elem.toString());
	conn.send(elem);
}

function disable_sm(conn){
	// <enable xmlns='urn:xmpp:sm:3' resume=\"true\" max=\"100/>

	console.log("testing 3");
	var elem = new xmpp.Element('enable', {'xmlns': 'urn:xmpp:sm:3', 'resume': 'false', 'max': '1000'});

	console.log(elem.toString());
	conn.send(elem);
}

function resume_conn(conn, resume_id){
// <resume xmlns=\"urn:xmpp:sm:3\" previd=\"g2gCbQAAABkxNDk4NTYwODUxMzE5NDgyNDIzNzQxNTExaANiAAAF8mIABhoOYgAAI2U=\" h=\"4\"/>

console.log("testing 3");
var elem = new xmpp.Element('resume', {'xmlns': 'urn:xmpp:sm:3', 'previd': resume_id, h:'0'});

console.log(elem.toString());
conn.send(elem);
}


// conn.socket.setTimeout(0);
// conn.socket.setKeepAlive(true, 10000);

// console.log(conn);
function set_status_message(conn, status_message){
	console.log("setting presence");
	var presence_elem = new xmpp.Element('presence', {'type':'available', 'xml:lang':'ch'}).c('show').t('chat').up().c('status').t('Hi I am here <message/> stanzas').up().c('lang').t('ch');
	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);

	console.log(presence_elem.toString());
	conn.send(presence_elem);
}

function send_message(conn, to, message){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: to + '@'+conn.options.host, type: 'chat', id: uuidv1()})
	.c('body').t(message).up()
	// .c('url').t('this is a test').up()
	.c('type').t('text').up()
	.c('request', {xmlns: 'urn:xmpp:receipts'});
	
	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	// var r = new xmpp.Element('r', {xmlns: 'urn:xmpp:sm:3'});
	console.log("sending message...");
	// console.log(message_elem);
	conn.send(message_elem);	
	// conn.send(r);
}

function send_message_to_livechat(conn, body, business_id, tag){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: 'livechat.'+conn.options.host, type: 'groupchat', id: uuidv1()})
	.c('body').t(body).up()
	.c('type').t('text').up()
	.c('livechat').c('business', {id: business_id}).c('tag').t(tag);

	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	// var r = new xmpp.Element('r', {xmlns: 'urn:xmpp:sm:3'});
	console.log("sending message...");
	console.log(message_elem.toString());
	conn.send(message_elem);	
	// conn.send(r);
}

function send_chat_message_to_livechat(conn, chatroom_id, body){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: chatroom_id + '@livechat.'+conn.options.host, type: 'groupchat', id: uuidv1()})
	.c('body').t(body);

	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	// var r = new xmpp.Element('r', {xmlns: 'urn:xmpp:sm:3'});
	console.log("sending message...");
	console.log(message_elem);
	conn.send(message_elem);	
	// conn.send(r);
}

function send_chat_message_media_to_livechat(conn, chatroom_id, body){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: chatroom_id + '@livechat.'+conn.options.host, type: 'groupchat', id: uuidv1()})
	.c('body').t(body).up()
	.c('url').t("https://s3-ap-southeast-1.amazonaws.com/com.ttwoweb.media/601159161011/1529981809726477533/VOC_5C0A6EF4-4829-4114-BA8C-9CA3DF06781D.m4a").up()
	.c('md5').t("e5f81056dbc8c908c5e9fc5d70b3be8d").up()
	.c('encrypted_md5').c('type').t('audio').up()
	.c('filesize').t('66666');


		// <active xmlns="http://jabber.org/protocol/chatstates"/><url>https://s3-ap-southeast-1.amazonaws.com/com.ttwoweb.media/601159161011/1529981809726477533/VOC_5C0A6EF4-4829-4114-BA8C-9CA3DF06781D.m4a</url><md5>e5f81056dbc8c908c5e9fc5d70b3be8d</md5><encrypted_md5/><type>audio</type><filesize>30017</filesize><encrypt>false</encrypt><body>caption:Voice Message (0:03)</body>

	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	// var r = new xmpp.Element('r', {xmlns: 'urn:xmpp:sm:3'});
	console.log("sending message...");
	console.log(message_elem);
	conn.send(message_elem);	
	// conn.send(r);
}

function send_to_business(conn, business_id, message, tag){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: business_id + '@' + conn.options.host, type: 'chat', id: uuidv1()})
	.c('body').t(message).up()
	.c('tag').t(tag).up()
	.c('subject').t('thisisasubject').up()
	.c('type').t('text').up()
	.c('request').t('something');

	console.log("sending: ");
	console.log(message_elem);
	conn.send(message_elem);
}

function send_to_business_domain(conn, business_id, domain, message, tag){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: business_id + '@' + domain, type: 'chat', id: uuidv1()})
	.c('body').t(message).up()
	.c('tag').t(tag).up()
	.c('subject').t('thisisasubject').up()
	.c('type').t('text').up()
	.c('request').t('something');

	console.log("sending: ");
	// console.log(message_elem);
	conn.send(message_elem);
}

function send_to_chatstate_business(conn, business_id, message, tag){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: business_id + '@' + conn.options.host, type: 'chat', id: uuidv1()})
	.c('composing', {xmlns: 'http://jabber.org/protocol/chatstates'}).up()
	.c('tag').t(tag).up()
	.c('request').t('something');

	console.log("sending: ");
	console.log(message_elem);
	conn.send(message_elem);
}

function send_headline_message(user){
	var message_elem = new xmpp.Element('message', {to: user + '@'+conn.options.host, type: 'headline', id: uuidv1()})
	.c('prompt').t('Xun would like to send you a message.')
	.up()
	.c('subject').t('New business message')
	.up()
	.c('store', {xmlns: 'urn:xmpp:hints'});

	console.log("sending message...");
	console.log(message_elem.toString());
	conn.send(message_elem);	
	// conn.send(r);
}

function send_business_message(conn, to, body, tag){
	console.log("conn.options.host " + conn.options);
	var message_elem = new xmpp.Element('message', {to: to + '@'+conn.options.host, id:uuidv1()})
	.c('body').t(body).up()
	.c('subject').t('business#$'+tag);
	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	console.log("sending message...");
	console.log(message_elem.toString());
	conn.send(message_elem);	
}


function send_delivery_receipt(conn, stanza){
	// <message type="chat" to="c919978e-79de-11e8-820a-06f8d367d97c@livechat.dev.xun.global" lang="en" xmlns="jabber:client"><received xmlns="urn:xmpp:receipts" id="xun-1c1ff1bd-12e1-4989-9ea0-75a07aabbc74" type="groupchat"/><tag xmlns="tag">Support</tag><business xmlns="business" id="15103"/></message>
	// <message xml:lang="en" to="+60124466833@dev.xun.global/1079400482694060441736210" from="+60124466899@dev.xun.global/1462765151836910182536282" id="bzNHw-2040" xmlns:stream="http://etherx.jabber.org/streams"><received xmlns="urn:xmpp:receipts" id="groupmsg9a999e42-a1ed-11e8-a1a2-5163f55ce54b"/></message>

	// console.log("stanza");
	// console.log(stanza);
	var stanzaType = stanza.name;
	if (stanzaType == 'message') {
		var child = stanza.children;
		if(child[0] && child[0].name && child[0].name != 'received'){
			var from = stanza.attrs.from;
			var fromArr = from.split("@");
			var fromUser = fromArr[0];
			var jidUser = conn.jid.user;
			if(fromUser != jidUser){
		// console.log("to::");
		// console.log(fromUser);
		// console.log('from:: ');
			// console.log(jidUser);
				var message_id = stanza.attrs.id;
				var message_type = stanza.attrs.type;
				var message_elem = new xmpp.Element('message', {to: from})
				.c('received', {xmlns: 'urn:xmpp:receipts', id: message_id, type: message_type});

				console.log("sending message...");
				console.log(message_elem.toString());
				conn.send(message_elem);	
			}
		}
	}
}

// <message to='+60124466833@dev.xun.global' from='15122@dev.xun.global' type='chat' id='15122-001-9e4a3506-6d25-11e8-971f-06f8d367d97c' xmlns='jabber:client'><encrypt>false</encrypt><body xml:lang='en'>bytsTe+lkaRYmRTPRZPuPFyHi2zlvqYlDseGXF8WnNOqbQSLcLEeZizXz5wSInTz3sTXR/8y
// Z74OHvCqSde2gJN/EJ3zcoAKFMWgu6jbiVE8JDQ6lB3A0BzqZcWnSCsB6G9gRX+0J3b8yAgF
// F40N7yB5oth/cpq8QannHbwCwlTqKNRmZE7VgcvsAeLmuCQPU6t0ztd50kay34Y77UHGzUiw
// J3cQQ1D6R9ScE5ubCpU4o0zG5/GnXwQ1GmJm/CmqIkU+yNgDzegn19dPpkkJW+4o8wLbsYm8
// VPfCqiqU1W8Rsm82yVvHWOaIvMwzhljjKAf8EdwH+glw8SZZaEoeqQ==</body><subject xml:lang='en'>business#$Keyboard</subject></message>

function send_message_to_self(from){
	var message_elem = new xmpp.Element('message', {from: from + "@" + conn.options.host, to: from + '@'+conn.options.host}).c('body').t('Helloooooooo').up().c('url').t('this is a test').up().c('type').t('image');
	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	console.log("sending message...");
	console.log(message_elem.toString());
	conn.send(message_elem);	
}


function send_composing_message(to){
	// <message type=\"groupchat\" to=\"c3bd34d4-2d86-11e8-8c90-06f8d367d97c@conference.dev.xun.global\"><composing xmlns=\"http://jabber.org/protocol/chatstates\"/></message>
	var message_elem = new xmpp.Element('message', {to: to + '@'+conn.options.host, type: 'chat'})
	.c('composing', {xmlns: 'http://jabber.org/protocol/chatstates'});

	console.log('sending... ' + message_elem.toString());
	conn.send(message_elem);	
}
// var toJID = 'user3@localhost';
// var stanza = new xmpp.Element('message', {to: toJID, type:'chat', id: 'test_message'}).c('body').t(msg);

// conn.send(stanza.tree());

// cache.admin.addListener('online',function() {
//             cache.admin.send(new xmpp.Element('presence',{type:'chat'}).c('show').c('status').t('mine status'));
//             cache.admin.send(new xmpp.Element('iq',{type:'get',id:'reg1'}).c('query',{xmlns:'jabber:iq:register'}));            
//         })

function iq_function(){
	var iq = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'testing_iq', 'xml:lang':'ch'}).c('query', {'xmlns':'jabber:iq:language'})
	.c('item', {'language':'ch', 'name':'test_iq_name', 'subscription': 'none'}
		);
	conn.send(iq);
	console.log(iq);
	console.log("#############");
	console.log(iq.toString());
}

function vcard_iq_function(){
//<iq type='set' id='vcard-update'
//   from='userjid@example.com'
//     to='userjid@example.com'>
//   <query xmlns='xun:vcard:update'/>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'set', id: 'vcard-update', 'xml:lang':'en'}).c('query', {'xmlns':'xun:vcard:update'});
conn.send(iq);
console.log(iq);
console.log("#############vcard-update-js");
console.log(iq.toString());
}

// var iq = $iq({type: 'get', to: 'some host'}).c('query', {xmlns: 'jabber:iq:conversations'}); connection.sendIQ(iq);
function service_discovery(){
// <iq from='romeo@montague.tld/garden'
//     id='step_01'
//     to='montague.tld'
//     type='get'>
//   <query xmlns='http://jabber.org/protocol/disco#items'/>

// </iq>
// 	<iq from='juliet@capulet.com/chamber' to='capulet.com' type='get' id='disco1'>
//   <query xmlns='http://jabber.org/protocol/disco#info'/>
// </iq>
var iqItems = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'step_01', to:conn.options.host, 'xml:lang':'en'}).c('query', {'xmlns':'http://jabber.org/protocol/disco#items'});
conn.send(iqItems);
console.log(iqItems);

var iqInfo = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'disco01', to:conn.options.host, 'xml:lang':'en'}).c('query', {'xmlns':'http://jabber.org/protocol/disco#info'});
conn.send(iqInfo);
console.log(iqInfo);

}

function upload_service() {
// <iq from='romeo@montague.tld/garden'
//     id='step_02'
//     to='upload.montague.tld'
//     type='get'>
//   <query xmlns='http://jabber.org/protocol/disco#info'/>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'step_02', to:'upload.'+conn.options.host, 'xml:lang':'en'}).c('query', {'xmlns':'http://jabber.org/protocol/disco#info'});
conn.send(iq);
console.log(iq);
}

function request_slot(){
// <iq from='romeo@montague.tld/garden'
//     id='step_03'
//     to='upload.montague.tld'
//     type='get'>
//   <request xmlns='urn:xmpp:http:upload:0'
//     filename='my-juliet.jpg'
//     size='23456'
//     content-type='image/jpeg' />
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'step_03', to:'upload.'+conn.options.host, 'xml:lang':'en'}).c('request', {'xmlns':'urn:xmpp:http:upload'})
.c('filename').t('testpic.jpg').up()
.c('size').t('123313').up()
.c('content-type').t('image/jpeg');
conn.send(iq);
console.log("\n");
console.log(iq);
console.log("\n");
	// upload_slot_0,<<"https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/UqDc9Z5deB96yRmE43rQzp0bSildt0JzJvcJpXTa/test_filename.jpg">>
	// "https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/v3jn6EXW9e18cWC6LMpwsWfaaDyJaEuFyZPE1ziG/test_filename.jpg
}
function request_slot_png(){
// <iq from='romeo@montague.tld/garden'
//     id='step_03'
//     to='upload.montague.tld'
//     type='get'>
//   <request xmlns='urn:xmpp:http:upload:0'
//     filename='my-juliet.jpg'
//     size='23456'
//     content-type='image/jpeg' />
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'step_03', to:'upload.'+conn.options.host, 'xml:lang':'en'}).c('request', {'xmlns':'urn:xmpp:http:upload'})
.c('filename').t('mario.png').up()
.c('size').t('1764904').up()
.c('content-type').t('image/png');
conn.send(iq);
console.log("\n");
console.log(iq);
console.log("\n");
	// upload_slot_0,<<"https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/UqDc9Z5deB96yRmE43rQzp0bSildt0JzJvcJpXTa/test_filename.jpg">>
	// "https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/v3jn6EXW9e18cWC6LMpwsWfaaDyJaEuFyZPE1ziG/test_filename.jpg
}

function request_slot_localhost(){
// <iq from='romeo@montague.tld/garden'
//     id='step_03'
//     to='upload.montague.tld'
//     type='get'>
//   <request xmlns='urn:xmpp:http:upload:0'
//     filename='my-juliet.jpg'
//     size='23456'
//     content-type='image/jpeg' />
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'get', id: 'step_03', to:'upload.'+conn.options.host, 'xml:lang':'en'}).c('request', {'xmlns':'urn:xmpp:http:upload:0'})
.c('filename').t('testpic.jpg').up()
.c('size').t('123313').up()
.c('content-type').t('image/jpeg');
conn.send(iq);
console.log("\n");
console.log(iq);
console.log("\n");
	// upload_slot_0,<<"https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/UqDc9Z5deB96yRmE43rQzp0bSildt0JzJvcJpXTa/test_filename.jpg">>
	// "https://localhost:5444/6c9aad1e26deaca1cf71ff6ff358f8fbd16c48cf/v3jn6EXW9e18cWC6LMpwsWfaaDyJaEuFyZPE1ziG/test_filename.jpg
}


function mucsub_iq(){
	var iq = new xmpp.Element('iq', {from: conn.jid, to: 'testroom@conference.localhost', type:'set', id: 'mucsubE6E10350-76CF-40C6-B91B-1EA08C332FC7'})
	.c('subscribe', {'xmlns':'urn:xmpp:mucsub:0', 'nick':'testroom'})
	.c('event', {'node':'urn:xmpp:mucsub:nodes:messages'})
	.c('event', {'node':'urn:xmpp:mucsub:nodes:affiliations'}).up()
	.c('event', {'node':'urn:xmpp:mucsub:nodes:subject'}).up()
	.c('event', {'node':'urn:xmpp:mucsub:nodes:config'});
	conn.send(iq);
	console.log(iq);
}

function send_presence(){
	var presence_elem = new xmpp.Element('presence', {'from': conn.jid, 'to': 'echo.localhost', 'type':'available', 'xml:lang':'ch'}).c('show').t('chat').up().c('status').t('Testing').up().c('lang').t('ch');
	conn.send(presence_elem);
}

function get_block_list(){
// <iq type='get' id='blocklist1'>
//   <blocklist xmlns='urn:xmpp:blocking'/>
// </iq>
var iq =  new xmpp.Element('iq', {from: conn.jid, type: 'get', id: 'blocklist001'})
.c('blocklist', {
	'xmlns': 'urn:xmpp:blocking'
});
conn.send(iq);
console.log(iq);
}

function block_user(){
// <iq from='juliet@capulet.com/chamber' type='set' id='block1'>
//   <block xmlns='urn:xmpp:blocking'>
//     <item jid='romeo@montague.net'/>
//   </block>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'set', id: 'block_user001', 'xml:lang':'en'})
.c('block', {'xmlns':'urn:xmpp:blocking'})
.c('item', {'jid': '+60124466833@'+conn.options.host})
.up()
.c('item', {'jid': '+601155090561@'+conn.options.host});
conn.send(iq);
console.log(iq);
}

function unblock_user(){
// <iq type='set' id='unblock1'>
//   <unblock xmlns='urn:xmpp:blocking'>
//     <item jid='romeo@montague.net'/>
//   </unblock>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, type:'set', id: 'unblock_user001', 'xml:lang':'en'}).
c('unblock', {'xmlns':'urn:xmpp:blocking'}).
c('item', {'jid': '+601155090561@'+conn.options.host})
.up()
.c('item', {'jid': '+60194222222@'+conn.options.host})
.up()
.c('item', {'jid': '+60194110561@'+conn.options.host})
;
conn.send(iq);
console.log(iq);
}

function subscribe_muc_events(){

// <iq from='hag66@shakespeare.example'
//     to='coven@muc.shakespeare.example'
//     type='set'
//     id='E6E10350-76CF-40C6-B91B-1EA08C332FC7'>
//   <subscribe xmlns='urn:xmpp:mucsub:0'
//              nick='mynick'
//              password='roompassword'>
//     <event node='urn:xmpp:mucsub:nodes:messages' />
//     <event node='urn:xmpp:mucsub:nodes:affiliations' />
//     <event node='urn:xmpp:mucsub:nodes:subject' />
//     <event node='urn:xmpp:mucsub:nodes:config' />
//   </subscribe>
// </iq>

var iq_elem = new xmpp.Element('iq', {from: conn.jid, to:'testmucsub4@conference.localhost', type:'set', id:'test-muc'})
.c('subscribe', {'xmlns':'urn:xmpp:mucsub:0', nick: conn.jid, password: 'roompassword'})
.c('event', {node: 'urn:xmpp:mucsub:nodes:messages'})
.up()
.c('event', {node: 'urn:xmpp:mucsub:nodes:affiliations'})
.up()
.c('event', {node: 'urn:xmpp:mucsub:nodes:subject'})
.up()
.c('event', {node: 'urn:xmpp:mucsub:nodes:presence'})
.up()
.c('event', {node: 'urn:xmpp:mucsub:nodes:config'});

conn.send(iq_elem);
console.log(iq_elem);

}

function discover_mucsub(){
// <iq from='hag66@shakespeare.example/pda'
//     to='coven@muc.shakespeare.example'
//     type='get'
//     id='ik3vs715'>
//   <query xmlns='http://jabber.org/protocol/disco#info'/>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: 'testmucsub4@conference.localhost', type:'get', id:'discover-mucsub'})
.c('query', {xmlns: 'http://jabber.org/protocol/disco#info'});

conn.send(iq);
}

function send_message_to_group(conn, group_id, body){
// <message from="hag66@shakespeare.example"
//          to="coven@muc.shakespeare.example"
//          type="groupchat">
//   <body>Test</body>
// </message>

// <message to='180431b4-a1cc-11e8-b5d9-06f8d367d97c@conference.dev.xun.global' type='groupchat'><paused xmlns='http://jabber.org/protocol/chatstates'/></message><r xmlns='urn:xmpp:sm:3'/><message to='180431b4-a1cc-11e8-b5d9-06f8d367d97c@conference.dev.xun.global' id='60124466844-3fef3e2a-df6c-415c-8561-12cbe9c23fa6' type='groupchat'><body>YER4jwh90QaFzWncIsAB1z/B4sJHfrddl+X0Dsb5+HxMftLjuheBwkhp8Oubaz4apJKceOvVhDVIYH3ijhA33ljwnrljD7HbsmpBIyymnAlrNfg2vcU+54JpADj1ZLZzdphwdlXPli5rMU7Fwi2HhNVzWhDy2fwgQ27lsrC43WZ30glblYzpDvTaPALjwzF5ptyCtg6cSdiOmh4FmfqK3I+Sj8b/tKL+Q36yb/7QW/hMIy6YhxNjjsiRu0gDtANDj+bpw3NZqx4TO0XwiabX++bP44PZRdrcwQgT4wHtpEk/xjjssYE9wZbYVsSw5PIiFn7grjtQSCAlNeCd2n+HJA==</body><type xmlns='type'>text</type><encrypt xmlns='encrypt'>true</encrypt></message>


var message_elem = new xmpp.Element('message', {from: conn.jid, to: group_id+'@conference.'+conn.options.host, type:'groupchat', id: 'groupmsg'+uuidv1()})
.c('body').t(body).up()
.c('type', {xmlns:'type'}).t('text').up()
.c('encrypt', {xmlns:'encrypt'}).t('false');
	// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
	console.log("sending message...");
	console.log(message_elem.toString());
	conn.send(message_elem);	


}

function send_message_with_variable(message, to, host){
	var message_elem = new xmpp.Element('message', {from: conn.jid, to: to+'@'+host, id: uuidv1()}).c('body').t(message);
			// new xmpp.Element('presence', {'type':'available'}).c('show').t('chat').up().c('status').t(status_message);
			console.log("sending message... " + message + " " + new Date());
			conn.send(message_elem);
		}

		function send_message_after_timeout(i) {
			setTimeout(function(){ 
		// send_message_with_variable(i, '+60163082152', 'prod.xun.global');
		// send_message_with_variable(i, '+601155090561', 'prod.xun.global');
		// send_message_with_variable(i, '+60165380190', 'dev.xun.global');
		send_message_with_variable(i, '+60169296101', 'dev.xun.global');
	}, 10000);
			// conn.send(message_elem);	
		}

		function print_date(i) {
			console.log("i " + i + " " + new Date());
		}

		function testTimeout(i){
			setTimeout(function(){
				print_date();
			}, 5000);
		}

		function temp(){
			for (var i = 0; i < 10; i++) {
				print_date(i);
			}
		}
		function send_multi_messages(){
	// 60169057629
	var totalMessages = 100;
	for (var i = 0; i < totalMessages ; i++) {
			// setTimeout(function(){ 
				send_message_after_timeout(i);
			// }, 10000);
			// conn.send(message_elem);	
		}
	}

	function change_user_affiliation(Affiliation, Username){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='admin1'
//     to='coven@chat.shakespeare.lit'
//     type='set'>
//   <query xmlns='http://jabber.org/protocol/muc#admin'>
//     <item affiliation='admin'
//           jid='wiccarocks@shakespeare.lit'/>
//   </query>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'change-affiliation'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#admin'})
.c('item', {affiliation: Affiliation, 
	jid:Username+'@'+conn.options.host
			// nick: 'yoyo'
		});

conn.send(iq);
}


function grants_membership(Username){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='member1'
//     to='coven@chat.shakespeare.lit'
//     type='set'>
//   <query xmlns='http://jabber.org/protocol/muc#admin'>
//     <item affiliation='member'
//           jid='hag66@shakespeare.lit'
//           nick='thirdwitch'/>
//   </query>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'grants_membership'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#admin'})
.c('item', {affiliation: 'member', 
	jid: Username + '@'+conn.options.host
});

conn.send(iq);
}

function request_room_configuration(){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='config1'
//     to='coven@chat.shakespeare.lit'
//     type='get'>
//   <query xmlns='http://jabber.org/protocol/muc#owner'/>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'get', id:'request_room_configuration'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#owner'});

conn.send(iq);
}

function submit_room_configuration(){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='create2'
//     to='coven@chat.shakespeare.lit'
//     type='set'>
//   <query xmlns='http://jabber.org/protocol/muc#owner'>
//     <x xmlns='jabber:x:data' type='submit'>
//       <field var='FORM_TYPE'>
//         <value>http://jabber.org/protocol/muc#roomconfig</value>
//       </field>
//       <field var='muc#roomconfig_roomname'>
//         <value>A Dark Cave</value>
//       </field>

var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'submit_room_configuration'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#owner'})
.c('x', {xmlns: 'jabber:x:data', type: 'submit'})
.c('field', {var: 'FORM_TYPE', type: 'hidden'})
.c('value')
.t('http://jabber.org/protocol/muc#roomconfig')
.up().up()
		// .c('field', {var: 'muc#roomconfig_roomname'})
		// .c('value')
		// .t('A Dark Hole');
		.c('field', {var: 'muc#roomconfig_publicroom'})
		.c('value')
		.t(1);

		conn.send(iq);
	}

	function grant_moderator_status(username){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='mod1'
//     to='coven@chat.shakespeare.lit'
//     type='set'>
//   <query xmlns='http://jabber.org/protocol/muc#admin'>
//     <item nick='thirdwitch'
//           role='moderator'/>
//   </query>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'grant_moderator_status'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#admin'})
.c('item', {nick: username, role: 'moderator'});

conn.send(iq);
}


function get_moderator_list(){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='mod3'
//     to='coven@chat.shakespeare.lit'
//     type='get'>
//   <query xmlns='http://jabber.org/protocol/muc#admin'>
//     <item role='moderator'/>
//   </query>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'get', id:'get_moderator_list'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#admin'})
.c('item', {role: 'moderator'});

conn.send(iq);
}

function get_member_list(){
// <iq from='crone1@shakespeare.lit/desktop'
//     id='member3'
//     to='coven@chat.shakespeare.lit'
//     type='get'>
//   <query xmlns='http://jabber.org/protocol/muc#admin'>
//     <item affiliation='member'/>
//   </query>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'get', id:'get_member_list'})
.c('query', {xmlns: 'http://jabber.org/protocol/muc#admin'})
.c('item', {affiliation: 'member'});

conn.send(iq);
}

function get_subscribers(){
// <iq from='hag66@shakespeare.example'
//     to='muc.shakespeare.example'
//     type='get'
//     id='E6E10350-76CF-40C6-B91B-1EA08C332FC7'>
//   <subscriptions xmlns='urn:xmpp:mucsub:0' />
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'get', id:'get_subscribers'})
.c('subscriptions', {xmlns: 'urn:xmpp:mucsub:0'});

conn.send(iq);
}


function unsubscribe_group_member(user){
// <iq from='king@shakespeare.example'
//     to='coven@muc.shakespeare.example'
//     type='set'
//     id='E6E10350-76CF-40C6-B91B-1EA08C332FC7'>
//   <unsubscribe xmlns='urn:xmpp:mucsub:0'
//                jid='hag66@shakespeare.example'/>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'unsubscribe_group_member'})
.c('unsubscribe', {xmlns: 'urn:xmpp:mucsub:0', jid: user+'@'+conn.options.host});

conn.send(iq);
}

function request_subscribers(){
// <iq from='hag66@shakespeare.example'
//     to='coven@muc.shakespeare.example'
//     type='get'
//     id='E6E10350-76CF-40C6-B91B-1EA08C332FC7'>
//   <subscriptions xmlns='urn:xmpp:mucsub:0' />
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'get', id:'request_subscribers'})
.c('subscriptions', {xmlns: 'urn:xmpp:mucsub:0'});

conn.send(iq);
}

function set_room_vcard(conn, nickname, vCard){
// <iq id='set1'
//     type='set'
//     to='test@conference.localhost'>
// <vCard xmlns='vcard-temp'>
//     <PHOTO>
//       <TYPE>image/png</TYPE>
//       <BINVAL>

//       </BINVAL>
//     </PHOTO>
// </vCard>
// </iq>

var iq = new xmpp.Element('iq', {from: conn.options.jid, to: conn.options.groupname + '@conference.' + conn.options.host, type:'set', id:'set_room_vcard'})
.c('vCard', {xmlns: 'vcard-temp'})
.c('NICKNAME')
.t(nickname)
.up()
.c('PHOTO')
.c('TYPE')
.t('image/jpeg')
.up()
.c('BINVAL')
.t(vCard);

conn.send(iq);
}

function set_user_vcard(conn, nickname, vCard){
// <iq id='set1'
//     type='set'
//     to='test@conference.localhost'>
// <vCard xmlns='vcard-temp'>
//     <PHOTO>
//       <TYPE>image/png</TYPE>
//       <BINVAL>

//       </BINVAL>
//     </PHOTO>
// </vCard>
// </iq>

// <iq type='set' id='purple43bb9820'><vCard xmlns='vcard-temp' prodid='-//HandGen//NONSGML vGen v1.0//EN' version='2.0'><FN>aassdsasd</FN><ADR><CTRY>sssss</CTRY>
var iq = new xmpp.Element('iq', {type:'set', id:'set_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'})
.c('NICKNAME')
.t(nickname)
.up()
.c('PHOTO')
.c('TYPE')
.t('image/jpeg')
.up()
.c('BINVAL')
.t(vCard);

conn.send(iq);
}

function set_user_vcard(conn, nickname, vCard){
// <iq id='set1'
//     type='set'
//     to='test@conference.localhost'>
// <vCard xmlns='vcard-temp'>
//     <PHOTO>
//       <TYPE>image/png</TYPE>
//       <BINVAL>

//       </BINVAL>
//     </PHOTO>
// </vCard>
// </iq>

// <iq type='set' id='purple43bb9820'><vCard xmlns='vcard-temp' prodid='-//HandGen//NONSGML vGen v1.0//EN' version='2.0'><FN>aassdsasd</FN><ADR><CTRY>sssss</CTRY>
var iq = new xmpp.Element('iq', {type:'set', id:'set_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'})
.c('NICKNAME')
.t(nickname)
.up()
.c('PHOTO')
.c('TYPE')
.t('image/jpeg')
.up()
.c('BINVAL')
.t(vCard);

conn.send(iq);
}

function set_public_user_vcard(conn, nickname, vCard){
// <iq id='set1'
//     type='set'
//     to='test@conference.localhost'>
// <vCard xmlns='vcard-temp'>
//     <PHOTO>
//       <TYPE>image/png</TYPE>
//       <BINVAL>

//       </BINVAL>
//     </PHOTO>
// </vCard>
// </iq>

// <iq type='set' id='purple43bb9820'><vCard xmlns='vcard-temp' prodid='-//HandGen//NONSGML vGen v1.0//EN' version='2.0'><FN>aassdsasd</FN><ADR><CTRY>sssss</CTRY>
var iq = new xmpp.Element('iq', {type:'set', id:'set_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'})
.c('NICKNAME')
.t(nickname)
.up()
.c('PHOTO')
.c('TYPE')
.t('image/jpeg')
.up()
.c('BINVAL')
.t(vCard);

conn.send(iq);
}


function get_room_vcard(groupname, conn){
// <iq to='test@conference.localhost'
//     id='get1'
//     type='get'>
//   <vCard xmlns='vcard-temp'/>
// </iq>
var iq = new xmpp.Element('iq', {to: groupname + '@conference.' + conn.options.host, type:'get', id:'get_room_vcard'})
.c('vCard', {xmlns: 'vcard-temp'});

conn.send(iq);
}

function get_user_vcard(conn, user){
// <iq to='test@localhost'
//     id='get1'
//     type='get'>
//   <vCard xmlns='vcard-temp'/>
// </iq>
var iq = new xmpp.Element('iq', {to: user+'@' +conn.options.host, type:'get', id:'get_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'});

conn.send(iq);
}

function get_public_user_vcard(conn, user){
// <iq to='test@localhost'
//     id='get1'
//     type='get'>
//   <vCard xmlns='vcard-temp'/>
// </iq>
var iq = new xmpp.Element('iq', {to: user+'@public.' +conn.options.host, type:'get', id:'get_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'});

conn.send(iq);
}

function get_livechat_vcard(conn, user){
// <iq to='test@localhost'
//     id='get1'
//     type='get'>
//   <vCard xmlns='vcard-temp'/>
// </iq>
var iq = new xmpp.Element('iq', {to: user+'@livechat.' +conn.options.host, type:'get', id:'get_user_vcard'})
.c('vCard', {xmlns: 'vcard-temp'});

conn.send(iq);
}

function get_business_vcard(){
// <iq to='test@conference.localhost'
//     id='get1'
//     type='get'>
//   <vCard xmlns='vcard-temp'/>
// </iq>
var iq = new xmpp.Element('iq', {to: '15118@'+conn.options.host, type:'get', id:'get_business_vcard'})
.c('vCard', {xmlns: 'vcard-temp'});

conn.send(iq);
}

function join_room(status_message){
// <presence to='86a6b05c-127e-11e8-ab71-08002779e561@conference.test.com/+777'>
// 	<c xmlns='http://jabber.org/protocol/caps' node='http://pidgin.im/' hash='sha-1' ver='DdnydQG7RGhP9E3k9Sf+b+bF0zo='/>
// 	<x xmlns='http://jabber.org/protocol/muc'/>
// </presence>
var presence_elem = new xmpp.Element('presence', {to: conn.options.groupname + '@conference.' + conn.options.host + '/+0000'})
.c('x', { xmlns: 'http://jabber.org/protocol/muc'});

console.log(presence_elem.toString());
conn.send(presence_elem);
}

function subscribe_presence(conn, username){
// <presence to='86a6b05c-127e-11e8-ab71-08002779e561@conference.test.com/+777'>
// 	<c xmlns='http://jabber.org/protocol/caps' node='http://pidgin.im/' hash='sha-1' ver='DdnydQG7RGhP9E3k9Sf+b+bF0zo='/>
// 	<x xmlns='http://jabber.org/protocol/muc'/>
// </presence>
// <presence from=\"+60169296101@dev.xun.global\" to=\"+60124466833@dev.xun.global\" type=\"subscribe\"/>
var presence_elem = new xmpp.Element('presence', {to: username + '@' + conn.options.host, type: 'subscribe'});


console.log(presence_elem.toString());
conn.send(presence_elem);
}

function response_subscribe_presence(conn, stanza){
	if(stanza.attrs.to != stanza.attrs.from){
		if(stanza.name == 'presence' && stanza.attrs.type && stanza.attrs.type == 'subscribe') {
		// <presence to='contact@example.org' type='subscribed'/>
		console.log(stanza.attrs.type);
			// var presence_elem = new xmpp.Element('presence', {to: stanza.attrs.from, type: 'unsubscribed'});
			// console.log(presence_elem.toString());
			// conn.send(presence_elem);

			var presence_elem2 = new xmpp.Element('presence', {to: stanza.attrs.from, type: 'subscribed'});
			console.log(presence_elem2.toString());
			conn.send(presence_elem2);
			var username = stanza.attrs.from.split('@')[0];
			console.log('#### ' + username);
			subscribe_presence(conn, username);
		}	
	}
}

function reply_message(User){
// <message xml:lang='en' to='+60165380190@dev.xun.global/2330277718360452669125226' from='+60195916731@dev.xun.global/1019383500689058738113031' id='vDu59-46'><url xmlns='url'>123 </url><type xmlns='type'>123 </type><reply xmlns='reply' type='chat' id='NBa7M-55' from=''><body>Testing</body><media>0</media><type>text</type></reply><body>bn</body></message><r xmlns='urn:xmpp:sm:3'/>
var elem = new xmpp.Element('message', {to: User, id:uuidv1()})
.c('reply', {id: uuidv1(), from: User})
.c('body').t('ori_body').up()
.c('media').t('testmedia').up()
.c('type').t('test').up().up()
.c('body').t('new_body')
.up().c('test').t('lalla');


console.log(elem.toString());
conn.send(elem);
}

function delete_message(User){
// <message type="chat" to="+60163082152@dev.xun.global">
//     <replace xmlns="urn:xmpp:message-correct:0" id="B48149F0-908D-445B-BCF0-68F5F90CF491">
//     </replace>
// </message>

var elem = new xmpp.Element('message', {to: User + '@' + conn.options.host})
.c('replace', {xmlns: "urn:xmpp:message-correct:0", id:uuidv1()});

console.log(elem.toString());
conn.send(elem);
}

function request_ack(){
	// <r xmlns="urn:xmpp:sm:3"/>
	var r = new xmpp.Element('r', {xmlns: 'urn:xmpp:sm:3'});

	conn.send(r);
}

function join_mix(){
// <iq type='set'
//     to='hellomix@mix.localhost'
//     id='E6E10350-76CF-40C6-B91B-1EA08C332FC7'>
//   <join xmlns='urn:xmpp:mix:0'>
//     <subscribe node='urn:xmpp:mix:nodes:messages'></subscribe>
//     <subscribe node='urn:xmpp:mix:nodes:presence'></subscribe>
//     <subscribe node='urn:xmpp:mix:nodes:participants'></subscribe>
//     <subscribe node='urn:xmpp:mix:nodes:subject'></subscribe>
//     <subscribe node='urn:xmpp:mix:nodes:config'></subscribe>
//   </join>
// </iq>
var iq = new xmpp.Element('iq', {from: conn.jid, to: conn.options.groupname + '@mix.' + conn.options.host, type:'set', id:'join_mix'})
.c('join', {xmlns: 'urn:xmpp:mix:0'})
.c('subscribe', {node: 'urn:xmpp:mix:nodes:messages'})
.up()
.c('subscribe', {node: 'urn:xmpp:mix:nodes:participants'})
.up()
.c('subscribe', {node: 'urn:xmpp:mix:nodes:subject'})
.up()
.c('subscribe', {node: 'urn:xmpp:mix:nodes:presence'})
.up()
.c('subscribe', {node: 'urn:xmpp:mix:nodes:config'});

conn.send(iq);
}

function enable_cc(conn){
// <iq xmlns='jabber:client'
//     from='romeo@montague.example/garden'
//     id='enable1'
//     type='set'>
//   <enable xmlns='urn:xmpp:carbons:2'/>
// </iq>

var iq = new xmpp.Element('iq', {type:'set', id:'enable_carboncopy'})
.c('enable', {xmlns: 'urn:xmpp:carbons:2'});

conn.send(iq);
}

function disable_cc(conn){
// <iq xmlns='jabber:client'
//     from='romeo@montague.example/garden'
//     id='disable1'
//     type='set'>
//   <disable xmlns='urn:xmpp:carbons:2'/>
// </iq>

var iq = new xmpp.Element('iq', {type:'set', id:'disable_carboncopy'})
.c('disable', {xmlns: 'urn:xmpp:carbons:2'});

conn.send(iq);
}

function get_business_key_pair(conn, business_jid){
	var url = make_get_public_key_url(conn);
	// request
	//   .get('http://google.com/img.png')
	//   .on('response', function(response) {
	//     console.log(response.statusCode) // 200
	//     console.log(response.headers['content-type']) // 'image/png'
	//   })
	//   .pipe(request.put('http://mysite.com/img.png'))

	// console.log("conn.options");
	// console.log(conn.options);
	var options = {
		url: url,
		qs: {
			mobile: conn.options.jid.user,
			jid: business_jid
		},
		headers: 
		{
			'X-XUN-TOKEN': conn.options.password,
		}
	};

	request.get(options, function (error, response, body) {
      // body is the decompressed response body
      console.log(body);
  });	
		// .on('response', function(response){
		// 	console.log(response);
		// });
	}

	function get_encrypted_private_key(conn, jid){
		var url = make_get_encrypted_key_url(conn);
		// https://dev.xun.global:5281/xun/encryption/encrypted_private_key/user/get?mobile=%2b60124466855&chat_room_jid=be17c2b4-49c5-11e8-8fdc-06f8d367d97c@conference.dev.xun.global

		var options = {
			url: url,
			qs: {
				mobile: conn.options.jid.user,
				chat_room_jid: jid
			},
			headers: 
			{
				'X-XUN-TOKEN': conn.options.password,
			}
		};

		request.get(options, function (error, response, body) {
      // body is the decompressed response body
      console.log(body);
  });	
		// .on('response', function(response){
		// 	console.log(response);
		// });
	}

	function process_business_message(conn, stanza){
		if(stanza.name == 'message'){
			var from_jid = stanza.attrs.from;
			get_business_key_pair(conn, from_jid);
			get_encrypted_private_key(conn, from_jid);
		}
	}

	function make_get_public_key_url(conn){
	// http://test.com:5281/xun/encryption/public_key/get?mobile=%2b0000&jid=%2b0000@test.com
	var host = get_host(conn);
	var http = "http";

	if (host != 'test.com') {
		http = "https";
	}

	var url = http + "://"+host+":5281/xun/encryption/public_key/get";
	return url;
}

function make_get_encrypted_key_url(conn){
	// https://dev.xun.global:5281/xun/encryption/encrypted_private_key/user/get?mobile=%2b60124466855&chat_room_jid=be17c2b4-49c5-11e8-8fdc-06f8d367d97c@conference.dev.xun.global
	var host = get_host(conn);
	var http = "http";

	if (host != 'test.com') {
		http = "https";
	}

	var url = http + "://"+host+":5281/xun/encryption/encrypted_private_key/user/get";
	return url;
}

function get_host(conn){
	if (conn && conn.options && conn.options.host){
		return conn.options.host;
	}

	return "";
} 


function livechat_query_iq(conn, room_id){
// <iq type="get" id="livechat_query_iq">
// 	<query xmlns="urn:xmpp:xun:livechat:detail">
// 		<item chat_room_jid="5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.dev.xun.global"/>
// 	</query>
// </iq>
	var iq = new xmpp.Element('iq', {type:'get', id:'livechat_query_iq'})
	.c('query', {xmlns: 'urn:xmpp:xun:livechat:details'})
	.c('item', {chat_room_jid: room_id + '@livechat.' + conn.options.host})
	.up()
	.c('item', {chat_room_jid: room_id + '@livechat.' + conn.options.host});

	conn.send(iq);
}

function livechat_query_iq_multi(conn){
// <iq type="get" id="livechat_query_iq">
// 	<query xmlns="urn:xmpp:xun:livechat:detail">
// 		<item chat_room_jid="5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.dev.xun.global"/>
// 	</query>
// </iq>
	var iq = new xmpp.Element('iq', {type:'get', id:'livechat_query_iq'})
	.c('query', {xmlns: 'urn:xmpp:xun:livechat:details'})
	// .c('item', {chat_room_jid: '25af3942-832e-11e8-8b01-0256e3917544@livechat.' + conn.options.host})
	.c('item', {chat_room_jid: '5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.' + conn.options.host})
	.up()
	// .c('item', {chat_room_jid: '230dee92-9079-11e8-b874-06f8d367d97c@livechat.' + conn.options.host})
	;

	conn.send(iq);
}

function livechat_query_iq_multi_test(conn){
// <iq type="get" id="livechat_query_iq">
// 	<query xmlns="urn:xmpp:xun:livechat:detail">
// 		<item chat_room_jid="5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.dev.xun.global"/>
// 	</query>
// </iq>
	var iq = new xmpp.Element('iq', {type:'get', id:'livechat_query_iq'})
	.c('query', {xmlns: 'urn:xmpp:xun:livechat:details'})
	// .c('item', {chat_room_jid: '25af3942-832e-11e8-8b01-0256e3917544@livechat.' + conn.options.host})
	.c('item', {chat_room_jid: '3cd1aa0e-7791-11e8-91f2-08002779e561@livechat.' + conn.options.host})
	.up()
	.c('item', {chat_room_jid: '009a464e-7f53-11e8-9866-08002779e561@livechat.' + conn.options.host})
	;

	conn.send(iq);
}

// <iq to="+60124466833@dev.xun.global/1187231039794660966584834" from="+60124466833@dev.xun.global" type="result" id="livechat_query_iq" xmlns:stream="http://etherx.jabber.org/streams">
// 	<query xmlns="urn:xmpp:xun:livechat:status">
// 		<item 
// 			user_jid="+60169057629@dev.xun.global" 
// 			tag="tag" 
// 			modified_date="2018-07-20T07:20:39.320472Z" 
// 			created_date="2018-07-20T07:20:26.418510Z" 
// 			chat_room_status="accepted" chat_room_jid="5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.dev.xun.global" 
// 			business_id="15109" 
// 			attending_staff_jid="+60166855099@dev.xun.global" 
// 			attending_employee_id=""/>
// 	</query>
// </iq>


function test_custom_iq(conn){
// <iq type="get" id="livechat_query_iq">
// 	<query xmlns="urn:xmpp:xun:livechat:detail">
// 		<item chat_room_jid="5fb7b7d2-8bed-11e8-8d70-06f8d367d97c@livechat.dev.xun.global"/>
// 	</query>
// </iq>
	var iq = new xmpp.Element('iq', {type:'get', id:'test_custom_iq'})
	.c('query', {xmlns: 'test'})
	;

	conn.send(iq);
}

function test1(a){
	console.log("This is test1 " + a);
}

function test2(a){
	console.log("This is test2 " + a);
}

module.exports = {
	test1: test1,
	test2: test2,
	v1: v1,
	v2: v2,
	v3: v3,
	print_stanza: print_stanza,
	set_status_message: set_status_message,
	set_user_vcard: set_user_vcard,
	set_public_user_vcard: set_public_user_vcard,
	set_room_vcard:set_room_vcard,
	get_user_vcard: get_user_vcard,
	get_public_user_vcard: get_public_user_vcard,
	get_livechat_vcard: get_livechat_vcard,
	get_room_vcard: get_room_vcard,
	subscribe_presence: subscribe_presence,
	response_subscribe_presence: response_subscribe_presence,
	enable_cc: enable_cc,
	disable_cc: disable_cc,
	enable_sm: enable_sm,
	disable_sm: disable_sm,
	resume_conn: resume_conn,
	process_business_message: process_business_message,
	send_message: send_message,
	send_message_to_group: send_message_to_group,
	send_business_message: send_business_message,
	send_message_to_livechat: send_message_to_livechat,
	send_chat_message_to_livechat: send_chat_message_to_livechat,
	send_to_business: send_to_business,
	send_to_business_domain: send_to_business_domain,
	send_to_chatstate_business: send_to_chatstate_business,
	send_chat_message_media_to_livechat: send_chat_message_media_to_livechat,
	send_delivery_receipt: send_delivery_receipt,
	livechat_query_iq: livechat_query_iq,
	test_custom_iq: test_custom_iq,
	livechat_query_iq_multi: livechat_query_iq_multi,
	livechat_query_iq_multi_test:livechat_query_iq_multi_test
};
