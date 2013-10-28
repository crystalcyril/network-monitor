<?php

require_once 'bootstrap.php';

require_lib('nmap');
require_lib('asus_router');


/**
 * Collection network computer information.
 * 
 * @return boolean
 */
function netmon_collect() {
	
	// read external configuration.
	$ip_range = Config::get('scan_range');
	
	// asus's router configuration.
	$router_host = Config::get('router_host');
	$router_username = Config::get('router_username');
	$router_password = Config::get('router_password');
	
	
	// start a new scan session.
	$netmonScanSession = netmon_create_scan_session();

	// use nmap to collect active hosts.
	netmon_scan_active_hosts($netmonScanSession, $ip_range);
	
	// close the scan session.
	$netmonScanSession = netmon_close_scan_session($netmonScanSession);
	
	
// 	// do a network scan to discover all active hosts.
// 	network_scan($ip_range, null);
	
	
// 	$list = asus_router_fetch_client_list($router_host, $router_username, $router_password);
// 	$updateCount = update_scanned_host_with_dhcp_lease($list);
// 	echo "Number of scanned host record updated (by router client list): $updateCount\n";
	
// 	// obtains a list of DHCP leases which contains MAC address <-> hostname
// 	// mapping. Note that host name may not be identified.
// 	$list = asus_router_fetch_dhcp_leases($router_host, $router_username, $router_password);
	
// 	// update the scanned result using the DHCP lease.
// 	$updateCount = update_scanned_host_with_dhcp_lease($list);
// 	echo "Number of scanned host record updated (by DHCP leases): $updateCount\n";
	
	
	return true;
}


/**
 * 
 * 
 * @param unknown $netmonScanSession
 */
function netmon_scan_active_hosts($netmonScanSession, $ip_range) {
	
	netmon_nmap_network_scan($ip_range, null);
	
}



function netmon_create_scan_session() {
	return FALSE;
}

function netmon_close_scan_session($netmonScanSession) {
	return false;
}