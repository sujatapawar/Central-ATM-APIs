var request = require('request');

exports.is_valid_IP = function(KeyIP,ClientAPIKey,ServerAPIKey,con,callback)
{
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

exports.is_PTR_Exist = function (AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Type,callback)
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

exports.AddPTR = function (AuthID,AuthPassword,NewZone,ZoneDomainName,Host,Domain,callback)
{
  request("https://api.cloudns.net/dns/add-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-type=PTR&host="+Host+"&record="+Domain+"&ttl=3600", function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}

exports.update_PTR = function(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,Host,Domain,callback)
{
  request("https://api.cloudns.net/dns/mod-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-id="+ID+"&host="+Host+"&record="+Domain+"&ttl=3600", function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}

exports.DeletePTR = function(AuthID,AuthPassword,NewZone,ZoneDomainName,ID,callback)
{
  request("https://api.cloudns.net/dns/delete-record.json?auth-id="+AuthID+"&auth-password="+AuthPassword+"&domain-name="+NewZone+"."+ZoneDomainName+"&record-id="+ID, function (error, response, body) {
    if (!error && response.statusCode == 200) {
      callback(body);
    }
  });
}