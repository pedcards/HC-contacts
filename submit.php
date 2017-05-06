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
        <title>Heart Center Paging</title>
    </head>
<body>

<?php
function dialog($title,$tcolor,$msg1,$msg2,$img,$alt,$bar,$fg,$bg) {
    ?>
    <div data-role="page" data-dialog="true" id="dialog-fn" data-overlay-theme="<?php echo $bg;?>">
        <div data-role="header" data-theme="<?php echo $bar;?>">
            <h1 style="color:<?php echo $tcolor;?>"><?php echo $title;?></h1>
        </div>
        <div data-role="content" data-theme="<?php echo $fg;?>">
            <p style="text-align:center">
                <?php echo $msg1;?><br>
                <img src="images/<?php echo $img;?>" alt="<?php echo $alt;?>"><br>
                <?php echo $msg2;?><br>
            </p>
        </div>
    </div>
    <?php
}
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
function smartnum($text) {
    return preg_replace('/(?<!\d)7(\-)?(\d{4})(?!\d)/', '$0 [987-$2]', $text);
}

// **** Referrer info for logfile ****
    $logfile = 'logs/'.date('Ym').'.csv';
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
// **** SNPP server Info ****
//$ip = '63.172.11.60';
$ip = 'snpp.amsmsg.net';
$port = '444';

$success = 0;  // preset success to boolean false

// Get values from form page
$fromName = ucwords(trim(filter_input(INPUT_POST,'MYNAME')));
$pinarray = explode(",", trim(simple_decrypt(filter_input(INPUT_POST,'NUMBER'))));  // Pin Number from form page
    $uid = $pinarray[0]; // recipient UID
    $pagesys = $pinarray[1]; // Cook vs USA Mobility
    $pin = $pinarray[2]; // pager number
    $sendto = $pinarray[3]; // A:$pin, B:both, C:service
    $sendSvc = $pinarray[4]; // extra service: sms,pbl,pov,bxc
    $sendStr = $pinarray[5]; // user string
    $userCis = $pinarray[6]; // user CIS
$messagePost = preg_replace("/\r\n/"," ",trim(filter_input(INPUT_POST,'MESSAGE')));  // Get message from form page. Filter CR,LF
$message = "[".$fromName."] ".$messagePost; // Construct Message, add MYNAME in front of MESSAGE.

// Log the access
$out = fopen($logfile,'a');
fputcsv(
    $out, 
    array(
        date('c'),
        $ipaddress,
        $pin,
        $uid,
        $fromName,
        simple_encrypt($messagePost)
    )
); 
fclose($out);

if (strtolower($fromName) == strtolower($userCis)) {
    ?>
    <div data-role="page" data-dialog="true" id="dialog-fn" data-overlay-theme="b">
        <div data-role="header" data-theme="b">
            <h1 style="color:red">EDIT</h1>
        </div>
        <div data-role="content" data-theme="a">
            <form action="edit.php?id=<?php echo $uid;?>" method="POST" name="sendForm" id="sendForm" data-prefetch>
                <input type="hidden" name="GROUP" value="<?php echo $group; ?>">
                <div style="text-align: center">
                    Click here to edit user<br>
                    <input type="submit" value="EDIT!" data-inline="true" data-theme="b" />
                    <br><br>
                    Or close dialog (X) to return.<br>
                </div>
            </form>
        </div>
    </div>
    <?php
    exit();
}

// Update the MRU cookie
if ($pin) {
    $cstr = $uid;
    $cookie = explode(",", filter_input(INPUT_COOKIE,'pagemru'));
    $i = 0;
    foreach($cookie as $cvals){
        if ($cvals==$uid){
            continue;
        }
        $i++;
        if ($i==5){
            break;
        }
        $cstr .= ','.$cvals;
    }
    setcookie('pagemru',$cstr,time()+(86400*30));
}
// Error handling if no FROM specified

