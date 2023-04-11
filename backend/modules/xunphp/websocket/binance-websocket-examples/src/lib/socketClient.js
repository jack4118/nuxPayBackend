import WebSocket from 'ws';
import logger from './logger';
var fs = require('fs')
var filename = '../include/config.json';
var contents = fs.readFileSync(filename);
var jsonContent = JSON.parse(contents);
const io = require("socket.io");
const axios = require('axios');
class SocketClient {

  
  constructor(path, baseUrl) {
    this.baseUrl = baseUrl || 'wss://stream.binance.com/';
    this._path = path;
    this._createSocket();
    this._handlers = new Map();        

    // choose one
    // this._socket = require('socket.io-client')('http://admin.newnuxpaywhitelabelbackend.testback');
    // this._socket = require('socket.io-client')('http://localhost:8080');    
    this._socket = require('socket.io-client')(jsonContent.own_socket);    
  }

  _createSocket() {
    console.log(`${this.baseUrl}${this._path}`);
    this._ws = new WebSocket(`${this.baseUrl}${this._path}`);

    this._ws.onopen = (msg) => {
      logger.info('ws connected');    
      // var url = msg.target.url  
      // axios
      //   .post(jsonContent.notificationURL, {
      //     tag: 'Socket',
      //     message: 'Websocket connected! '+url
      //   })
      //   .then(res => {          
      //     console.log(res.status)
      //   })
      //   .catch(error => {
      //     console.error(error)
      //   })
    };

    this._ws.on('pong', () => {
      logger.info('receieved pong from server');
    });
    this._ws.on('ping', () => {
      logger.info('==========receieved ping from server');
      this._ws.pong();
    });

    this._ws.onclose = (msg) => {
      logger.warn('ws closed');
      var url = msg.target.url  
      axios
        .post(jsonContent.notificationURL, {
          tag: 'Socket',
          message: 'Websocket disconnected! '+url
        })
        .then(res => {          
          console.log(res.status)
        })
        .catch(error => {
          console.error(error)
        })
    };

    this._ws.onerror = (err) => {
      logger.warn('ws error', err);
    };

    this._ws.onmessage = (msg) => {
      try {
        const message = JSON.parse(msg.data);
        if (message.e) {
          if (this._handlers.has(message.e)) {
            this._handlers.get(message.e).forEach((cb) => {
              cb(message);
            });
          } else {
            logger.warn('Unprocessed method', message);
          }
        } else {          
          let stream = message.stream;
          var type = stream.split("@")[1];          
          console.log(new Date() + " " + stream);                    
          if(type == 'kline_1m' || type == 'kline_5m' || type == 'kline_1h' || type == 'kline_1d' || type == 'kline_1w' || type == 'kline_1M' ){
            let newData = {
              'stream' : message.stream,
              'symbol' : message.data.s,
              'date' : message.data.k.t,
              'open' : message.data.k.o, 
              'high' : message.data.k.h,
              'low' : message.data.k.l,
              'close' : message.data.k.c,
              'volume' : message.data.k.n,
              'adj_close' : message.data.k.E
            }
            this._socket.emit(stream, { message: newData });
            console.log(new Date() + " " + stream + " emitted");
          }   


          if(type == 'ticker' ){
            let newData = {
              'symbol' : message.data.s,
              'time' : message.data.E,
              'last_price' : message.data.c,
              'high_price' :  message.data.h,
              'low_price' : message.data.l              
            }
            this._socket.emit(stream, { message: newData });
            console.log(new Date() + " " + stream + " emitted");
          }   

        }
      } catch (e) {
        logger.warn('Parse message failed', e);
      }
    };

    this.heartBeat();
  }

  heartBeat() {
    setInterval(() => {
      if (this._ws.readyState === WebSocket.OPEN) {
        this._ws.ping();
        logger.info("ping server");
      }
    }, 5000);
  }

  setHandler(method, callback) {
    if (!this._handlers.has(method)) {
      this._handlers.set(method, []);
    }
    this._handlers.get(method).push(callback);
  }
}

export default SocketClient;
