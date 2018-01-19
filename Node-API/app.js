const express = require('express');
const bodyParser = require('body-parser');
const request = require('request');
const config  = require('./config.js');
const func = require('./db_functions');

const con = config.con;
const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
const server = app.listen(3000,"0.0.0.0" ,() => {
  const host = server.address().address;
  const port = server.address().port;
  console.log('running at http://' + host + ':' + port)
});

const AuthID = config.AuthID;
const AuthPassword = config.AuthPassword;
const ZoneDomainName = config.ZoneDomainName;
const Host = config.Host;
const Type = config.Type;
const ServerAPIKey = config.APIKey;

app.get('/',(req,res)=>{
  res.json({"status":"Error","statusDescription":"Invalid Request."});
});

app.post('/AddPTR',(req,res)=>{
  const keyIP = req.connection.remoteAddress;
  const ClientAPIKey = req.body.APIKey;
  let IP = req.body.IP.trim();
  let Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  let Zone = IP.split(".").reverse();
  IP = Zone[0];
  let NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];

  Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  //Valid IP
  func.is_valid_IP(keyIP,ClientAPIKey,ServerAPIKey,con,(result) => {
    if(result)
    {
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,(ID)=>{
        if(ID=='')
        {
          func.AddPTR(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Domain,(result)=>{
            res.send(result);
          });
        }
        else
        {
          func.update_PTR(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,Host,Domain,(result)=>{
            res.send(result);
          });
        }
      });
    }
    else
    {
      res.json({"status":"Error","statusDescription":"Invalid Request."});
    }
  });
});

app.post('/DeletePTR',(req,res)=>{
  const keyIP = req.connection.remoteAddress;
  const ClientAPIKey = req.body.APIKey;
  let IP = req.body.IP.trim();
  let Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  let Zone = IP.split(".").reverse();
  IP = Zone[0];
  let NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];
  
  func.is_valid_IP(keyIP,ClientAPIKey,ServerAPIKey,con,(result)=>{
    if(result)
    {
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,(ID)=>{
        if(ID=='')
        {
          res.json({"status":"Error","statusDescription":"Please enter valid record."});
        }
        else
        {
          func.DeletePTR(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,(result)=>{
            res.send(result);
          });
        }
      });
    }
    else{
      res.json({"status":"Error","statusDescription":"Invalid Request."});
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
