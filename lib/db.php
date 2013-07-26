<?php


if (!defined('BOOTSTRAPPED')) {
	exit('should not include this script directly');
}


function guid(){
	if (function_exists('com_create_guid')){
		return com_create_guid();
	}else{
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = chr(123)// "{"
		.substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12)
		.chr(125);// "}"
		return $uuid;
	}
}


class FileDb {

	private $db_file;

	private $db;

	function __construct() {

		global $conf;

		$this->db_file = DATA_DIR . DS . 'data.db';
	}

	public function open() {
		$db = new PDO('sqlite:' . $this->db_file);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			
		//
		// create tables.
		//
		// the scanned host table.
		$tables[] = 'CREATE TABLE host (id varchar(40), ipv4 varchar(15), ipv6 varchar(45), mac varchar(17), hostname varchar(255), nic_vendor varchar(255), last_scan DATETIME)';
		// host filters.
		$tables[] = 'CREATE TABLE host_filter (id varchar(40), ipv4 varchar(15), ipv6 varchar(45), mac varchar(17), hostname varchar(255))';
		// host alias.
		$tables[] = 'CREATE TABLE host_alias (id varchar(40), nickname varchar(50), ipv4 varchar(15), ipv6 varchar(45), mac varchar(17), hostname varchar(255))';
		
		if ($tables != null) {
			foreach ($tables as $table) {
				try {
					$db->exec($table);
				} catch (PDOException $e) {
					// XXX handle exception properly.
				}
			} // for each table
		}
		
		$this->db = $db;
	}
	
	
	public function clearScannedHosts() {
		
		$sql = "DELETE FROM host";
		$stmt = $this->db->prepare($sql);
		$retval = $stmt->execute();
		
		if ($retval === FALSE) return FALSE;
		
		return TRUE;
		
	}
	
	public function insertScannedHost($host) {
		
		$insert = "INSERT INTO host (id, ipv4, ipv6, mac, hostname, nic_vendor, last_scan) VALUES (:id, :ipv4, :ipv6, :mac, :hostname, :nic_vendor, datetime('now', 'localtime'))";
		$stmt = $this->db->prepare($insert);
		if ($stmt === FALSE) {
			return FALSE;
		}
		
		$id = guid();
		
		$stmt->bindParam(':id', $id);
		$stmt->bindParam(':ipv4', $host['ipv4']);
		$stmt->bindParam(':ipv6', $host['ipv6']);
		$stmt->bindParam(':mac', $host['mac']);
		$stmt->bindParam(':hostname', $host['hostname']);
		$stmt->bindParam(':nic_vendor', $host['nic_vendor']);
		
		$retval = $stmt->execute();
		$stmt = null;
		
		if ($retval === FALSE) return FALSE;
		return $id;	// success
	}
	
	/**
	 * 
	 * 
	 * @param array $list
	 */
	public function updateHostnameWithMac($list) {
		
		// do nothing for empty list.
		if ($list == null || empty($list)) return;

		$sql = "UPDATE host SET hostname = :hostname WHERE LOWER(mac) = :mac AND (hostname IS NULL OR hostname = '')";
		$stmt = $this->db->prepare($sql);
		
		$updateCount = 0;
		
		foreach ($list as $item) {
			
			if (empty($item['mac']) || empty($item['hostname'])) {
				continue;
			}
			
			$realMac = strtolower($item['mac']);
			$stmt->bindParam(':mac', $realMac);
			$stmt->bindParam(':hostname', $item['hostname']);
			$retval = $stmt->execute();
			
			if ($retval != FALSE) {
				$updateCount++;
			}
		}
		
		return $updateCount;
		
	}
	
	
	public function updateHostByIpV4($list) {
	
		// do nothing for empty list.
		if ($list == null || empty($list)) return;
	
		$sql_update_hostname = "UPDATE host SET hostname = :hostname WHERE LOWER(mac) = :mac AND (hostname IS NULL OR hostname = '')";
		
		$stmt = $this->db->prepare($sql_update_hostname);
	
		$updateCount = 0;
	
		foreach ($list as $item) {
				
			if (empty($item['mac']) || empty($item['hostname'])) {
				continue;
			}
				
			$realMac = strtolower($item['mac']);
			$stmt->bindParam(':mac', $realMac);
			$stmt->bindParam(':hostname', $item['hostname']);
			$retval = $stmt->execute();
				
			if ($retval != FALSE) {
				$updateCount++;
			}
		}
	
		return $updateCount;
	
	}	
	
	
	
