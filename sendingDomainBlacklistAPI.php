<?php
/*
1. Find all IP_ids for the blacklisted sending domain
2. Put sending domain into freezer
3. Switch to the alternate (inactive) domain
4. If there's no alternate domain found, all IPs should become inactive.
5. Send email to service, delivery and client.
{
"Steps to be done later:
1. Fetch new sending_domain from warm-up domain
2. Assign new domain as alternate (inactive) domain for all the IPs.
3. Replace rDNS config file with alternate domain (requires restart which should happen once a day using a chron job).
4. Replace PMTA config file with alternate domain 
(requires restart which should happen once a day using a chron job while the compiler is not sending any emails to the PMTAs). - Change DNS settings."

}

*/



?>
