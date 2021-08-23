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

function get_objects($pbx, $username, $password, $pbx_name = '', $guid = false) {
    $curl_context = stream_context_create(array('http' => array('header'  => "Authorization: Basic ".base64_encode($username.":".$password))));
    if ($guid) {
        $curl = @file_get_contents("https://".$pbx."/PBX0/ADMIN/mod_cmd_login.xml?cmd=show&user-guid=".$guid, false, $curl_context);
    } else {
        $curl = @file_get_contents("https://".$pbx."/PBX0/ADMIN/mod_cmd_login.xml?cmd=show&user=*&search=&search-loc=".urlencode($pbx_name)."&search-grp=&hide=&xsl=pbx_objs_right.xsl", false, $curl_context);
        if (isset($http_response_header[0]) && $http_response_header[0] == 'HTTP/1.0 401 Unauthorized') {
            echo('Error: Wrong user/pass for Master PBX, or <a href="http://wiki.innovaphone.com/index.php?title=Reference10:Services/HTTP/Server" target="_blank">basic auth</a> not activated - ('.$username.'@'.$pbx.')');
        } elseif (!$curl) {
            echo('Error: PBX not reachable - ('.$username.'@'.$pbx.')');
        }
    }
    return simplexml_load_string($curl);
}

$data = get_objects($config->master_pbx, $config->username, $config->password);
if (!$data) exit();

function get_table($data, $pbx) {
    global $config, $attributes;

    if (!$data) return false;

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
        if ($item->attributes()->cn == '_MASTER_') continue;

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
            $data_user = get_objects($pbx, $config->username, $config->password, false, $item->attributes()->guid);
        }

        $i++;
        echo "<tr>";
        echo "<td align=left>".$i."</td>";
        echo "<td align=left>".$item->attributes()->cn."</td>";
        echo "<td align=left>".$item->attributes()->node."</td>";
        echo "<td align=left>".$item->attributes()->loc."</td>";
        foreach($attributes as $attribute) {
            // quick fix for license naming (uclic aka uc)
            $attribute_to_check = $attribute;
            if ($attribute == 'uclic') $attribute_to_check = 'uc';

            // unlicensed
            $nok = '';
            $attribute_nok = $attribute_to_check.'-unlicensed';
            if (isset($data_user->user[$attribute_nok])) {
                $nok = ' (<span style="color: red; font-weight: bold;">unlicensed</span>)';
                $counter_unlicensed[$attribute]++;
            }
            echo "<td align=center>".(!empty($item->attributes()->{$attribute}) ? $item->attributes()->{$attribute} : '').$nok."</td>";
        }
        echo "</tr>";
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
}

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

// build pbx list
$pbxes = array();
foreach($data as $item) {
    if (empty($item)) continue;
    if (isset($item->pseudo) && isset($item->pseudo->attributes()->type) && $item->pseudo->attributes()->type == 'loc') {
        $pbxes[(string) $item->attributes()->cn] = $item;
    }
}

echo '<h1>License Summary</h1>
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
    </div>';


if (count($pbxes) > 1) {
    echo '<h2>PBX: Master</h2>';
    get_table($data,$config->master_pbx);

    foreach ($pbxes as $pbx) {
        echo '<h2>PBX: '.$pbx->attributes()->cn.'</h2>';
        if (@!isset($pbx->ep->attributes()->addr)) {
            echo 'PBX registration is offline! - skip PBX';
        } else {
            get_table(get_objects($pbx->ep->attributes()->addr, $config->username, $config->password, $pbx->attributes()->cn), $pbx->ep->attributes()->addr);
        }
    }
} else {
    echo '<h2>Users</h2>';
    get_table($data);
}

?>
</body>
