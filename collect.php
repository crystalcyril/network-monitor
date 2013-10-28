<?php

/**
 * This script scans the network to identify all hosts.
 */

require_once 'bootstrap.php';

require_lib('netmon');

netmon_collect();

/*

require_lib('nmap');
require_lib('asus_router');

$ip_range = Config::get('scan_range');

$router_host = Config::get('router_host');
$router_username = Config::get('router_username');
$router_password = Config::get('router_password');


// do a network scan to discover all active hosts.
network_scan($ip_range, null);


$list = asus_router_fetch_client_list('10.0.0.1', 'admin', 'password169');
$updateCount = update_scanned_host_with_dhcp_lease($list);
echo "Number of scanned host record updated (by router client list): $updateCount\n";

// obtains a list of DHCP leases which contains MAC address <-> hostname 
// mapping. Note that host name may not be identified.
$list = asus_router_fetch_dhcp_leases($router_host, $router_username, $router_password);

// update the scanned result using the DHCP lease.
$updateCount = update_scanned_host_with_dhcp_lease($list);
echo "Number of scanned host record updated (by DHCP leases): $updateCount\n";
*/