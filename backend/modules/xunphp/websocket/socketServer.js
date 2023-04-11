const fs = require('fs');
var https = require('https');
const mysql = require('mysql')
var moment = require('moment');

var filename = 'include/config.json';
var contents = fs.readFileSync(filename);
var jsonContent = JSON.parse(contents);

var connection = mysql.createConnection({
    host: jsonContent.db_host,
    user: jsonContent.db_user,
    password: jsonContent.db_password,
    database: jsonContent.db_database
})

const maxSize = jsonContent.maxSize;
var whitelist = ["ethusdt","btcusdt","ethbtc", "filusdt"];
var table = ["graph_data_1m","graph_data_5m","graph_data_1h","graph_data_1d","graph_data_1w","graph_data_1mt"];


connection.connect(function(err) {
  if (err) throw err;
  console.log("Connected!");
});

connection.on('error', function(err) {
    console.log(err.code); // 'ER_BAD_DB_ERROR'
  });

connection.on('close', function(err) {
    if (err) {
        console.log("SQL Connection Closed");
    } else {
        console.log('Manually called .end()');
    }
});


var options = {
    key: fs.readFileSync('./cert/file.pem'),
    cert: fs.readFileSync('./cert/file.crt')
};

var serverPort = jsonContent.socket_port;

var server = https.createServer(options);
const io1 = require("socket.io")(server,{
    cors: {        
        origin: '*',
      }
});

io1.on('connection', function(socket) {      

    socket.on('request', function(data) { 
        console.log(data)   
        var request_common_symbol = data.common_symbol;
        var request_table = data.table;
        var request_data = [];
            if(whitelist.includes(request_common_symbol) && table.includes(request_table)) {
                connection.query("SELECT * FROM "+request_table+" WHERE `common_symbol` = '"+request_common_symbol+"' ORDER BY `created_at` DESC LIMIT "+maxSize+" ", function (err, result, fields) {
                    if (err) throw err;
                    var dbRecords = JSON.parse(JSON.stringify(result));                
                    
                    for (var i = 0 ; i < dbRecords.length ; i++) {
                        let dbRecord = dbRecords[i]
                        let date = moment(dbRecord.created_at).valueOf();            
                        let array = {
                            date  : date,
                            open : dbRecord.open,
                            high : dbRecord.high,
                            low : dbRecord.low,
                            close : dbRecord.close
                        }
                        request_data.push(array);                                  
                    }
                    console.log(request_data)
                    socket.emit('request', request_data.reverse());
                }); 
            } else {
                console.log('not valid');
            }
    })

    socket.onAny(function(event,data) {    
        
        if(event == 'request'){
            return ''
        }

        socket.broadcast.emit(event, data);

        var event_name = event.split('@'); 
        var common_symbol = event_name[0];
        var type_interval = event_name[1];        
        var interval = type_interval.split('_')[1];
        var socket_data = data.message;
        var table_name;
        
        console.log(event);        

        if (interval) {
            if(interval == '1m') {
                table_name = 'graph_data_1m';
            } else if (interval == '5m') {
                table_name = 'graph_data_5m';
            } else if (interval == '1h') {
                table_name = 'graph_data_1h';
            } else if (interval == '1d') {
                table_name = 'graph_data_1d';
            } else if (interval == '1w') {
                table_name = 'graph_data_1w';
            } else if (interval == '1M') {
                table_name = 'graph_data_1mt';
            }
            
            var amount = socket_data.volume * socket_data.close;
            var change = socket_data.close - socket_data.open;
            var created_at = moment(socket_data.date).format("YYYY-MM-DD HH:mm:ss");        
    
            let stmt = `INSERT INTO `+table_name+` (\`common_symbol\`, \`high\`, \`low\`, \`open\`, \`close\`, \`volume\`, \`amount\`, \`change\`, \`created_at\`) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE \`high\`=?, \`low\`=?, \`open\`=?, \`close\`=?, \`volume\`=?, \`amount\`=?, \`change\`=?`;
            let todo =[common_symbol, socket_data.high, socket_data.low, socket_data.open, socket_data.close, socket_data.volume, amount, change, created_at, socket_data.high, socket_data.low, socket_data.open, socket_data.close, socket_data.volume, amount, change ];        
    
            // execute the insert statment
            connection.query(stmt, todo, (err, results, fields) => {
                if (err) {
                    return console.error(err.message);
                }            
            });
        } else if (type_interval == 'ticker'){
            // table_name = 'graph_summary';

            // var amount = socket_data.volume * socket_data.close;
            // var change = socket_data.close - socket_data.open;
            // var created_at = moment(socket_data.date).format("YYYY-MM-DD");

            // let stmt = `INSERT INTO `+table_name+` (\`common_symbol\`, \`high\`, \`low\`, \`open\`, \`close\`, \`volume\`, \`amount\`, \`change\`, \`date\`) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE \`high\`=?, \`low\`=?, \`open\`=?, \`close\`=?, \`volume\`=?, \`amount\`=?, \`change\`=?`;
            // let todo =[common_symbol, socket_data.high, socket_data.low, socket_data.open, socket_data.close, socket_data.volume, amount, change, created_at, socket_data.high, socket_data.low, socket_data.open, socket_data.close, socket_data.volume, amount, change ];        

            // console.log(socket_data)
            // console.log(todo)

            // // execute the insert statment
            // connection.query(stmt, todo, (err, results, fields) => {
            //     if (err) {
            //         return console.error(err.message);
            //     }            
            // });            
        }

    });
});

server.listen(serverPort)