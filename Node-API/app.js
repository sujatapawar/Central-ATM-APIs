const express = require('express');
const bodyParser = require('body-parser');
const request = require('request');
//const json = require('xml2json');
const config  = require('./config.js');
const func = require('./functions');
var qs = require('querystring');

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


const NCAPIKey = config.NCAPIKey;
const NCAPIUser = config.NCAPIUser;
const NCClientIP = config.NCClientIP;

app.post('/setDNSHost',(req,res)=>{
  const domain_name=req.body.domain_name;
  
  const host_name=req.body.host_name; 
  const record_type=req.body.record_type; 
  const addr_url=req.body.addr_url; 
  const mx_pref=req.body.mx_pref; 
  const obj= {};
  obj["HostName1"] = host_name;
  obj["RecordType1"] = record_type;
  obj["Address1"] = addr_url;
  obj["TTL1"] = mx_pref;
  
  func.get_DNSInfo(domain_name,NCAPIKey,NCAPIUser,NCClientIP,(data)=>{
	
    xml =data;
    var result;
    var parseString = require('xml2js').parseString;
    parseString(xml, function (err, data) 
    {
		result =data;
		
	});
    /*[
					{
						"HostId": "129366761",
						"Name": "@",
						"Type": "A",
						"Address": "190.01.201.8",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129368311",
						"Name": "@",
						"Type": "AAAA",
						"Address": "10.20.30.40",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129378178",
						"Name": "@",
						"Type": "AAAA",
						"Address": "40.30.20.10",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129359342",
						"Name": "www",
						"Type": "CNAME",
						"Address": "parkingpage.namecheap.com.",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "CName Record",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129384039",
						"Name": "@",
						"Type": "MX",
						"Address": "mx.test.com.",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129368265",
						"Name": "juvapp6cl204454085.in",
						"Type": "TXT",
						"Address": "v=spf1 include:juvlonns.com ~all",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					},
					{
						"HostId": "129359341",
						"Name": "@",
						"Type": "URL",
						"Address": "http://www.juvapp6cl204454085.in/?from=@",
						"MXPref": "10",
						"TTL": "1800",
						"AssociatedAppTitle": "",
						"FriendlyName": "URL Record",
						"IsActive": "true",
						"IsDDNSEnabled": "false"
					}
				]*/
	
	
     var cr=result['ApiResponse']['CommandResponse'];
     var result=cr[0]['DomainDNSGetHostsResult'][0]['host'];
     
    
    
    if(result.length>0)
    {
      var cnt=1;
      for (var i=0;i<result.length;i++)
      {
        ++cnt;
        obj["HostName"+cnt] = result[i]['$']['Name'];
        obj["RecordType"+cnt] = result[i]['$']['Type'];
        obj["Address"+cnt] = result[i]['$']['Address'];
        obj["TTL"+cnt] = result[i]['$']['TTL'];
      }
    }
    else
    {
      obj["HostName"] = result['$']['Name'];
      obj["RecordType"] = result['$']['Type'];
      obj["Address"] = result['$']['Address'];
      obj["TTL"] = result['$']['TTL'];
    }
  //  res.send(obj);
    func.setDNS(domain_name,obj,qs,NCAPIKey,NCAPIUser,NCClientIP,(data)=>{
		parseString(data, function (err, dnsdata) 
		{
			dns_result =dnsdata;
		});
		var cr=dns_result['ApiResponse']['CommandResponse'];
        var dnsresult=cr[0]['DomainDNSSetHostsResult'][0]['$']['IsSuccess'];
     
     res.send(dnsresult);
    });
  });
});

app.get('/',(req,res)=>{
  res.json({"status":"Error","statusDescription":"Invalid Request."});
});

app.post('/CheckPTR',(req,res)=>{
  const keyIP = req.connection.remoteAddress;
  const ClientAPIKey = req.body.APIKey;
  let IP = req.body.IP.trim();
  let Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  let Zone = IP.split(".").reverse();
  IP = Zone[0];
  let NewZone = Zone[1]+'.'+Zone[2]+'.'+Zone[3];
  Domain = (typeof req.body.Domain == 'undefined')?"":req.body.Domain.trim();
  func.PTRCheck(AuthID,AuthPassword,NewZone,ZoneDomainName,Domain,IP,Type,(data)=>{
    if(data)
    {
      res.json({"status":"Success","statusDescription":"Record Exist"});
    }
    else
    {
      res.json({"status":"Error","statusDescription":"Record Not Exist"});
    }
  });
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
    console.log(result+" "+keyIP);
    if(result)
    { 
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,IP,Type,(ID)=>{
        if(ID=='')
        {
          func.AddPTR(AuthID,AuthPassword,NewZone,ZoneDomainName,IP,Domain,(result)=>{
            res.send(result);
          });
        }
        else
        {
          func.update_PTR(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,IP,Domain,(result)=>{
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
  
  /* func.is_valid_IP(keyIP,ClientAPIKey,ServerAPIKey,con,(result)=>{
    if(result)
    { */
      func.is_PTR_Exist(AuthID,AuthPassword,NewZone,ZoneDomainName,IP,Type,(ID)=>{
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
    /* }
    else{
      res.json({"status":"Error","statusDescription":"Invalid Request."});
    }
  }); */
});



//===For Namecheap

 /* app.post('/setDNSHost', (req, res)=>
  {
	
	var domain_name=req.body.domain_name; 
	var host_name=req.body.host_name; 
	var record_type=req.body.record_type; 
	var addr_url=req.body.addr_url; 
	var mx_pref=req.body.mx_pref; 
	
	res_arr2=[{HostName:host_name, RecordType: record_type, Address: addr_url, MXPref: mx_pref}];
	
	//namecheap = new Namecheap('nichesoftware', '1d62913c7d12472f9bc2c4e68a17faec', '52.44.195.201');
	namecheap = new Namecheap(NCAPIUser, NCAPIKey, NCClientIP);
	namecheap.domains.dns.getHosts(domain_name, function(err, result) 
	{
	
	res1= getDNSInfo(result);  
	
	param1=res1.concat(res_arr2);
	
	namecheap.domains.dns.setHosts(domain_name, param1, function(err, res1) 
	{
		res.send(res1)
	});	  
});
});


function getDNSInfo(result,host_name,record_type,addr_url,mx_pref)
{
	var res_arr=[];
	
	if(result.length>0)
	{
	for (var i=0;i<result.length;i++)
	{
		console.log(result[i]['Name']);
		res_arr[i]={HostName:result[i]['Name'],RecordType:result[i]['Type'],Address:result[i]['Address'],TTL:result[i]['TTL']};
	}
	 param1=res_arr;//.concat(res_arr2);
	}
	else
	{
		res_arr=[{HostName:result['Name'],RecordType:result['Type'],Address:result['Address'],TTL:result['TTL']}];
		param1=res_arr;//'//.concat(res_arr2);
	}

	 return param1;
	
	
} */



//===Namecheap Ends Here
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
