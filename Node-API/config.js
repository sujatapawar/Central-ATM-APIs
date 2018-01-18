var mysql = require('mysql'); 

module.exports = {
    con: connection = mysql.createPool({ 
        host     : '52.44.195.201', 
        user     : 'root', 
        password : '', 
        database : 'ATM' 
    }),
    APIKey:"Niche-User",
    AuthID:"1430",
    AuthPassword:"n2ch3*2123",
    ZoneDomainName:"in-addr.arpa",
    Host:"127",
    Type:"PTR"
  };