if ($fromName == "") {
    dialog('FAIL','red','', (($pin)?'The <b>FROM</b> field is <b>REQUIRED!</b>':'Must select a user!'), 'dead_ipod.jpg', 'bummer', 'b', 'a', 'b');
    exit;
}

    require './lib/PHPMailerAutoload.php';
    $key = substr(str_shuffle('ABCDEFGHJKLMNOPQRSTUVWXYZabcdefghijkmnopqrstuvwyxz'),0,4); // no upper "I" or lower "l" to avoid confusion.


// Send other services
$smsMsg = 'Page';
if (($sendto == "B") || ($sendto == "C")) {
    if ($sendSvc == 'sms'){
        require_once './lib/PHPMailerAutoload.php';
        $mail = new PHPMailer;
        $mail->setFrom('pedcards@uw.edu',$fromName);
        $mail->addAddress($sendStr);
        $mail->isHTML(false);
        $mail->Body    = (strpos($sendStr,'att')?'':'['.$fromName.'] ').smartnum($messagePost);
        $ret = (!$mail->send());
        $diag = array(
            'SMS',($ret ? 'red':'green'),
            'Text message '.($ret ? 'error!':'sent to cell phone!'),
            '<small>*May take a while to be received.</small>',
            'sms-128.png', 'sms',
            '', '', 'b');
        $smsMsg = "SMS and Page";
    }
    if ($sendSvc == 'pbl'){
        $data = array(
            "email" => $sendStr,
            "type" => "note",
            "title" => "FROM: ".smartnum($fromName),
            "body" => smartnum($messagePost)
            );
        $data_string = json_encode($data);
        $ch = curl_init('https://api.pushbullet.com/v2/pushes');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ouJTM6bJedxmqB4iY1pLNsIFp4b843qB',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $result = curl_exec($ch);
        $diag = array(
            'Pushbullet','green',
            'Pushbullet message sent!','',
            'sms-128.png','pushbullet',
            '','','b');
        $smsMsg = "Pushbullet and Page";
    }
    if ($sendSvc=='pov'){
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            CURLOPT_POSTFIELDS => array(
                "token" => "asfJPnVyAUvsTkoGT8cAEvtE8pndHY",
                "user" => $sendStr,
                "title" => "FROM: ".smartnum($fromName),
                "message" => smartnum($messagePost),
                "sound" => "echo"
            ),
            CURLOPT_SAFE_UPLOAD => true,
        ));
        $ret = curl_exec($ch);
        curl_close($ch);
        $diag = array(
            'Pushover',$ret ? 'green':'red', 
            'Pushover message '.($ret ? 'sent!':'failed!'), '', 
            'sms-128.png', 'pushover', 
            '', '', 'b');
        $smsMsg = "Pushover and Page";
    }
    if ($sendSvc=='bxc'){
        curl_setopt_array($chpush = curl_init(), array(
            CURLOPT_URL => "https://new.boxcar.io/api/notifications",
            CURLOPT_POSTFIELDS => array(
                "user_credentials" => $sendStr,
                "notification[title]" => 'FROM: '.smartnum($fromName),
                "notification[long_message]" => smartnum($messagePost),
                "notification[sound]" => "bird-1",
            ),
            CURLOPT_SAFE_UPLOAD => true,
        ));
        $ret = curl_exec($chpush);
        curl_close($chpush);
        $diag = array(
            'Boxcar','green', 
            'Boxcar message sent!', '', 
            'sms-128.png', 'boxcar', 
            '', '', 'b');
        $smsMsg = "Boxcar and Page";
    }
    if ($sendSvc=='prl'){
        curl_setopt_array($chpush = curl_init(), array(
            CURLOPT_URL => "https://api.prowlapp.com/publicapi/add",
            CURLOPT_POSTFIELDS => array(
                "apikey" => "6171e00966210b2c7a6fa33aff317117d0feb2b9",
                "providerkey" => "63f5b2bcffdbec65d4939f9769ce4211c32c8cca",
                "application" => "HC Paging",
                "event" => 'FROM: '.smartnum($fromName),
                "description" => smartnum($messagePost),
            ),
            CURLOPT_SAFE_UPLOAD => true,
        ));
        $ret = curl_exec($chpush);
        curl_close($chpush);
        $diag = array(
            'Prowl','green', 
            'Prowl message sent!', '', 
            'sms-128.png', 'prowl', 
            '', '', 'b');
        $smsMsg = "Prowl and Page";
    }
    if ($sendSvc=='tgt'){
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://developer.tigertext.me/v2/message",
            CURLOPT_USERPWD => "RIq3MIqNcB6dsM2F5HuwfvMgffw8wTZ4:5diOz0ARBM8LHZLM0aynO88sSX87GUQ0Vll1RU29PF0q2Fpn",
            CURLOPT_POSTFIELDS => array(
                "recipient" => $sendStr,
                "body" => smartnum('FROM: '.$fromName.chr(10).$messagePost)
            ),
            CURLOPT_SAFE_UPLOAD => true,
        ));
        $ret = curl_exec($ch);
        curl_close($ch);
        $diag = array(
            'TigerText',$ret ? 'green':'red', 
            'TigerText message '.($ret ? 'sent!':'failed!'), '', 
            'sms-128.png', 'tigertext', 
            '', '', 'b');
        $smsMsg = "TigerText and Page";
    }
}

