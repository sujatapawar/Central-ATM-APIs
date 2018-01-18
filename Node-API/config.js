var mysql = require('mysql'); 

module.exports = {
    con: connection = mysql.createPool({ 
        host     : 'localhost', 
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
