var express = require('express');
var bodyParser = require('body-parser');
var request = require('request');
var config  = require('./config.js');
var func = require('./db_functions');

var con = config.con;
var app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
var server = app.listen(3000,"127.0.0.1" ,function () {
  var host = server.address().address;
  var port = server.address().port;
  console.log('running at http://' + host + ':' + port)
});

var AuthID = config.AuthID;
var AuthPassword = config.AuthPassword;
var ZoneDomainName = config.ZoneDomainName;
var Host = config.Host;
var Type = config.Type;
var ServerAPIKey = config.APIKey;

app.get('/test',function(req,res){
  res.send("test");
});

app.post('/AddPTR',function(req,res){
  var keyIP = req.connection.remoteAddress;
  var ClientAPIKey = req.body.APIKey;
  var IP = req.body.IP.trim();
  var Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  var Zone = IP.split(".").reverse();
  IP = Zone[0];
  var NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];

  var Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  //Valid IP
  func.is_valid_IP(keyIP,ClientAPIKey,ServerAPIKey,con,function(result){
    if(result)
    {
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,function(ID){
        if(ID=='')
        {
          func.AddPTR(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Domain,function(result){
            res.send(result);
          });
        }
        else
        {
          func.update_PTR(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,Host,Domain,function(result){
            res.send(result);
          });
        }
      });
    }
    else
    {
      res.send('"status":"Error","statusDescription":"Invalid Request."');
    }
  });
});

app.post('/DeletePTR',function(req,res){
  var keyIP = req.connection.remoteAddress;
  var ClientAPIKey = req.body.APIKey;
  var IP = req.body.IP.trim();
  var Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  var Zone = IP.split(".").reverse();
  IP = Zone[0];
  var NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];
  
  func.is_valid_IP(keyIP,ClientAPIKey,ServerAPIKey,con,function(result){
    if(result)
    {
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,function(ID){
        if(ID=='')
        {
          res.send('"status":"Error","statusDescription":"Please enter valid record."');
        }
        else
        {
          func.DeletePTR(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,function(result){
            res.send(result);
          });
        }
      });
    }
    else{
      res.send('"status":"Error","statusDescription":"Invalid Request."');
    }
  });
});
/*
app.post('/AllRecord',function(req,res){
  var IP = req.body.IP.trim();
  var Zone = IP.split(".").reverse();
  IP = Zone[0];
  var NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];
  request("https://api.cloudns.net/dns/records.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&host=0&type=PTR", function (error, response, body) {
    if (!error && response.statusCode == 200) {
      body = JSON.parse(body);
      //var records = [];
      //var array = new array();
      //for (var key in body) {
      //  records.push(body[key].record);
      //}
      res.send(body);
    }
  });
});
*/