	public function getActiveHostCount() {
		
		$sql = "SELECT COUNT(id) FROM host "
				. "WHERE ipv4 NOT IN (SELECT DISTINCT ipv4 FROM host_filter WHERE ipv4 IS NOT NULL) "
				. " AND ipv6 NOT IN (SELECT DISTINCT ipv6 FROM host_filter WHERE ipv6 IS NOT NULL)"
				. " AND mac NOT IN (SELECT DISTINCT mac FROM host_filter WHERE mac IS NOT NULL)";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetch(PDO::FETCH_NUM);
		return $rows[0];
	}
	
	
	public function getActiveHostTotalCount() {
	
		$sql = "SELECT COUNT(id) FROM host";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetch(PDO::FETCH_NUM);
		return $rows[0];
	}	

	
	public function getActiveHosts() {
		$sql = "SELECT * FROM host ";
		$sql .= "WHERE ipv4 NOT IN (SELECT DISTINCT ipv4 FROM host_filter WHERE ipv4 IS NOT NULL) "
				. " AND ipv6 NOT IN (SELECT DISTINCT ipv6 FROM host_filter WHERE ipv6 IS NOT NULL)"
				. " AND mac NOT IN (SELECT DISTINCT mac FROM host_filter WHERE mac IS NOT NULL)";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll();
		return $result;
	}
	
	public function getActiveHostsWithNickName() {
		$sql = "
		SELECT * FROM
		(
		SELECT s.ipv4, s.hostname, s.mac, s.nic_vendor, a.nickname
		FROM host s, host_alias a
		WHERE s.ipv4=a.ipv4 AND a.ipv4 IS NOT NULL AND a.ipv4 <> ''
		
		UNION
		
		SELECT s.ipv4, s.hostname, s.mac, s.nic_vendor, a.nickname
		FROM host s, host_alias a
		WHERE s.ipv6=a.ipv6 AND a.ipv6 IS NOT NULL AND a.ipv6 <> ''
		
		UNION
		
		SELECT s.ipv4, s.hostname, s.mac, s.nic_vendor, a.nickname
		FROM host s, host_alias a
		WHERE LOWER(s.mac)=LOWER(a.mac) AND a.mac IS NOT NULL AND a.mac <> ''
		
		UNION
		
		SELECT s.ipv4, s.hostname, s.mac, s.nic_vendor, a.nickname
		FROM host s, host_alias a
		WHERE s.hostname=a.hostname AND a.hostname IS NOT NULL AND a.hostname <> ''
		
		UNION
		
		SELECT s.ipv4, s.hostname, s.mac, s.nic_vendor, null as nickname
		FROM host s
		WHERE (s.mac IS NULL OR LOWER(s.mac) NOT IN (SELECT DISTINCT LOWER(mac) FROM host_alias WHERE mac IS NOT NULL AND mac <> ''))
		AND (s.ipv4 IS NULL OR LOWER(s.ipv4) NOT IN (SELECT DISTINCT LOWER(ipv4) FROM host_alias WHERE ipv4 IS NOT NULL AND ipv4 <> ''))
		AND (s.ipv6 IS NULL OR LOWER(s.ipv6) NOT IN (SELECT DISTINCT LOWER(ipv6) FROM host_alias WHERE ipv6 IS NOT NULL AND ipv6 <> ''))
		AND (s.hostname IS NULL OR LOWER(s.hostname) NOT IN (SELECT DISTINCT LOWER(hostname) FROM host_alias WHERE hostname IS NOT NULL AND hostname <> ''))
		
		)";
		
		$sql .= "WHERE ipv4 NOT IN (SELECT DISTINCT ipv4 FROM host_filter WHERE ipv4 IS NOT NULL AND ipv4 <> '') "
				. " AND mac NOT IN (SELECT DISTINCT mac FROM host_filter WHERE mac IS NOT NULL AND mac <> '')"
				. " AND hostname NOT IN (SELECT DISTINCT hostname FROM host_filter WHERE hostname IS NOT NULL AND hostname <> '')"
				;
				
		$sql .= "ORDER BY nickname DESC";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll();
		return $result;
	}
	
	
	public function updateLastScan() {
		
		
	}
	
	
	public function close() {
		$file_db = null;
	}

}
