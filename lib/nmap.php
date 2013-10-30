<?php
// vim: ts=2 sw=2 sts=2

/**
 * Contains NMAP functions.
 *
 * 
 * Key functions:
 *
 * To perform a network scan, call the function netmon_nmap_network_scan().
 *
 * 
 *
 */


if (!defined('BOOTSTRAPPED')) {
	exit('should not include this script directly');
}


require_once(ROOT . DS . 'lib' . DS . 'nmap.php');
require_once(ROOT . DS . 'lib' . DS . 'db.php');


/**
 * Returns the path of the NMAP executable.
 *
 * @return string the path of the NMAP executable.
 */
function get_nmap_bin() {
	return 'nmap';
}



/**
 * Scan the whole network using NMAP, and return the result as an array of 
 * host objects.
 *
 * Read the nmap documentation for the output of XML.
 *
 * The return value of this function is equals to the value returned by
 * function netmon_convert_nmap_xml_to_host().
 *
 * @return an array containing host details.
 */
function netmon_nmap_network_scan($target, $options) {

	$nmap_bin = get_nmap_bin();

	$output_xml_file = tempnam(sys_get_temp_dir(), "netmon");

	// build the NMAP command to scan all hosts.
	// we found that as of NMAP 5.21, if -O (OS detection) is added,
	// all iPhone and android phones cannot be found.
	$opts = '-sV -T4 -O -F --version-light';
	$opts = '-T4 -F';
	$cmd = $nmap_bin . " $opts $target" . " -oX $output_xml_file";

	$output = array();
	$exec_ret_code = 0;

	// execute the command.
	echo "nmap command: $cmd\n";
	exec($cmd, $output, $exec_ret_code);
	echo "nmap return code: $exec_ret_code\n";

	$xmlDoc = simplexml_load_file($output_xml_file);
	return netmon_convert_nmap_xml_to_host($xmlDoc);

}



/**
 * Scan the whole network using NMAP, and return the result as XML.
 *
 * Read the nmap documentation for the output of XML.
 */
function network_scan($target, $options) {
	
	$nmap_bin = get_nmap_bin();
	
	$output_xml_file = tempnam(sys_get_temp_dir(), "netmon");

	$opts = '-sV -T4 -O -F --version-light';
	// the "-A" parameters enable nmap
	$opts = '-T4 -F';

	// build the command.
	$cmd = $nmap_bin . " $opts $target" . " -oX $output_xml_file";
	
	$output = array();
	$exec_ret_code = 0;
	
	// execute command.
	echo "nmap command: $cmd\n";
	exec($cmd, $output, $exec_ret_code);
	echo "nmap return code: $exec_ret_code\n";
	
	parse_scan_result($output_xml_file);
	
}


/**
 * Convert the NMAP's XML to our format.
 *
 * This function will return an array of objects
 */
