var request = require('request');

exports.get_DNSInfo = (domain_name,NCAPIKey,NCAPIUser,NCClientIP,callback) =>{
  var Domain = domain_name.split(".");
  var TLD = Domain[1];
  var SLD = Domain[0]
  request("https://api.namecheap.com/xml.response?ApiUser="+NCAPIUser+"&ApiKey="+NCAPIKey+"&UserName="+NCAPIUser+"&ClientIP="+NCClientIP+"&Command=namecheap.domains.dns.getHosts&TLD="+TLD+"&SLD="+SLD, function (error, response, body) {
    callback(body)
  });
}

exports.setDNS = (domain_name,obj,qs,NCAPIKey,NCAPIUser,NCClientIP,callback) =>{
  var Domain = domain_name.split(".");
  var TLD = Domain[1];
  var SLD = Domain[0];
  var result = qs.stringify(obj);
  request("http://api.namecheap.com/xml.response?apiuser="+NCAPIUser+"&apikey="+NCAPIKey+"&username="+NCAPIUser+"&Command=namecheap.domains.dns.setHosts&ClientIp="+NCClientIP+"&SLD="+SLD+"&TLD="+TLD+"&"+result, function (error, response, body) {
    callback(body)
  }); 
}

exports.PTRCheck = (AuthID,AuthPassword,NewZone,ZoneDomainName,Domain,Host,Type,callback) =>
{
  request("https://api.cloudns.net/dns/records.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&host="+Host+"&type="+Type, function (error, response, body) {
    if (!error && response.statusCode == 200) {
      if(body=="[]")
      {
        callback(false);
      }
      else
      {
        body = JSON.parse(body);
        for (var key in body) {
          if(Domain==body[key]['record'])
          {
            callback(true);
          }
          else
          {
            callback(false);
          }
        }
      }
    }
  });
}

exports.is_valid_IP = (KeyIP,ClientAPIKey,ServerAPIKey,con,callback) => {
  con.query("SELECT server_id FROM server_master where host_name='"+KeyIP+"'", function (err, result, fields) {
    if(result=="" || ClientAPIKey!=ServerAPIKey)
    {
      callback(false);
    }
    else{
      callback(true);
    }
  });
};

exports.is_PTR_Exist = (AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,callback) => 
{
  request("https://api.cloudns.net/dns/records.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&host="+Host+"&type="+Type, function (error, response, body) {
    if (!error && response.statusCode == 200) {
      if(body=="[]")
      {
        callback("");
      }
      else
      {
        body = JSON.parse(body);
        for (var key in body) {
          callback(key);
        }
      }
    }
  });
};

exports.AddPTR = (AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Domain,callback) =>
{
  request("https://api.cloudns.net/dns/add-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-type=PTR&host="+Host+"&record="+Domain+"&ttl=3600", function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}

exports.update_PTR = (AuthID,AuthPassword,NewZone,ZoneDomainName,ID,Host,Domain,callback) =>
{
  request("https://api.cloudns.net/dns/mod-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-id="+ID+"&host="+Host+"&record="+Domain+"&ttl=3600", function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}

exports.DeletePTR = (AuthID,AuthPassword,NewZone,ZoneDomainName,ID,callback) => 
{
  request("https://api.cloudns.net/dns/delete-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-id="+ID, function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}