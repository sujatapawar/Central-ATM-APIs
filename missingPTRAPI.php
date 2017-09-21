<?php
/*
1. Remove IP from Pool (childPool_IPs) and put into warm-up (pool id 1)
2. Replace with warm-up IP (pool id 1) with same environment and same grade, with the last stage.
3. Release IP, and update counts
4. Add new entry into client_IP_detail with new IP assignment
5. Notify delivery team
6. Return (IP, PMTA, Domain)
*/
?>
