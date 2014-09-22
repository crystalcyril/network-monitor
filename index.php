<?php
require_once 'bootstrap.php';

require_lib('db');
require_lib('nmap');



$type = @$_REQUEST['type'];
if (empty($type)) {
	$type = 'active';
}



$db = new FileDb();
$db->open();

// lots of database query.
$hostCount = $db->getHostCount();
$activeHostCount = $db->getActiveHostCount();
$activeHostActualCount = $db->getActiveHostTotalCount();

$activeHosts = null;
if ('all' == $type) {
	$activeHosts = $db->getHostsWithNickName();
} else {
	$activeHosts = $db->getActiveHostsWithNickName();
}

// find the last scan time
$lastScannedAt = null;

foreach ($activeHosts as $activeHost) {
	if (!empty($activeHost['last_scan'])) {
		$lastScannedAt = $activeHost['last_scan'];
		break;
	}
}


?>
<!DOCTYPE html>
<!--[if IE 8]> 				 <html class="no-js lt-ie9" lang="en" > <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en" > <!--<![endif]-->
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>Network Monitor</title>
  <link rel="stylesheet" href="s/css/foundation.css">
  <script src="s/js/vendor/custom.modernizr.js"></script>
</head>
<body>

    <div class="row">
        <div class="large-12 columns">
            Active Hosts: <span id="active_host"><a href="index.php"><?php echo $activeHostCount;?></a> (Total: <span id="active_host"><a href="?type=all"><?php echo $hostCount;?></span></a>)</span>
            <br />
            (total: <span id="active_host_actual"><?php echo $activeHostActualCount;?></span>, filtered <span id="active_host_filtered"><?php echo $activeHostActualCount - $activeHostCount;?></span>)
            <br />
            last scanned: <span id="last_scan_at"><?php if (!empty($lastScannedAt)) echo $lastScannedAt; ?></span>
            
            <br/><br/>
            
            Host Details:
            <table>
            	<thead>
            		<tr>
				<th>#</th>
            			<th>Who</th>
            			<th>IP</th>
            			<th>Host</th>
            		</tr>
            	</thead>
            	<tbody>
            		<?php
				$i = 1;
            			foreach ($activeHosts as $activeHost) {

							$macInfo = 'MAC: ' . strtoupper($activeHost['mac']);
							if ($activeHost['nic_vendor'] != null) {
								$macInfo .= ' (' . htmlspecialchars($activeHost['nic_vendor']) . ')';
							}
            		?>
            		<tr>
				<td><?php echo $i; ?>
            			<td><?php echo $activeHost['nickname'];?><br/><!--<small><?php echo $activeHost['last_scan'];?></small>--></td>
            			<td><span title="<?php echo $macInfo;?>"><?php echo $activeHost['ipv4'];?></span></td>
            			<td><?php echo $activeHost['hostname'];?></td>
            		</tr>
            		<?php
					$i++;
            			} 
            		?>
            	</tbody>
            </table>
        </div>
    </div>

</body>
</html>
<?php
	$db->close();
?>
