<?php
set_time_limit(0);
function get_ubnt_stats($ip, $logins)
{
    $cookie_file = tempnam('/tmp', 'freqin-cookie');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://' . $ip);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    $result = curl_exec($ch);
    if (!strstr($result, 'AIROS_SESSIONID')) {
        unlink($cookie_file);
        return false;
    }
    
    $radio_data = 0;
    foreach ($logins as $login) {
        $login_post_data = array(
            'uri' => '/status.cgi',
            'username' => $login['user'],
            'password' => $login['pass'],
            'Submit' => 'Login'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Expect: '));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, 'http://' . $ip . '/login.cgi');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $login_post_data);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $result = curl_exec($ch);
		
// ATTEMPTING SSH
		$command = "cat /var/etc/board.info | grep board.name | cut -d= -f2";
		$port = 22;
		$user = $_POST['username'];
		$pass = $_POST['password'];

		$connection = ssh2_connect($ip, $port);
		ssh2_auth_password($connection, $user, $pass);
		
		$model = ssh2_exec($connection, $command);
			if($model == 0) {
				echo "An error has occured.";
			}
			stream_set_blocking($model, true);
// END ATTEMPT

		if ($result) {
            $data = json_decode($result);
// IF STATEMENT FOR <= 5.3.5 DEVICES
            if (array_key_exists('lan', $data)) {
                $radio_data = array(
                    'name' => $data->host->hostname,
                    'mode' => $data->wireless->mode == 'sta' ? 'Station' : 'Access Point',
                    'fw' => $data->host->fwversion,
                    'uptime' => sprintf('%.2f', $data->host->uptime / 86400) . ' days',
					'dfs' => 'N',
                    'freq' => preg_replace('/[^0-9]+/', '', $data->wireless->frequency),
                    'channel' => $data->wireless->channel,
					'width' => $data->wireless->chwidth,
                    'signal' => $data->wireless->signal,
                    'noise' => $data->wireless->noisef,
					'wds' => $data->wireless->wds,
					'ssid' => $data->wireless->essid,
					'security' => $data->wireless->security,
					'distance' => sprintf('%.2f', $data->wireless->distance * 0.000621371192) . 'mi',
					'connections' => $data->wireless->count,
					'ccq' => sprintf('%.1f', $data->wireless->ccq / 10) . '%',
					'ame' => $data->wireless->polling->enabled,
					'amq' => $data->wireless->polling->quality,
					'amc' => $data->wireless->polling->capacity,
					'lan' => isset($data->lan->status[0]->plugged) ? ($data->lan->status[0]->plugged ? $data->lan->status[0]->speed . "mbps-" . ($data->lan->status[0]->duplex ? 'Full' : 'Half') : 'Unplugged') : $data->lan->status[0],
					'lan_mac' => $data->lan->hwaddr,
					'wlan_mac' => $data->wlan->hwaddr,
					'tx' => $data->wireless->txrate,
					'rx' => $data->wireless->rxrate,
					'retries' => $data->wireless->stats->tx_retries,
					'err_other' => $data->wireless->stats->err_other,
					'chains' => $data->wireless->chains,
					'model' => trim(stream_get_contents($model)),
					'gps' => 0
					);

			} else { // ELSE STATEMENT FOR > 5.3.5 DEVICES 			
// IF GPS...
					if(array_key_exists('gps', $data)) {
						$radio_data = array(
						'name' => $data->host->hostname,
						'mode' => $data->wireless->mode == 'sta' ? 'Station' : 'Access Point',
						'fw' => $data->host->fwversion,
						'uptime' => sprintf('%.2f', $data->host->uptime / 86400) . ' days',
						'dfs' => $data->wireless->dfs,
						'freq' => preg_replace('/[^0-9]+/', '', $data->wireless->frequency),
						'channel' => $data->wireless->channel,
						'width' => $data->wireless->chwidth,
						'signal' => $data->wireless->signal,
						'noise' => $data->wireless->noisef,
						'wds' => $data->wireless->wds,
						'ssid' => $data->wireless->essid,
						'security' => $data->wireless->security,
						'distance' => sprintf('%.2f', $data->wireless->distance * 0.000621371192) . 'mi',
						'connections' => $data->wireless->count,
						'ccq' => sprintf('%.1f', $data->wireless->ccq / 10) . '%',
						'ame' => $data->wireless->polling->enabled,
						'amq' => $data->wireless->polling->quality,
						'amc' => $data->wireless->polling->capacity,
						'lan' => $data->interfaces[1]->status->speed . "mbps-" . ($data->interfaces[1]->status->duplex ? 'Full' : 'Half'),
						'lan_mac' => $data->interfaces[1]->hwaddr,
						'wlan_mac' => $data->interfaces[3]->hwaddr,
						'tx' => $data->wireless->txrate,
						'rx' => $data->wireless->rxrate,
						'retries' => $data->wireless->stats->tx_retries,
						'err_other' => $data->wireless->stats->err_other,
						'chains' => $data->wireless->chains,
						'model' => trim(stream_get_contents($model)) . " GPS",
						'gps' => 1
					);
// TWO ETHERNETS, NO GPS.  GPS WOULD'VE BEEN MATCHED BY ABOVE CONDITION					
					} elseif (($data->interfaces[3]->ifname) == 'wifi0') {
						$radio_data = array(
						'name' => $data->host->hostname,
						'mode' => $data->wireless->mode == 'sta' ? 'Station' : 'Access Point',
						'fw' => $data->host->fwversion,
						'uptime' => sprintf('%.2f', $data->host->uptime / 86400) . ' days',
						'dfs' => $data->wireless->dfs,
						'freq' => preg_replace('/[^0-9]+/', '', $data->wireless->frequency),
						'channel' => $data->wireless->channel,
						'width' => $data->wireless->chwidth,
						'signal' => $data->wireless->signal,
						'noise' => $data->wireless->noisef,
						'wds' => $data->wireless->wds,
						'ssid' => $data->wireless->essid,
						'security' => $data->wireless->security,
						'distance' => sprintf('%.2f', $data->wireless->distance * 0.000621371192) . 'mi',
						'connections' => $data->wireless->count,
						'ccq' => sprintf('%.1f', $data->wireless->ccq / 10) . '%',
						'ame' => $data->wireless->polling->enabled,
						'amq' => $data->wireless->polling->quality,
						'amc' => $data->wireless->polling->capacity,
						'lan' => $data->interfaces[1]->status->speed . "mbps-" . ($data->interfaces[1]->status->duplex ? 'Full' : 'Half'),
						'lan_mac' => $data->interfaces[1]->hwaddr,
						'wlan_mac' => $data->interfaces[3]->hwaddr,
						'tx' => $data->wireless->txrate,
						'rx' => $data->wireless->rxrate,
						'retries' => $data->wireless->stats->tx_retries,
						'err_other' => $data->wireless->stats->err_other,
						'chains' => $data->wireless->chains,
						'model' => trim(stream_get_contents($model)),
						'gps' => 0
					);
					} else {
// ELSE {DEVICE ONLY HAS ONE ETHERNET PORT & IS NOT GPS}
						$radio_data = array(
						'name' => $data->host->hostname,
						'mode' => $data->wireless->mode == 'sta' ? 'Station' : 'Access Point',
						'fw' => $data->host->fwversion,
						'uptime' => sprintf('%.2f', $data->host->uptime / 86400) . ' days',
						'dfs' => $data->wireless->dfs,
						'freq' => preg_replace('/[^0-9]+/', '', $data->wireless->frequency),
						'channel' => $data->wireless->channel,
						'width' => $data->wireless->chwidth,
						'signal' => $data->wireless->signal,
						'noise' => $data->wireless->noisef,
						'wds' => $data->wireless->wds,
						'ssid' => $data->wireless->essid,
						'security' => $data->wireless->security,
						'distance' => sprintf('%.2f', $data->wireless->distance * 0.000621371192) . 'mi',
						'connections' => $data->wireless->count,
						'ccq' => sprintf('%.1f', $data->wireless->ccq / 10) . '%',
						'ame' => $data->wireless->polling->enabled,
						'amq' => $data->wireless->polling->quality,
						'amc' => $data->wireless->polling->capacity,
						'lan' => $data->interfaces[1]->status->speed . "mbps-" . ($data->interfaces[1]->status->duplex ? 'Full' : 'Half'),
						'lan_mac' => $data->interfaces[1]->hwaddr,
						'wlan_mac' => $data->interfaces[2]->hwaddr,
						'tx' => $data->wireless->txrate,
						'rx' => $data->wireless->rxrate,
						'retries' => $data->wireless->stats->tx_retries,
						'err_other' => $data->wireless->stats->err_other,
						'chains' => $data->wireless->chains,
						'model' => trim(stream_get_contents($model)),
						'gps' => 0
					);

					}
			}
    }
	
	unlink($cookie_file);
    return $radio_data;
}
}


// NEW CODE TO TRY AND GRAB PEER INFORMATION ##################################################################################################################################################################################################


function get_peer_stats($ip, $logins)
{
    $cookie_file = tempnam('/tmp', 'peer-cookie');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://' . $ip);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    $result = curl_exec($ch);
    if (!strstr($result, 'AIROS_SESSIONID')) {
        unlink($cookie_file);
		return false;
    } 
    
    $peer_data = 0;
    foreach ($logins as $login) {
        $login_post_data = array(
            'uri' => '/sta.cgi',
            'username' => $login['user'],
            'password' => $login['pass'],
            'Submit' => 'Login'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Expect: '));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, 'http://' . $ip . '/login.cgi');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $login_post_data);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $presult = curl_exec($ch);
        if ($presult) {
            $peer = json_decode($presult);
            if (isset($peer[0]->aprepeater)) {
                $peer_data = array(
                    'name' => $peer[0]->name,
					'mac' => $peer[0]->mac,
					'ip' => $peer[0]->lastip,
					'rep' => $peer[0]->aprepeater
				);
				} else {
				$peer_data = array(
					'name' => $peer[0]->name,
					'mac' => $peer[0]->mac,
					'ip' => $peer[0]->lastip,
					'rep' => 'A'
				);
            }
        } 			
    }
    unlink($cookie_file);
    return $peer_data;
}
	
?>
<html>
<head>
<title>Freqin'</title>
<style type="text/css">
body {
    background-color: #fff;
    color: #000;
    font: 8pt Verdana;
}
td {
    color: #000;
    font: 8pt Verdana;
}
</style>
</head>
<body>

<?php
if ($_POST) {
    $radio_data = array();
	$peer_data = array();
    
    echo str_repeat(' ', 512);
    ob_flush();
    flush();
    
    $logins = array(
        array(
            'user' => $_POST['username'],
            'pass' => $_POST['password']
        )
    );
    if ($_POST['username2'] && $_POST['password2']) {
        $logins[] = array(
            'user' => $_POST['username2'],
            'pass' => $_POST['password2']
        );
    }

function iprange($ip,$mask=24,$return_array=FALSE) {
    $corr=(pow(2,32)-1)-(pow(2,32-$mask)-1);
    $first=ip2long($ip) & ($corr);
    $length=pow(2,32-$mask)-1;
    if (!$return_array) {
    return array(
        'first'=>$first,
        'size'=>$length+1,
        'last'=>$first+$length,
        'first_ip'=>long2ip($first),
        'last_ip'=>long2ip($first+$length)
        );
    }
    $ips=array();
    for ($i=0;$i<=$length;$i++) {
        $ips[]=long2ip($first+$i);
    }
    return $ips;
}


function ping($host, $port, $timeout) {
        $tB = microtime(true);
        $fP = fSockOpen($host, $port, $errno, $errstr, $timeout);
        if (!$fP) {
                return 0;
        }
        $tA = microtime(true);
        return round((($tA - $tB) * 1000), 0)." ms";
}


$test = iprange($_POST['ips'], $_POST['netmask'],TRUE);

for($i = 0; $i < count($test); $i++) {
        ob_flush();
        flush();
        if($rtadata = ping($test[$i], 80, 0.075)) {
                echo $test[$i] . ' - <span style="color:green"> Online. RTA: </span>' . $rtadata . ' Retrieving Data: ';
				if($data = get_ubnt_stats($test[$i], $logins)) {
					$radio_data[$test[$i]] = $data;
					echo ' <span style="color:green">Success! </span> ';
				} else {
					echo ' <span style="color:red">Failed! </span> ';
				}
				echo $test[$i] . ' - Retrieving Peer Data: ';
				if($pdata = get_peer_stats($test[$i], $logins)) {
					$peer_data[$test[$i]] = $pdata;
					echo ' <span style="color:green">Success! </span> ';
				} else {
					echo ' <span style="color:red">Failed!</a> </span> ';
				}
		        $online_stack[] = $test[$i];
        } else {
                echo $test[$i] . ' - <span style="color:red">' . 'Offline' . '.</span>';
                $offline_stack[] = $test[$i];
        }
        echo "<br />";
        ob_flush();
        flush();
}

/*
$ips = preg_split('/[\r\n\s]+/', $_POST['ips']);
foreach ($ips as $ip) {
if (!($ip = trim($ip))) continue;
if (strstr($ip, '/')) {
list($net,$mask) = explode('/', $ip);
$nmask = pow(2, 32-$mask);
for ($host = ip2long($net)+1; $host < ip2long($net)+$nmask-1; $host++) {
echo long2ip($host) . "...";
ob_flush();
flush();
if ($data = get_ubnt_stats(long2ip($host), $logins)) {
$radio_data[long2ip($host)] = $data;
echo '<span style="color:blue">success.</span>';
} else {
echo '<span style="color:red">' . ($data === false ? 'failed' : 'failed: invalid login') . '.</span>';
}
echo "<br />";
ob_flush();
flush();
}
} else {
echo "$ip...";
ob_flush();
flush();
if ($data = get_ubnt_stats($ip, $logins)) {
$radio_data[$ip] = $data;
echo '<span style="color:blue">success.</span>';
} else {
echo '<span style="color:red">' . ($data === false ? 'failed' : 'failed: invalid login') . '.</span>';
}
echo "<br />";
ob_flush();
flush();
}
}
*/
    echo '<script type="text/javascript">location.href=\'#freqin\';</script>';
}
?>

<a name="freqin"><h1>Freqin'</h1></a>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<table>
<tr><td colspan="4"><b>Ip Address / Netmask:</b><br /></td></tr>
<tr><td colspan="4"><textarea name="ips" cols="15" rows="1"><?php echo isset($_POST['ips']) ? $_POST['ips'] : ''; ?></textarea><textarea name="netmask" cols="4" rows="1"><?php echo isset($_POST['netmask']) ? $_POST['netmask'] : '32'; ?></textarea>
</td></tr>
<tr><td><b>Username:</b></td><td><input type="text" size="20" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : 'ubnt'; ?>" /></td><td><b>Password:</b></td><td><input type="password" name="password" value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>" size="20" /></td></tr>
<tr><td><b>Secondary Username:</b></td><td><input type="text" size="20" name="username2" value="<?php echo isset($_POST['username2']) ? $_POST['username2'] : 'ubnt'; ?>" /></td><td><b>Password:</b></td><td><input type="password" name="password2" value="<?php echo isset($_POST['password2']) ? $_POST['password2'] : ''; ?>" size="20" /></td></tr>
</table><br />
<input type="submit" value="Go" />
</form>
<?php
if (isset($radio_data)) {
    echo '<br /><br />';
    echo '<table border="0" cellspacing="1" cellpadding="4" width="100%">';
    echo '<tr align="center" style="background-color:#ddd">';
    echo '<td><b>IP</b></td>';
	echo '<td><b>Peer IP</b></td>';
	echo '<td><b>Model</b></td>';
    echo '<td><b>Name</b></td>';
    echo '<td><b>Mode</b></td>';
    echo '<td><b>FW</b></td>';
    echo '<td><b>Uptime</b></td>';
    echo '<td><b>LAN</b></td>';
	echo '<td><b>LAN MAC</b></td>';
	echo '<td><b><a href="javascript:alert(\'DFS Enabled?\')">?</b></td>';
    echo '<td><b>Freq</b></td>';
	echo '<td><b>Width</b></td>';
	echo '<td><b>Ch</b></td>';
	echo '<td><b>WLAN MAC</b></td>';
    echo '<td><b>Signal</b></td>';
    echo '<td><b>Noise</b></td>';
	echo '<td><b>SSID</b></td>';
	echo '<td><b>Security</b></td>';
	echo '<td><b>Dist</b></td>';
	echo '<td><b><a href="javascript:alert(\'Connections\')">?</b></td>';
	echo '<td><b>Speeds</b></td>';
	echo '<td><b>Chain</b></td>';
	echo '<td><b>Errors</b></td>';
	echo '<td><b>CCQ</b></td>';
	echo '<td><b><a href="javascript:alert(\'airMax Enabled?\')">AME</a></b></td>';
	echo '<td><b><a href="javascript:alert(\'airMax Quality\')">AMQ</a></b></td>';
	echo '<td><b><a href="javascript:alert(\'airMax Capacity\')">AMC</a></b></td>';
	echo '<td><b>GPS</b></td>';
	echo '<td><b>+</b></td>';
    echo '</tr>';
for ($bgcolor = '#fff'; list($ip,$data) = each($radio_data); $bgcolor = $bgcolor == '#fff' ? '#eee' : '#fff') {
        echo '<tr align="center" style="background-color:' . $bgcolor . '">';
        echo '<td><a href="http://' . $ip . '" target="_blank">' . $ip . '</a></td>';
        
		for ($j = 0; list($ip,$pdata) = each($peer_data); $j++) {
			echo '<td>' . $pdata['mac'] . '</td>';
			break;
		}
		
		echo '<td>' . $data['model'] . '</td>';
		echo '<td>' . $data['name'] . '</td>';
		if($data['wds'] == 1) {
			echo '<td>' . $data['mode'] . ' WDS</td>';
		} else {
			echo '<td>' . $data['mode'] . '</td>';
		}
        echo '<td>' . $data['fw'] . '</td>';
        echo '<td>' . $data['uptime'] . '</td>';
        echo '<td>' . $data['lan'] . '</td>';
		echo '<td>' . $data['lan_mac'] . '</td>';
		if($data['dfs'] == 1) {
			echo '<td>Y</td>';
		} elseif ($data['dfs'] == 0) {
			echo '<td>N</td>';
		} else {
			echo '<td>N/A</td>';
		}
        echo '<td>' . $data['freq'] . ' MHz</td>';
		echo '<td>' . $data['width'] . ' MHz</td>';
		echo '<td>' . $data['channel'] . '</td>';
        echo '<td>' . $data['wlan_mac'] . '</td>';
		echo '<td>' . $data['signal'] . ' dBm</td>';
        echo '<td>' . $data['noise'] . ' dBm</td>';
		echo '<td>' . $data['ssid'] . '</td>';
		echo '<td>' . $data['security'] . '</td>';
		echo '<td>' . $data['distance'] . '</td>';
		echo '<td>' . $data['connections'] . '</td>';
		echo '<td>' . $data['tx'] . "/" . $data['rx'] . '</td>';
		echo '<td>' . $data['chains'] . '</td>';
		echo '<td>' . ($data['retries'] + $data['err_other']) . '</td>';
		echo '<td>' . $data['ccq'] . '</td>';
		if($data['ame'] == 1) {	
			echo '<td>Y</td>';
		} else {
			echo '<td>N</td>';
		}
		if($data['ame'] == 1) {
			echo '<td>' . $data['amq'] . '</td>';
			echo '<td>' . $data['amc'] . '</td>';
		} else {
			echo '<td>N/A</td>';
			echo '<td>N/A</td>';
		}
		if($data['gps'] == 1) {
			echo '<td>Y</td>';
		} else {
			echo '<td>N</td>';
		}
		echo '<td><input type="checkbox" name="addtodb" value="' . $ip . '" /></td>';
		echo '</tr>';
    }
	echo '</table>';

}

?><pre><?php print_r($radio_data) . "<br>" . print_r($peer_data);?></pre>

</body>
</html>