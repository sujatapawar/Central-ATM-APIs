<?php
/*
1. Put Domain into freezer
2. Replace with a new domain (with same grade and environment, and last stage) from warm-up
3. Check the number of times that any domain has been blacklisted during this client's sendings 
(irrespective of the type of domain that was blacklisted before)
4. "If any domain is blacklisted for the first time for the client, then:
- Notify the delivery team of the changes carried out in the pool(s)
- Return the domain blacklist count of the client and the new domain for the ATM2.0 to resume compilation.

Or

If the number of times that the domain is blacklisted for this client exceeds 1, then:
- Release all IPs, and update counts
- Notify client, client servicing and delivery team
- Return the domain blacklist count of the client"
5. 

*/

?>
