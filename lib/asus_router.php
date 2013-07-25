<?php

if (!defined('BOOTSTRAPPED')) {
	exit('should not include this script directly');
}


/**
 * Fetch client list from asus router.
 *
 * @param unknown $router_ip
 */
function asus_router_fetch_client_list($router_ip, $username, $password) {

	//$url = 'http://10.0.0.1/update_clients.asp?_=1374729100182'
	$url = 'http://' . $router_ip . '/update_clients.asp?_=' . time();
	
	$process = curl_init($url);
	curl_setopt($process, CURLOPT_HTTPHEADER, array(
		'Accept: text/javascript, application/javascript, */*',
		'Accept-Language: en-US,en;q=0.8',
		'Content-Type: application/xml',
		)
	);
	curl_setopt($process, CURLOPT_HEADER, 1);
	curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
	$return = curl_exec($process);
	curl_close($process);

	
	if ($return === FALSE) {
		// fail to do http request.
		return FALSE;
	}
	
	// strip useless header and footer from the response body.
	$return = preg_split("/'/", $return, 2);
	$return = $return[1];
	// strip fotter
	$return = preg_split("/';/", $return, 2);
	$return = $return[0];
	
	echo $return;
	
	// parse the response body.
	$hosts = preg_split('/,/', $return);
	
	// pattern:
	// i have identified the following distinct patterns:
	// a) <1>ML006          >10.0.0.160>9C:4E:36:16:07:88>0>0>0
	// b) <6>>10.0.0.206>A0:ED:CD:64:03:76>0>0>0
	// c) <1>ML002           WORKGROUP      >10.0.0.168>9C:4E:36:1F:D4:80>0>0>0
	//
	// observation:
	// - seems the first element is something like "<n>" where n is a number.
	// - for the <n> thing, seems 1 = host name, 6 = IP address.
	// - everything afterwards are delimited by the character ">" (greater than).
	
	// grep the leading <n> element
	foreach ($hosts as $host) {
		
		$retval = preg_match('/<(.*?)>(.*)/', $host, $matches);
		
		if ($retval === FALSE) {
			echo "error parsing host string '$host'";
			continue;
		}
		
		$recordType = $matches[1];
		
		$parts = preg_split("/>/", $matches[2], 6);
		
		$hostname = trim($parts[0]);
		$ip = $parts[1];
		$mac = $parts[2];
		
		echo "recordType = $recordType\n";
		echo "hostname = [[$hostname]]\n";
		echo "mac = $mac\n";
		echo "ip = $ip\n";
	}
	
	print_r($hosts);
	
}






function asus_router_fetch_dhcp_leases($router_ip, $username, $password) {
	
	// URL: http://10.0.0.1/getdhcpLeaseInfo.asp
	$url = 'http://' . $router_ip . '/getdhcpLeaseInfo.asp?_=' . time();
	
	$process = curl_init($url);
	curl_setopt($process, CURLOPT_HTTPHEADER,
		array(
			'Accept: */*',
			'Accept-Language: en-US,en;q=0.8'
		)
	);
	curl_setopt($process, CURLOPT_HEADER, 1);
	curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
	$return = curl_exec($process);
	curl_close($process);
	
	
	if ($return === FALSE) {
		// fail to do http request.
		return FALSE;
	}
	
	
	// response is XML
	// <?xml version="1.0" ? >
	// <dhcplease>
	//  <client>
	//   <mac>value=a0:ed:cd:64:03:76</mac>
	//   <hostname>value=Catherines-i5</hostname>
	//  </client>
	//  <client>
	//    ..
	//  </client>
	// </dhcplease>
	
	
// 	echo "\n[[[$return]]\n\n";
	
	$return = preg_split('/\?>/', $return);

	$return = $return[1];
	
	$xmlDoc = simplexml_load_string($return);
	
	if ($xmlDoc === FALSE) {
		echo "fail to parse XML\n";
		
		foreach(libxml_get_errors() as $error) {
			echo "\t", $error->message;
		}		
		
		return FALSE;
	}
	
	
	$list = array();
	
	if ($xmlDoc->client != null) {
		
		foreach ($xmlDoc->client as $client) {
			
			$o = array();
			$o['mac'] = trim($client->mac);
			$o['hostname'] = trim($client->hostname);
			
			$o['mac'] = preg_replace('/^value=/', '', $o['mac']);
			$o['hostname'] = preg_replace('/^value=/', '', $o['hostname']);
			
			// skip garbage records.
			if (strtoupper($o['mac']) == 'NONE') {
				continue;
			}
			
			
			if ($o['hostname'] == '*') {
				$o['hostname'] = null;
			}
			
			$list[] = $o;
		}
		
	}
	
	return $list;
	
}




function update_scanned_host_with_dhcp_lease($list) {
	
	if ($list == null  || count($list) == 0) return;
	
	// insert into database
	$db = new FileDb();
	$db->open();
	
	$retval = $db->updateHostnameWithMac($list);
	
	$db->close();
	
	return $retval;
	
}