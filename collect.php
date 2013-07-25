<?php

require_once 'bootstrap.php';

require_lib('nmap');
require_lib('asus_router');



//network_scan('10.0.0.170-171', null);

parse_scan_result('C:/Temp/nmap2.xml');
//parse_scan_result('C:/Users/Cyril/git/network-monitor/example/2_hosts.xml');


asus_router_fetch_client_list('10.0.0.1', 'admin', 'password169');

$list = asus_router_fetch_dhcp_leases('10.0.0.1', 'admin', 'password169');

foreach ($list as $item) {
	echo "# [" . $item['mac'] . "] -> [" . $item['hostname'] . "]\n";
}

$updateCount = update_scanned_host_with_dhcp_lease($list);

echo "Number of scanned host record updated: $updateCount\n";



?>