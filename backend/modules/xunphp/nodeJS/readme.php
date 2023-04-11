<?php

	## this is nodeJS dependancy 

	npm i node-xmpp
	npm i mysql

	sudo npm install -g forever

	## To Start node js background
	cd /var/www/xunBackendPHP/backend
	forever start -o nodeJS/out.log -e nodeJS/err.log nodeJS/businessSendingNodeJS.js

?>