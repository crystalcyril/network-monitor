<?php
// vim: ts=2 sw=2 sts=2

/**
 * 
 * Core functions:
 *
 * Collect host details on the network:
 * - netmon_collect().
 *
 */


require_once 'bootstrap.php';

require_lib('db');
require_lib('nmap');
require_lib('asus_router');


/**
 * Collection network computer information and save the persistent store.
 *
 * This method will use several technique to scan active hosts on the 
 * configured network range, try to fill in all details (IP, MAC, 
 * hostname, etc...) as much as possible. Finally, the result will be 
 * saved to the data store for query.
 * 
 * @return boolean
 */
function netmon_collect() {

	$db = new FileDb();
	$db->open();

	
	// read external configuration.
	$ip_range = Config::get('scan_range');
	
	// asus's router configuration.
	$router_host = Config::get('router_host');
	$router_username = Config::get('router_username');
	$router_password = Config::get('router_password');
	
	
	// start a new scan session.
	$netmonScanSession = netmon_create_scan_session();

	// call nmap to scan for ALL hosts and return the scanned result
	// as XML document.
	echo "scanning hosts using nmap on ip range $ip_range...\n";
	$hosts = netmon_nmap_network_scan($ip_range, null);

	echo "number of hosts located: " . count($hosts) . "\n";

	// let nbtscan to patch information
	netmon_nbtscan_visit($hosts);

	// now append information if not exists.
	echo "hosts after rectification\n";
	print_r($hosts);
	


	// use nmap to collect active hosts.
//	netmon_scan_active_hosts($netmonScanSession, $ip_range);
	
	// close the scan session.
	$netmonScanSession = netmon_close_scan_session($netmonScanSession);


	//
	// Save the scanned hosts to database.
	//
	$db->clearScannedHosts();

	foreach ($hosts as $host) {
		$db->insertScannedHost($host);
	}

	
	
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


function netmon_nbtscan_visit(&$hosts) {

	if ($hosts == null) return;
	if (count($hosts) == 0) return;

	foreach ($hosts as &$host) {

		$need_process = false;
		if (empty($host['hostname']) || empty($host['mac'])) {
			$need_process = true;
		}

		if (!$need_process) {
			continue;
		}

//		echo "host of IP " . $host['ipv4'] . " requires patching\n";

		// call nbtscan to acquire more information.
		$nbtscan_result = netmon_nbtscan($host['ipv4']);
		if ($nbtscan_result === FALSE) {
			// no result found, skipped.
			continue;
		}

		// patch the host informatino
		if (empty($host['hostname']) && !empty($nbtscan_result['hostname'])) {
			$host['hostname'] = $nbtscan_result['hostname'];
		}
		if (empty($host['mac']) && !empty($nbtscan_result['mac'])) {
			$host['mac'] = $nbtscan_result['mac'];
		}

	}
}


/**
 * 
 * 
 * @param unknown $netmonScanSession
 */
function netmon_scan_active_hosts($netmonScanSession, $ip_range) {
	
	$hostXml = netmon_nmap_network_scan($ip_range, null);

	echo "number of hosts located: " . count($hostXml->host) . "\n";
	
	netmon_patch_netbios_name();
	
}



function netmon_create_scan_session() {
	return FALSE;
}

function netmon_close_scan_session($netmonScanSession) {
	return false;
}


function netmon_patch_netbios_name() {
	
	$db = new FileDb();
	$db->open();

	$hosts = $db->getHostSessionWithEmptyHostname();
	if (!empty($hosts)) {
		foreach ($hosts as $host) {

			$sbtscan = netmon_nbtscan($host['ipv4']);
			
			if ($sbtscan === FALSE) continue;
			if (empty($sbtscan['hostname'])) continue;
		//echo "updating IP $host['ipv4'] with hostname $sbtscan['host']\n";
		
		} // foreach hosts
	}
}


/**
 * This function will use the linux utility 'nbtscan' to scan for 
 * NetBIOS name and MAC address for the specified IP.
 */
function netmon_nbtscan($ip) {
	
	$nbtscan_bin = Config::get('nbtscan_bin');

	$cmd = "$nbtscan_bin -s =###= -q $ip";
//	echo "sbtscan command: $cmd\n";

	$output = array();
	$exec_ret_code = 0;

	exec($cmd, $output, $exec_ret_code);
	
//	echo "sbtscan return code: $exec_ret_code\n";

	$ret = FALSE;

	// parse the output 
	foreach ($output as $raw) {
		
		// split it
		$host_details = split('=###=', $raw);
		if (count($host_details) < 5) {
			echo "host line '$raw' does not contain 5 elements, skipped\n";
			continue;
		}

		// spec:
		// 1: IP
		// 2: NetBIOS name
		// 3: type
		// 4: ?
		// 5: MAC address
		$netbios_name = trim($host_details[1]);
//		echo "IP $ip: NetBIOS=[[$netbios_name]]\n";
		
		$ret['ipv4'] = $host_details[0];
		$ret['hostname'] = $netbios_name;
		$ret['mac'] = $host_details[4];
	}

	return $ret;
	
}