function netmon_convert_nmap_xml_to_host($nmapXmlDOM) {

	$ret = array();

	if (count($nmapXmlDOM->host) > 0) {

		// iterate all hosts
		foreach ($nmapXmlDOM->host as $host) {

// 			echo "== host ==\n";
			
			//
			// we will collect these variables:
			//
			$ipv4 = null;		// e.g. 10.0.0.191
			$ipv6 = null;		// e.g. 
			$mac = null;		// e.g. aa:bb:cc:00:11:22
			$nicVendor = null;	// e.g. Apple, 
			$hostname = null;
			$nicState = null;	// e.g. up/down
			//
			$osFamily = null;
			$os = null;
			
			// we need to skip those inactive scans
			if (strtolower($host->status['state']) != 'up') {
				continue;
			}
			
			
			// dael with IP, MAC addresses, and NIC vendor information.
			if (count($host->address) > 0) {
				
				foreach ($host->address as $address) {
					
					$addrType = $address['addrtype'];
					
// 					echo " - " . $address['addr'] . " (" . $addrType . ")" . "\n";
					
					// handle different address types.
					if ('ipv4' == $addrType) {
						
						$ipv4 = (string)$address['addr'];
						
					} else if ('ipv6' == $addrType) {
						
						$ipv6 = (string)$address['addr'];
						
					} else if ('mac' == $addrType) {
						
						$mac = (string)$address['addr'];
						
						// may have vendor information
						if ($address['vendor'] != null) {
							$nicVendor = (string)$address['vendor'];
						}
					}
				}
			}
			
			// deal with host name
			if ($host->hostnames != null && $host->hostnames->hostname != null && count($host->hostnames->hostname) > 0) {
				
				$hostname = $host->hostnames->hostname[0]['name'];
				
			}
			
			// output in console for debugging.
// 			echo "host information:\n";
// 			echo "- host       : $hostname\n";
// 			echo "- ipv4       : $ipv4\n";
// 			echo "- ipv6       : $ipv6\n";
// 			echo "- mac        : $mac\n";
// 			echo "- nic vendor : $nicVendor\n";
// 			echo "\n";
			
			$o = array();
			$o['ipv4'] = $ipv4;
			$o['ipv6'] = $ipv6;
			$o['mac'] = $mac;
			$o['hostname'] = $hostname;
			$o['nic_vendor'] = $nicVendor;
			$o['detect_by'] = 'nmap';

			//print_r($o);
			
			$ret[] = $o;

		} // for each host
	}

	return $ret;
}


/**
 * 
 * 
 * @param string $output_file the output XML scanned file.
 */
function parse_scan_result($output_file) {
	
	// insert into database
	$db = new FileDb();
	$db->open();
	
	$db->clearScannedHosts();
	
	$xmlDoc = simplexml_load_file($output_file);
	
	if (count($xmlDoc->host) > 0) {

		// iterate all hosts
		foreach ($xmlDoc->host as $host) {

// 			echo "== host ==\n";
			
			//
			// we will collect these variables:
			//
			$ipv4 = null;		// e.g. 10.0.0.191
			$ipv6 = null;		// e.g. 
			$mac = null;		// e.g. aa:bb:cc:00:11:22
			$nicVendor = null;	// e.g. Apple, 
			$hostname = null;
			$nicState = null;	// e.g. up/down
			
			// we need to skip those inactive scans
			if (strtolower($host->status['state']) != 'up') {
				continue;
			}
			
			
			// dael with IP, MAC addresses, and NIC vendor information.
			if (count($host->address) > 0) {
				
				foreach ($host->address as $address) {
					
					$addrType = $address['addrtype'];
					
// 					echo " - " . $address['addr'] . " (" . $addrType . ")" . "\n";
					
					// handle different address types.
					if ('ipv4' == $addrType) {
						
						$ipv4 = $address['addr'];
						
					} else if ('ipv6' == $addrType) {
						
						$ipv6 = $address['addr'];
						
					} else if ('mac' == $addrType) {
						
						$mac = $address['addr'];
						
						// may have vendor information
						if ($address['vendor'] != null) {
							$nicVendor = $address['vendor'];
						}
					}
				}
			}
			
			// deal with host name
			if ($host->hostnames != null && $host->hostnames->hostname != null && count($host->hostnames->hostname) > 0) {
				
				$hostname = $host->hostnames->hostname[0]['name'];
				
			}
			
			// output in console for debugging.
// 			echo "host information:\n";
// 			echo "- host       : $hostname\n";
// 			echo "- ipv4       : $ipv4\n";
// 			echo "- ipv6       : $ipv6\n";
// 			echo "- mac        : $mac\n";
// 			echo "- nic vendor : $nicVendor\n";
// 			echo "\n";
			
			$host['ipv4'] = $ipv4;
			$host['ipv6'] = $ipv6;
			$host['mac'] = $mac;
			$host['hostname'] = $hostname;
			$host['nic_vendor'] = $nicVendor;
			$retval = $db->insertScannedHost($host);
			if ($retval === FALSE) {
				echo "failed to insert scanned host\n";
			}
			
		} // for each host
		
	}
	
	$db->close();
	
	return TRUE;
	
}
