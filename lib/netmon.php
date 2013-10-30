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
	
	
	// start a new scan session.
	$netmonScanSession = netmon_create_scan_session();

	//
	// Scan technique.
	//


	// call nmap to scan for ALL hosts and return the scanned result
	// as XML document.
	echo "scanning hosts using nmap on ip range $ip_range...\n";
	$hosts = netmon_nmap_network_scan($ip_range, null);
	echo "number of hosts located by nmap: " . count($hosts) . "\n";

	netmon_asus_router_scan($hosts);


	// let nbtscan to patch information
	netmon_nbtscan_visit($hosts);

	netmon_asusrouter_visit($hosts);

	// now append information if not exists.
//	echo "hosts after rectification\n";
//	print_r($hosts);
	


	// close the scan session.
	$netmonScanSession = netmon_close_scan_session($netmonScanSession);


	//
	// Save the scanned hosts to database.
	//
	$db->clearScannedHosts();

	foreach ($hosts as $host) {
		$id = $db->insertScannedHost($host);
		if (empty($id)) {
			echo "failed to save host to database (ip: " . $host['ipv4'] . ")\n";
		}
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
			echo "nbtscan found no result for IP " . $host['ipv4'] . ", skipped\n";
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

	unset($host);
}



/**
 * Visit each input host's record and patch missing information.
 *
 * Current implementation will access ASUS router to look for information,
 * this function is capable of doing the following:
 *
 * 1. patch missing hostname with matching MAC address.
 */
function netmon_asusrouter_visit(&$hosts) {

	// asus's router configuration.
	$router_host = Config::get('router_host');
	$router_username = Config::get('router_username');
	$router_password = Config::get('router_password');

	if (empty($router_host)) {
		echo "router host IP missing, aborted\n";
		return;
	}

	// obtains list of clients from router.
	$router_mac_to_host_list = asus_router_fetch_dhcp_leases($router_host, $router_username, $router_password);

//	print_r($router_mac_to_host_list);

	// now we visit each host from the input hosts list, 
	// and if hostname is missing, we try to look it up from 
	// router's returned value.
	foreach ($hosts as &$host) {
		// if hostname already exists for the iterated host, skip processing.
		if (!empty($host['hostname']) || strlen(trim($host['hostname'])) > 0)
			continue;
		// since we need MAC address to lookup, if the host does not 
		// have MAC address, we skip as well.
		if (empty($host['mac']) || strlen(trim($host['mac'])) == 0)
			continue;

		// now we look up the host name from router's list
		// using the mac address from the input host.
		foreach ($router_mac_to_host_list as $router_client) {
			if (strtolower($host['mac']) == strtolower($router_client['mac'])) {
				$host['hostname'] = $router_client['hostname'];
				break;	// done
			}
		} // foreach client record from router's DHCP lease.
	} // foreach input host to be patched.

	unset($host);
	
}


function netmon_asus_router_scan(&$hosts) {

	// asus's router configuration.
	$router_host = Config::get('router_host');
	$router_username = Config::get('router_username');
	$router_password = Config::get('router_password');


	// retrieve the list of MAC address. Any MAC address in this list
	// reflect the fact that the host is online.
	$router_list = asus_router_get_wireless_client_list($router_host, $router_username, $router_password);

	if ($router_list !== FALSE) {
		echo "number of wireless clients from asus router: " . count($router_list) . "\n";
//	echo "list of wireless clients from asus router:\n";
//	print_r($router_list);
	}

	// retrieve the DHCP leases from the router. note that the list 
	// maybe out of date and the client may have already disconnected.
	$router_dhcp_list = asus_router_get_dhcp_lease($router_host, $router_username, $router_password);
	
	if ($router_dhcp_list !== FALSE) {
		echo "number of DHCP leases from asus router: " . count($router_dhcp_list) . "\n";
//	echo "list of DHCP leases from asus router:\n";
//	print_r($router_dhcp_list);
	}


	// merge the two lists (real-time wireless client and old DHCP leases)
	// into a new list.
	$patched_wireless_client_count = 0;
	if ($router_list !== FALSE && $router_dhcp_list !== FALSE) {
		foreach ($router_list as &$wireless_client) {
			foreach ($router_dhcp_list as $dhcp_lease) {
				if (strtolower($wireless_client['mac']) == strtolower($dhcp_lease['mac'])) {
//					echo "matching information found, patching wireless client (mac=" . $wireless_client['mac'] . ")\n";
					$wireless_client['ipv4'] = $dhcp_lease['ipv4'];
					$wireless_client['hostname'] = $dhcp_lease['hostname'];
					$patched_wireless_client_count++;
					break;
				}
			}
		}
		unset($wireless_client);

		echo "number of wireless client patched: $patched_wireless_client_count of " . count($router_list) . "\n";
	}

//	echo "patched wireless client list:\n";
//	print_r($router_list);
	

	//
	// the input host list may not have the clients contained in the 
	// router's wireless client list, so we merge router's wireless 
	// client list into the input list.
	//
	$patch_host_count = 0;
	foreach ($router_list as $wireless_client) {
	
		// $exists: true if the wireless client does exist in the 
		// input host list, false otherwise.
		$exists = false;
		foreach ($hosts as $input_host) {
			// host with matching MAC address.
			if (!empty($input_host['mac'])
					&& strtolower($input_host['mac']) == strtolower($wireless_client['mac'])) {
				$exists = true;
				break;
			} else if (!empty($input_host['ipv4'])
					&& strtolower($input_host['ipv4']) == strtolower($wireless_client['ipv4'])) {
				$exists = true;
				break;
			}
		}

		if (!$exists) {
			echo "wireless client is not found, going to add to input host list\n";

			$o = array_merge(array(), $wireless_client);
			$o['detect_by'] = 'router';
			// insert into array.
			$hosts[] = $o;

			$patch_host_count++;
		}

	}

	echo "number of hosts detected in asus router which are added to host list: $patch_host_count\n";

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
	if (!empty($nbtscan_bin)) $nbtscan_bin = trim($nbtscan_bin);

	if (empty($nbtscan_bin)) {
		echo "nbtscan_bin configuration item missing, abort\n";
		return FALSE;
	}


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
		$ret['mac'] = strtoupper($host_details[4]);
	}

	return $ret;
	
}

