<?php

// please update config array with your specific data
$config = (object) array(
    'master_pbx'    => '172.16.80.233',
    'username'      => 'admin',
    'password'      => 'ip811',
);

// dont change below this line
error_reporting(E_ALL);
$attributes = ['uclic', 'mypbx', 'reporting', 'fax', 'voicemail', 'mobility', 'video', 'app-sharing'];

$curl_context = stream_context_create(array('http' => array('header'  => "Authorization: Basic ".base64_encode($config->username.":".$config->password))));
$curl = @file_get_contents("https://".$config->master_pbx."/PBX0/ADMIN/mod_cmd_login.xml?cmd=show&user=*&search=&search-loc=&search-grp=&hide=&xsl=pbx_objs_right.xsl", false, $curl_context);
if ($http_response_header[0] == 'HTTP/1.0 401 Unauthorized') exit('Error: Wrong user/pass for Master PBX');
?>

<head>
	<style>
		label {
			cursor: pointer;
		}
	</style>
</head>
<body>

<?php


if (!$curl) {
    exit('Error: PBX not reachable');
} else {
    $data = simplexml_load_string($curl);

    // build pbx list
    $pbxes = array();
    foreach($data as $item) {
        if (empty($item)) continue;
        if (isset($item->pseudo) && isset($item->pseudo->attributes()->type) && $item->pseudo->attributes()->type == 'loc') {
            $pbxes[(string) $item->attributes()->cn] = $item;
        }
    }

    // build pbx overview
    #print_r($pbxes);
    // todo

    echo '<h1>License Overview</h1>
		<h2>Settings</h2>
		<div>
			<form method="POST">
				<label>
					<input type="checkbox" name="show_users_without_license" onchange="this.form.submit();" '.(isset($_POST['show_users_without_license']) ? 'checked="checked"' : '').' />
					Show also users without licenses
				</label>
				<br />
				<label>
					<input type="checkbox" name="check_unlicensed" onchange="this.form.submit();" '.(isset($_POST['check_unlicensed']) ? 'checked="checked"' : '').' />
					Check "unlicensed" licenses (takes a long time)
				</label>
			</form>
		</div>
		<h2>Users</h2>';
	
    echo "<table border='5' cellpadding='8'>";
    echo "<tr>";
    echo "<th>#</th>";
    echo "<th>name</th>";
	echo "<th>node</th>";
	echo "<th>pbx</th>";
	foreach($attributes as $attribute) {
		 echo "<th>".$attribute."</th>";
	}
    echo "</tr>";


    $i = 0;

	// init counter
    $counter = array();
	foreach($attributes as $attribute) {
		$counter[$attribute] = 0;
	}
	$counter_unlicensed = array();
	foreach($attributes as $attribute) {
		$counter_unlicensed[$attribute] = 0;
	}

	// userlist
    foreach($data as $item) {

		if (empty($item)) continue;
		// skip config templates in user list
		if (isset($item->pseudo) && $item->pseudo->attributes()->type == 'config') continue;

		// skip empty user
        if (!isset($_POST['show_users_without_license'])) {
			$skip = true;
			foreach($attributes as $attribute) {
				if ($item->attributes()->{$attribute} != "") $skip = false;
			}
			
			if ($skip) continue;
		}

        // update counter
		foreach($attributes as $attribute) {
			if (isset($item->attributes()->{$attribute}) && $item->attributes()->{$attribute} != "") {
				$counter[$attribute]++;
				#todo: pbx summary
				#$pbx[$item->attributes()->loc][$attribute]++;
			}	
		}

		// lic unlicensed check
		if (isset($_POST['check_unlicensed'])) {
            $user_lic = @file_get_contents("https://".(isset($pbxes[(string) $item->attributes()->loc]->ep->attributes()->addr) ? $pbxes[(string) $item->attributes()->loc]->ep->attributes()->addr : $config->master_pbx)."/PBX0/ADMIN/mod_cmd_login.xml?cmd=show&user-guid=".$item->attributes()->guid, false, $curl_context);
            if ($http_response_header[0] == 'HTTP/1.0 401 Unauthorized') exit('Error: Wrong user/pass for Slave PBX ('.$item->attributes()->loc.')');
            $data_user = simplexml_load_string($user_lic);
		}
		
        $i++;
        echo "<tr>";
            echo "<td align=left>".$i."</td>";
			echo "<td align=left>".$item->attributes()->cn."</td>";
			echo "<td align=left>".$item->attributes()->node."</td>";
			echo "<td align=left>".$item->attributes()->loc."</td>";
			foreach($attributes as $attribute) {
				// unlicensed
				$nok = '';
				$attribute_nok = $attribute.'-unlicensed';	
				if (isset($data_user->user[$attribute_nok])) {
					$nok = ' (<span style="color: red; font-weight: bold;">unlicensed</span>)';
					$counter_unlicensed[$attribute]++;
				}
				
				echo "<td align=center>".(!empty($item->attributes()->{$attribute}) ? $item->attributes()->{$attribute} : '-').$nok."</td>";
			}
        echo "</tr>";
    }
}

// print counter
echo "<tr>";
    echo "<td align=left>&sum;</td>";
    echo "<td align=left>-</td>";
	echo "<td align=left>-</td>";
	echo "<td align=left>-</td>";
	foreach($attributes as $attribute) {
		// unlicensed
		$nok = '';
		if ($counter_unlicensed[$attribute]) $nok = ' (<span style="color: red; font-weight: bold;">'.$counter_unlicensed[$attribute].'</span>)';
		echo "<td align=center>".$counter[$attribute].$nok."</td>";
	}
echo "</tr>";
echo "</table>";

?>
</body>
