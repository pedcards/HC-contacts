<!DOCTYPE html>
<HTML>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-capable" content="no" />
        <meta name="viewport" content="initial-scale=1, width=device-width, user-scalable=no" />
        <?php
        $isLoc = true;
        $ini = parse_ini_file("../paging/paging.ini");
        $cdnJqm = $ini['jqm'];
        $cdnJQ = $ini['jquery'];
        $instr = $ini['copyright'];
        ?>
        <link rel="stylesheet" href="<?php echo (($isLoc) ? './jqm' : 'http://code.jquery.com/mobile/'.$cdnJqm).'/jquery.mobile-'.$cdnJqm;?>.min.css" />
        <script src="<?php echo (($isLoc) ? './jqm/' : 'http://code.jquery.com/').'jquery-'.$cdnJQ;?>.min.js"></script>
        <script src="<?php echo (($isLoc) ? './jqm' : 'http://code.jquery.com/mobile/'.$cdnJqm).'/jquery.mobile-'.$cdnJqm;?>.min.js"></script>
        <script src="./lib/cookies.js"></script>
        <script type="text/javascript">
            function doSubmit($in="",$log="",$ip="") {
                $.get("result.php", { in : $in });
                //return false;
            }
        </script>
<!--==========================================-->
        <title>Heart Center Contacts</title>
    </head>
