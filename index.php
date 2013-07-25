<?php
require_once 'bootstrap.php';

require_lib('db');
require_lib('nmap');


$db = new FileDb();
$db->open();

// lots of database query.
$activeHostCount = $db->getActiveHostCount();
$activeHostActualCount = $db->getActiveHostTotalCount();
$activeHosts = $db->getActiveHostsWithNickName();

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
            <h2>Network States</h2>
            <hr />
            Active Hosts: <span id="active_host"><?php echo $activeHostCount;?></span>
            <br />
            (total: <span id="active_host_actual"><?php echo $activeHostActualCount;?></span>, filtered <span id="active_host_filtered"><?php echo $activeHostActualCount - $activeHostCount;?></span>)
            <br />
            last scanned: <span id="last_scan_at">0</span>
            
            <br/><br/>
            
            Host Details:
            <table>
            	<thead>
            		<tr>
            			<th>Who</th>
            			<th>IP</th>
            			<th>Host</th>
            		</tr>
            	</thead>
            	<tbody>
            		<?php
            			foreach ($activeHosts as $activeHost) {
            		?>
            		<tr>
            			<td><?php echo $activeHost['nickname'];?></td>
            			<td><?php echo $activeHost['ipv4'];?></td>
            			<td><?php echo $activeHost['hostname'];?></td>
            		</tr>
            		<?php
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