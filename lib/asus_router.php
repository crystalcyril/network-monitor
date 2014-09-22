<?php

if (!defined('BOOTSTRAPPED')) {
	exit('should not include this script directly');
}


/**
 * Fetch client list from asus router.
 * 
 * This implementation will do the following:
 *
 * 1. Fetch the client list from URL /update_clients.asp?, which contains 
 *    MAC and IP address.
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
	
	// echo $return;
	
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
	
	$result = array();
	
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
		$ip = trim($parts[1]);
		$mac = trim($parts[2]);
		
		$o['ipv4'] = $ip;
		$o['mac'] = strtoupper($mac);
		$o['hostname'] = $hostname;
		
		$result[] = $o;

		// debug
// 		echo "recordType = $recordType\n";
// 		echo "hostname = [[$hostname]]\n";
// 		echo "mac = $mac\n";
// 		echo "ip = $ip\n";
	}
	
	//print_r($hosts);
	
	_asus_router_logout($router_ip);
	
	
	return $result;
	
}




/**
 * This function assus the router and obtains the DHCP lease information, 
 * which contains the MAC address and hostname mapping. The result will be
 * returned as an array which each element is another array containing
 * the following keys:
 *
 * mac      - the mac address
 * hostname - corresponding host name of the MAC address.
 *
 *
 */
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
	
	
 	//echo "\n[[[$return]]\n\n";
	
	$return = preg_split('/\?>/', $return);
	
	echo  "split count: " . count($return) . "\n";
	
	if (count($return) == 2) {
		$return = $return[1];
	} else {
		
		echo "\n[[[" . $return[0] . "]\n\n";
		
		$return = $return[0];
	}
	
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
	
	
	
	_asus_router_logout($router_ip);
	
	return $list;
	
}



/**
 * Read the DHCP leases from the router.
 *
 * The source of the data comes from the URL 
 * http://10.0.0.1/Main_DHCPStatus_Content.asp
 */
function asus_router_get_dhcp_lease($router_ip, $username, $password) {

	// http://10.0.0.1/Main_DHCPStatus_Content.asp
	$url = 'http://' . $router_ip . '/Main_DHCPStatus_Content.asp?_=' . time();

	// this is a proper HTML web page.
	$process = _asus_router_create_http_connection($url, $username, $password);
	$webpage = curl_exec($process);
	curl_close($process);

	if ($webpage === FALSE) {
		// fail to do http request.
		return FALSE;
	}

	// now we need to extract information from the fetched web page.
	// we look for the following pattern:
	// - string "Stations List" - header
	// - string "</textarea>" - footer
	// 
	// the sample content is as follow:
	//
	//
	// <textarea cols="63" rows="25" readonly="readonly" wrap=off style="font-family:'Courier New', Courier, mono; font-size:13px;background:#475A5F;color:#FFFFFF;">Expires   MAC Address       IP Address      Host name
	// 20:58:24  9c:b7:0d:d0:ab:d8 10.0.0.164      ML006
	// 23:48:33  54:26:96:06:97:1f 10.0.0.157      *
	// 21:13:32  d0:57:85:6a:d5:89 10.0.0.190      android-809d3ef31490731d
	// 16:21:57  ac:22:0b:5f:6d:b0 10.0.0.180      android-e394761c9f7c8896
	// .
	// ... (omitted) ...
	// .
	// 16:31:03  84:a6:c8:10:ce:b7 10.0.0.155      ML015
	// 23:00:19  00:37:6d:6b:58:fb 10.0.0.187      android-405240901807fb2e
	// </textarea>
	//
	$match_result = preg_match('/<textarea\s.*>(.*)<\/textarea/sim', $webpage, $matches);
	if ($match_result !== 1) {
		echo "unable to grep wireless station list\n";
		echo "read web page data is: [[[$webpage]]]\n\n";
		return FALSE;
	}
	if (count($matches) != 2) {
		return FALSE;
	}
	$webpage = trim($matches[1]);
//	echo "extract webpage: $webpage\n";

	//
	// parse the content.
	//
	$ret = array();
	$raw_client_list = preg_split("/\r\n|\n|\r/", $webpage);
	foreach ($raw_client_list as $raw_client_line) {

//		echo "raw_client_line = [[$raw_client_line]] (count=" . count($raw_client_parts) . ")\n";

		$raw_client_line = trim($raw_client_line);
		$raw_client_parts = preg_split('/\s+/', $raw_client_line);

		// skip invalid lines
		if ($raw_client_parts == null || count($raw_client_parts) != 4) continue;

		$o = array();

		// the 1st element is hostname
		$hostname = trim($raw_client_parts[0]);
		if ('*' == $hostname) {
			$hostname = null;
		}

		$o['hostname'] = $hostname;
		// the second element is IP (v4) address.
		$ip = trim($raw_client_parts[1]);
		if (strlen($ip) < 7) {
			echo "ip address '$ip' should not be shorter than 7 characters! skipped\n";
			continue;
		}
		$o['ipv4'] = $ip;

		// the third element is MAC address which is 12 + 5 = 17 characters.
		$mac = trim($raw_client_parts[2]);
		if (strlen($mac) != 17) {
			echo "mac address '$mac' is not 17 character long! skipped\n";
			continue;
		}
		$mac = strtoupper($mac);
		$o['mac'] = $mac;

		$ret[] = $o;

	}

	return $ret;

}


