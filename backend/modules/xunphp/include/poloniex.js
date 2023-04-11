const fs = require('fs');

var params = process.argv;
var method = params[2];
var param = params[3];

const filename = __dirname+'/config.json';
const contents = fs.readFileSync(filename);
const jsonContent = JSON.parse(contents);

var url = jsonContent.poloUrl;
var apiKey = jsonContent.poloApikey;
var secretKey = jsonContent.poloSecretKey;

const axios = require('axios')
const CryptoJS = require('crypto-js')
let timestamp = new Date().getTime()



const paramUtils = {
    values: [],
    put(k, v) {
        let value = encodeURIComponent(v)
        this.values.push(k + "=" + value)
    },
    sortedValues() {
        return this.values.sort()
    },
    addGetParams(params) {
        Object.keys(params).forEach(k => {
            this.put(k, params[k])
        })
        this.sortedValues()
    },
    getParams(requestMethod, param) {
        if (requestMethod === 'GET') {
            this.put("signTimestamp", timestamp)
            this.addGetParams(param)
            return this.values.join("&").toString()
        } else if (requestMethod === 'POST' || requestMethod === 'PUT' || requestMethod === 'DELETE') {
            return "requestBody=" + JSON.stringify(param) + "&signTimestamp=" + timestamp
        }
    }
}

class Sign {
    constructor(method, path, param, secretKey) {
        this.method = method
        this.path = path
        this.param = param
        this.secretKey = secretKey
    }

    sign() {
        let paramValue = paramUtils.getParams(this.method, this.param)
        let payload = this.method.toUpperCase() + "\n" + this.path + "\n" + paramValue
        // console.log("payload:" + payload)

        let hmacData = CryptoJS.HmacSHA256(payload, this.secretKey);
        return CryptoJS.enc.Base64.stringify(hmacData);
    }
}


function getHeader(method, path, param) {
    const sign = new Sign(method, path, param, secretKey).sign()
    // console.log(`signature:${sign}`)
    return {
        "Content-Type": "application/json",
        "key": apiKey,
        "signature": sign,
        "signTimestamp": timestamp
    }
}

function get(url, path, param = {}) {
    const headers = getHeader('GET', path, param)
    return new Promise((resolve,reject)=>{ 
        axios.get(url + path, {params: param, headers: headers})
        .then(res => {
            resolve(res.data)  
        })
        .catch(e => {
            reject(e)
        })
    })
}

function post(url, path, param = {}) {
    const headers = getHeader('POST', path, param)
    return new Promise ((resolve,reject)=>{
        axios.post(url + path, param, {headers: headers})
        .then(res => {
            resolve(res.data)  
        })
        .catch(e => {
            reject(e)
        })
    })
}


function del(url, path, param = {}, apiKey, secretKey) {
    const sign = new Sign('DELETE', path, param, apiKey, secretKey)
    const headers = sign.getSign()
    return new Promise((resolve,reject)=>{
        axios.delete(url + path, {data: param, headers: headers})
        .then(res =>{
            resolve(res.data)  
        })
        .catch(e => {
            reject(e)
        })

    })
}

function put(url, path, param = {}, apiKey, secretKey) {
    const sign = new Sign('PUT', path, param, apiKey, secretKey)
    const headers = sign.getSign()
    return new Promise((resolve,reject)=>{ 
        axios.put(url + path, param, {headers: headers})
        .then(res => {
            resolve(res.data)  
        })
        .catch(e => {
            reject(e)
        })
    })
}




async function placeOrder(params) {

    try {
        
        var data = await post(url, '/orders/',params);
        
        var resultjson = JSON.stringify({"status": "ok", "code": 0, "data": data});
        
    } catch(error) {

        var resultjson = JSON.stringify({"status": "error", "code": 1, "data": error});
    }

    console.log(resultjson);
}

async function Withdraw(params) {

    var amount = params.amount;
    var address = params.address;
    var currency = params.currency;

    try {

       data = await post(url, '/wallets/withdraw', {"currency":currency, "amount": amount, "address": address});
        
        var resultjson = JSON.stringify({"status": "ok", "code": 0, "data": data});
        
    } catch(error) {

        var resultjson = JSON.stringify({"status": "error", "code": 1, "data": error});
    }

    console.log(resultjson);
}


async function orderStatus(params) {
orderId = params;
    try {
        
        var data = await get(url, '/orders/'+orderId);
        
        var resultjson = JSON.stringify({"status": "ok", "code": 0, "data": data});
        
    } catch(error) {

        var resultjson = JSON.stringify({"status": "error", "code": 1, "data": error});
    }

    console.log(resultjson);
}


    
async function returnBalance() {

try {

    var data = await get(url, '/accounts/balances');
    
    var resultjson = JSON.stringify({"status": "ok", "code": 0, "data": data});
} catch(error) {

    var resultjson = JSON.stringify({"status": "error", "code": 1, "data": error});

}
console.log(resultjson)
}
    
    switch(method) {
        case 'placeOrder':
            placeOrder(JSON.parse(param));
    
            break;
        case 'returnBalance':
            returnBalance();
    
            break;

        case 'Withdraw':
            Withdraw(JSON.parse(param));
    
            break;
        case 'orderStatus':
            orderStatus(param);
    
            break;
    
        default:
            console.log("Method not found");
            break;
    }
