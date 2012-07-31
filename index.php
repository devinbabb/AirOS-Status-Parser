<?php
set_time_limit(0);

function get_ubnt_stats($ip, $logins) {
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
        if ($result) {
            $data = json_decode($result);
            if (isset($data->host->hostname)) {
                $radio_data = array(
                    'name' => $data->host->hostname,
                    'mode' => $data->wireless->mode == 'sta' ? 'Station' : 'Access Point',
                    'fw' => $data->host->fwversion,
                    'uptime' => sprintf('%.2f', $data->host->uptime / 86400) . ' days',
                    'freq' => preg_replace('/[^0-9]+/', '', $data->wireless->frequency),
                    'width' => $data->wireless->chwidth,
                    'signal' => $data->wireless->signal,
                    'noise' => $data->wireless->noisef,
                    'lan' => isset($data->lan->status[0]->plugged) ? ($data->lan->status[0]->plugged ? 'Plugged: ' . $data->lan->status[0]->speed . ($data->lan->status[0]->duplex ? 'Full' : 'Half') : 'Unplugged') : $data->lan->status[0]
                );
                break;
            }
        }
    }
    unlink($cookie_file);
    return $radio_data;
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

function iprange($ip, $mask ,$return_array) {
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

$test = iprange($_POST['ips'], $_POST['netmask'], TRUE);

for($i = 0; $i < count($test); $i++) {
        ob_flush();
        flush();
        if($rtadata = ping($test[$i], 80, 0.100)) {
                echo $test[$i] . ' - <span style="color:green"> Online. RTA: </span>' . $rtadata . ' Retrieving Data: ';
		if($data = get_ubnt_stats($test[$i], $logins)) {
			$radio_data[$test[$i]] = $data;
			echo ' <span style="color:red">Success! </span> ';
		} else {
			echo ' <span style="color:red">Failed! </span> ';
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
<tr>
<td colspan="4">
<b>Ip Address / Netmask:</b>
<br />
</td>
</tr>
<tr>
<td colspan="4">
<textarea name="ips" cols="15" rows="1"><?php echo isset($_POST['ips']) ? $_POST['ips'] : ''; ?></textarea>
<textarea name="netmask" cols="4" rows="1"><?php echo isset($_POST['netmask']) ? $_POST['netmask'] : '32'; ?></textarea>
</td>
</tr>
<tr>
<td>
<b>Username:</b>
</td>
<td>
<input type="text" size="20" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : 'ubnt'; ?>" />
</td>
<td>
<b>Password:</b>
</td>
<td>
<input type="password" name="password" value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>" size="20" />
</td>
</tr>
<tr>
<td>
<b>Secondary Username:</b>
</td>
<td>
<input type="text" size="20" name="username2" value="<?php echo isset($_POST['username2']) ? $_POST['username2'] : 'ubnt'; ?>" />
</td>
<td>
<b>Password:</b>
</td>
<td>
<input type="password" name="password2" value="<?php echo isset($_POST['password2']) ? $_POST['password2'] : ''; ?>" size="20" />
</td>
</tr>
</table>
<br />
<input type="submit" value="Go" />
</form>
<?php
if (isset($radio_data)) {
    echo '<br /><br />';
    echo '<table border="0" cellspacing="1" cellpadding="4" width="850">';
    echo '<tr align="center" style="background-color:#ddd">';
    echo '<td><b>IP</b></td>';
    echo '<td><b>Name</b></td>';
    echo '<td><b>Mode</b></td>';
    echo '<td><b>Firmware</b></td>';
    echo '<td><b>Uptime</b></td>';
    echo '<td><b>LAN</b></td>';
    echo '<td><b>Freq/Width</b></td>';
    echo '<td><b>Signal</b></td>';
    echo '<td><b>Noise</b></td>';
    echo '</tr>';
    for ($bgcolor = '#fff'; list($ip,$data) = each($radio_data); $bgcolor = $bgcolor == '#fff' ? '#eee' : '#fff') {
        echo '<tr align="center" style="background-color:' . $bgcolor . '">';
        echo '<td><a href="http://' . $ip . '" target="_blank">' . $ip . '</a></td>';
        echo '<td>' . $data['name'] . '</td>';
        echo '<td>' . $data['mode'] . '</td>';
        echo '<td>' . $data['fw'] . '</td>';
        echo '<td>' . $data['uptime'] . '</td>';
        echo '<td>' . $data['lan'] . '</td>';
        echo '<td>' . $data['freq'] . ' MHz/' . $data['width'] . ' MHz</td>';
        if ($data['signal'] <= -70)
            echo '<td style="color:red">' . $data['signal'] . ' dBm</td>';
        else
            echo '<td>' . $data['signal'] . ' dBm</td>';

        if ($data['noise'] >= -89)
            echo '<td style="color:red">' . $data['noise'] . ' dBm</td>';
        else
            echo '<td>' . $data['noise'] . ' dBm</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    $conflicts = array();
    $c_radio_data = $radio_data;
    foreach ($radio_data as $ip => $data) {
        if ($data['mode'] == 'Station') continue;
        
        $min = $data['freq'] - ($data['width']/2);
        $max = $data['freq'] + ($data['width']/2);
        
        foreach ($c_radio_data as $ip2 => $data2) {
            if ($ip == $ip2 || $data2['mode'] == 'Station') continue;
            
            $min2 = $data2['freq'] - ($data2['width']/2);
            $max2 = $data2['freq'] + ($data2['width']/2);
            
            if ($data['freq'] == $data2['freq'] || $min >= $min2 && $min <= $max2 || $max >= $min2 && $max <= $max2) {
                $seen = false;
                foreach ($conflicts as $c) {
                    if (($c[0] == $data['name'] || $c[0] == $data2['name']) && ($c[1] == $data['name'] || $c[1] == $data2['name'])) {
                        $seen = true;
                        break;
                    }
                }
                if (!$seen) {
                    $conflicts[] = array($data['name'], $data2['name']);
                }
            }
        }
    }
    if ($conflicts) {
        echo '<br /><br />';
        echo '<div style="background-color:#ffcccc; border: 1px solid #000; padding: 4px; width:850px">';
        echo '<span style="font-weight:bold; color:red;">Overlapping frequencies:</span><br />';
        echo '<blockquote>';
        echo '<table>';
        foreach ($conflicts as $c) {
            echo '<tr><td><b>' . $c[0] . '</b></td><td><i>with</i>&nbsp;</td><td><b>' . $c[1] . '</b></td></tr>';
        }
        echo '</table>';
        echo '</blockquote>';
        echo '</div>';
    }
}
?>

</body>
</html>