/**
 * Read the active wireless clients from the router.
 *
 * The source of the data comes from the URL 
 * http://10.0.0.1/Main_WStatus_Content.asp.
 *
 * As of firmware version 3.0.0.4.360, the page reflects the real-time 
 * connection status of any wireless clients.
 */
function asus_router_get_wireless_client_list($router_ip, $username, $password) {

	// http://10.0.0.1/Main_WStatus_Content.asp?_=ddd
	$url = 'http://' . $router_ip . '/Main_WStatus_Content.asp?_=' . time();

	// this is a proper HTML web page.
	
	$process = _asus_router_create_http_connection($url, $username, $password);

	$webpage = curl_exec($process);
	curl_close($process);

	if ($webpage === FALSE) {
		// fail to do http request.
		return FALSE;
	}

	// now we need to extract information from the fetched web page.
	// we look for the following pattern:
	// - string "Stations List" - header
	// - string "</textarea>" - footer
	// 
	// the sample content is as follow:
	// Stations List			   
	// ----------------------------------------
	// MAC               PSM PhyMode BW  MCS SGI STBC Rate Connect Time
	// 00:37:6D:6B:58:FB Yes HTMIX   20M   7 NO  NO    65M 00:00:22
	// 9C:4E:36:1F:D4:80 NO  HTMIX   20M  15 NO  NO   130M 03:10:49
	// 60:67:20:75:FF:C2 NO  HTMIX   20M  15 NO  NO   130M 07:29:48
	// .
	// ... (omitted) ...
	// .
	// 
	// 5 GHz radio is disabled
	// 
	// </textarea>
	//
	$match_result = preg_match('/Stations\sList(.*)<\/textarea/sim', $webpage, $matches);
	if ($match_result !== 1) {
		echo "unable to grep wireless station list\n";
		return FALSE;
	}
	if (count($matches) != 2) {
		return FALSE;
	}
	$webpage = trim($matches[1]);
	//echo "extract webpage: $webpage\n";

	$ret = array();

	// split.
	$raw_client_list = preg_split("/\r\n|\n|\r/", $webpage);
	foreach ($raw_client_list as $raw_client_line) {

		$raw_client_line = trim($raw_client_line);
		$raw_client_parts = preg_split('/\s+/', $raw_client_line);

		// skip invalid lines
		if ($raw_client_parts == null || count($raw_client_parts) != 9) continue;

		$o = array();

		// the first element is MAC address which is 12 + 5 = 17 characters.
		$mac = trim($raw_client_parts[0]);

		if (strlen($mac) != 17) {
			continue;
		}
		$mac = strtoupper($mac);
		$o['mac'] = $mac;

		$ret[] = $o;
	}

	return $ret;

}




function _asus_router_logout($router_ip) {

	$url = 'http://' . $router_ip . '/Logout.asp';
	
	$process = curl_init($url);
	curl_setopt($process, CURLOPT_HTTPHEADER,
		array(
			'Accept: */*',
			'Accept-Language: en-US,en;q=0.8'
		)
	);
	curl_setopt($process, CURLOPT_HEADER, 1);
	//curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	//curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
	$return = curl_exec($process);
	curl_close($process);	
	
}

function _asus_router_create_http_connection($url, $username, $password) {

	$process = curl_init($url);

	curl_setopt($process, CURLOPT_HTTPHEADER,
		array(
			'Accept: */*',
			'Accept-Language: en-US,en;q=0.8'
		)
	);
	curl_setopt($process, CURLOPT_HEADER, 1);

	// authentication
	if (!empty($username)) {
		curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
	}

	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

	return $process;

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