<body>
    <?php
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')) {
        $ipaddress = getenv('HTTP_CLIENT_IP');
    } else if(getenv('HTTP_X_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    } else if(getenv('HTTP_X_FORWARDED')) {
        $ipaddress = getenv('HTTP_X_FORWARDED');
    } else if(getenv('HTTP_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    } else if(getenv('HTTP_FORWARDED')) {
       $ipaddress = getenv('HTTP_FORWARDED');
    } else if(getenv('REMOTE_ADDR')) {
        $ipaddress = getenv('REMOTE_ADDR');
    } else {
        $ipaddress = 'UNKNOWN';
    }
    $browser = $_SERVER['HTTP_USER_AGENT'];
    $phone = preg_match('/(iPhone|Android|Windows Phone)/i',$browser);
    $geo = json_decode(file_get_contents('http://ipinfo.io/'.$_SERVER['REMOTE_ADDR']));
    $xml = simplexml_load_file("../paging/list.xml");
    $chip = simplexml_load_file('../patlist/currlist.xml');
    $call = array(
        'CICU',
        'ICU_A',
        'Ward_A',
        'EP',
        'Txp'
    );
    $call_dt = date("Ymd");
    $call_d = date("l");
    $call_t = date("H");
    if ((preg_match('/(Saturday|Sunday)/i',$call_d)) or ($call_t >= 17 || $call_t < 8)) {
        $call = array(
            ($call_t >= 17 || $call_t < 8) ? 'CICU_PM' : 'CICU',
            'PM_We_A',
            'EP',
            'Txp'
        );
    }
    if ($call_t < 8) {
        $call_dt = date("Ymd", time()-60*60*24);
    }
    $fc_call = $chip->lists->forecast->xpath("call[@date='".$call_dt."']")[0];
    
    $logfile = 'logs/'.date('Ym').'.csv';
    $iplist = 'logs/iplist';
    lister($ipaddress);
    logger(
        $geo->org.",".$geo->hostname.",".
        $geo->city.",".$geo->region
    );
    
    function simple_encrypt($text, $salt = "") {
        if (!$salt) {
            global $instr; $salt = $instr;
        }
        if (!$text) {
            return $text;
        }
        return trim(base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), MCRYPT_RAND))));
    }
    function simple_decrypt($text, $salt = "") {
        if (!$salt) {
            global $instr; $salt = $instr;
        }
        if (!$text) {
            return $text;
        }
        return trim(mcrypt_decrypt(MCRYPT_BLOWFISH, $salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }
    function getUid($in) {
        global $xml;
        $trans = array(
            "Terry" => "Terrence",
            "Steve" => "Stephen",
            "Tom" => "Thomas",
            "Jenny" => "Jennifer",
            "Matt" => "Matthew",
            "John" => "Jonathon",
            "Mike" => "Michael",
            "Katherine" => "Katie"
        );
        $names = explode(" ", $in);
        $el = $xml->xpath("//user[@last='".$names[1]."' and (@first='".$names[0]."' or @first='".strtr($names[0],$trans)."')]")[0];
        return $el['uid'];
    }
    function fuzzyname($str) {
        global $xml;
        $users = $xml->xpath('//user');
        $shortest = -1;
        foreach ($users as $user) {
            $name = $user['first']." ".$user['last'];
            $lev = levenshtein($str, $name);
            if ($lev == 0) {
                $closest = $name;
                $shortest = 0;
                $uid = $user['uid'];
                break;
            }
            if ($lev <= $shortest || $shortest < 0) {
                $closest = $name;
                $shortest = $lev;
                $uid = $user['uid'];
            }
        }
        $user = $xml->xpath("//user[@uid='".$uid."']")[0];
        return array('first'=>$user['first'], 'last'=>$user['last'], 'uid'=>$user['uid']);
    }
    function clickPhone($num,$str) {
        global $phone;
        $ph_str = substr($num,0,3).'-'.substr($num,3,3).'-'.substr($num,6,4);
        echo '<a '.(($phone)?'href="tel:'.$num.'"':'')
            .'class="ui-btn ui-mini" '
            .'onclick="doSubmit(\''.$num.'\');">'
            .$str.'<br>'
            .$ph_str
            .'</a>'."\r\n";
    }
    function logger($str) {
        global $logfile, $ipaddress;
        $out = fopen($logfile,'a');
        fputcsv(
            $out, 
            array(
                preg_replace('/-07:00/','',date('c')),
                $ipaddress,
                $str
            )
        ); 
        fclose($out);
    }
    function lister($ip) {
        // Log IP address of access to this site for reference from Index
        global $iplist;
        $str = file_get_contents($iplist);
        if (strpos($str,$ip) !== false) {
            return;
        }
        file_put_contents($iplist, $ip."\r\n", FILE_APPEND);
    }
    ?>
    
    <div data-role="panel" id="info" data-display="overlay" data-position="right">
        <ul data-role="listview" data-inset="false">
            <li data-icon="info"><a href="#info_Popup" data-rel="popup" data-position-to="window" data-transition="pop">About...</a></li>
        </ul>
        <div data-role="popup" id="info_Popup" >
            <div data-role="header" >
                <h4>About this thing...</h4>
            </div>
            <div data-role="main" class="ui-content">
                This web page is provided as a service<br>
                to referring providers to the<br>
                Seattle Children's Hospital Heart Center. <br>
                Please be respectful of our providers <br>
                and try not to abuse the link.<br>
                <br>
                Thank you!
            </div>
        </div>
    </div>

    <div data-role="header" data-position="fixed" data-theme="b" >
        <p style="white-space: normal; text-align: center">Seattle Children's Hospital<br>Heart Center Contacts</p>
        <a href="#info" class="ui-btn ui-shadow ui-icon-bullets ui-btn-icon-notext ui-corner-all ui-btn-right" data-ajax="false">return to main</a>
    </div><!-- /header -->
    
    <div data-role="content">
        <?php
        echo '<a href="proc.php?group=SURG&id=55b948fa1c644" class="ui-btn ui-mini">Page Jonathan</a>';
        echo '<a href="proc.php?group=CARDS&id=55b948fa18a52" class="ui-btn ui-mini">Page Mark</a>';
        echo '<br>';
        clickPhone('2069876503', 'Call CICU attending line');
        foreach($call as $callU){
            $chName = $fc_call->$callU;
            if ($chName=='') {
                continue;
            }
            if ($callU=='EP') {
                if ($call_d=='Friday' && $call_t>=17) {
                    $chName = $chip->lists->forecast->xpath("call[@date='".date("Ymd",time()+60*60*24)."']/EP")[0];
                }
                if ($call_d=='Saturday') {
                    $chName = $chip->lists->forecast->xpath("call[@date='".date("Ymd",time())."']/EP")[0];
                }
            }
            $liUserId = getUid($chName);
            if (! $liUserId) {
                $liUserId = fuzzyname($chName)['uid'];
                $chName = "'".$chName."'";
            }
            $liUser = $xml->xpath("//user[@uid='".$liUserId."']")[0];
            $liGroup = $liUser->xpath('..')[0]->getName();
            if (strpos($callU,'CICU') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page CICU Attending'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
            if (strpos($callU,'ICU_A') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page ICU Consult Cardiologist'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
            if (strpos($callU,'Ward_A') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page Ward Consult Cardiologist'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
            if (strpos($callU,'PM_We_A') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page Cardiology Attending'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
            if (strpos($callU,'EP') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page Electrophysiologist'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
            if (strpos($callU,'Txp') !== false) {
                echo '<a href="proc.php?group='.$liGroup.'&id='.$liUserId.'" class="ui-btn ui-mini">'
                    .'Page Transplant Cardiologist'.(!$phone ? ' ' : '<br>')
                    .'On-Call: '.$chName.'</a>'."\r\n";
            }
        }
        echo '<br>';
        clickPhone('2069878899', 'MEDCON/Transport');
        clickPhone('2069877777', 'Physician Consult Line');
        echo '<br>';
        clickPhone('2069872198', 'Surgical/Procedure Coordinators');
        clickPhone('2069875629', 'Prenatal Diagnosis and Treatment Program');
        clickPhone('2069876442', 'Regional Nurse Practitioner: Emily');
        clickPhone('2069871058', 'Community Liaison: Anya');
        ?>
    </div>

</body>
</html>