// Option C, stop after sending SMS.
if ($sendto === "C") {
    dialog($diag[0],$diag[1],$diag[2],$diag[3],$diag[4],$diag[5],$diag[6],$diag[7],$diag[8]);
    exit;
}

// Block for submitting to USA Mobility number
if ($pagesys === "U") {
    $usaForm = array(
        "PIN" => $pin,
        "MSSG" => $message,
        "Q1" => '0'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"http://www.usamobility.net/cgi-bin/wwwpage.exe");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($usaForm));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    if (preg_match("/page\s*sent/i",$result)) {
        dialog('USA Mobility','green', $smsMsg.' sent!*', '<small>*hopefully you won\'t regret what you just sent!</small><br>', 'pager.jpg', 'pager', '', '', 'b');
    } else {
        dialog('USA Mobility','red', 'Server error', 'Message failed to send!<br>', 'dead_ipod.jpg', 'bummer', 'b', 'a', 'b');
    }
    exit;
}

// Block for submitting to CookMail
// This is the socket connection to the snpp server
$snppPointer = fsockopen($ip , $port);  // Open connection
$snppConnect = fgets($snppPointer);

fwrite($snppPointer, "PAGE ".($pin)."\r\n");  // Send the pin to the SNPP server
$snppPage = fgets($snppPointer);

fwrite($snppPointer, "MESS ".($message)."\r\n");  // Send the message to the SNPP Server
$snppMsg = fgets($snppPointer);

fwrite($snppPointer, "SEND\r\n");  // Send
$snppSend = fgets($snppPointer);

fwrite($snppPointer, "QUIT\r\n");  // Send QUIT to the SNPP Server
$snppQuit = fgets($snppPointer);

fclose($snppPointer);  // Close the connection

// Testing for a server response code 250 (good).
// If doesn't meet this, then error back.

//echo $snppSend;

if (substr($snppSend,0,3) === "250") { 
    $success = 1;
    dialog('SUCCESS!', 'green', $smsMsg.' sent!*', '<small>*hopefully you won\'t regret what you just sent!</small>', 'pager.jpg', 'pager', '', '', 'b');
    exit;
    }

  // Testing for a server response code 550 (bad). Pager number was not valid. Error back.
else if (($snppPage[0] === "4") || ($snppPage[0] === "5")) {
    $success = false;
    dialog('SERVER ERROR', 'red', 'ERROR!!!', 'Message failed to send!<br>'.$snppSend.'<br>', 'dead_ipod.jpg', 'bummer', 'b', 'a', 'b');
}
?>

</body>
</HTML>
