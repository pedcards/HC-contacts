<!DOCTYPE html>
<HTML>
<HEAD>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="apple-touch-icon" href="favicon.png" />
    <link href="" rel="apple-touch-startup-image" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-capable" content="no" />
    <meta name="viewport" content="initial-scale=1, width=device-width, user-scalable=no" />
<!--==========================================-->
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
<!--==========================================-->
    <script type="text/javascript">
    // from http://web.enavu.com/daily-tip/maxlength-for-textarea-with-jquery/
        $(document).ready(function() {  
            $('textarea[maxlength]').keyup(function(){  //get the limit from maxlength attribute  
                var limit = parseInt($(this).attr('maxlength'));  //get the current text inside the textarea  
                var text = $(this).val();  //count the number of characters in the text  
                var chars = text.length;  //check if there are more characters then allowed  
                if(chars > limit){  //and if there are use substr to get the text before the limit  
                    var new_text = text.substr(0, limit);  //and change the current text with the new text  
                    $(this).val(new_text);  
                }
            });
        });
    </script>
    <title>Heart Center Paging</title>
</HEAD>
<BODY>

<?php
function simple_encrypt($text, $salt = "") {
    if (!$salt) {
        global $instr; $salt = $instr;
    }
    if (!$text) {
        return $text;
    }
    return openssl_encrypt(
            $text, 
            'AES-128-CBC',
            $salt);
}
function simple_decrypt($text, $salt = "") {
    if (!$salt) {
        global $instr; $salt = $instr;
    }
    if (!$text) {
        return $text;
    }
    return openssl_decrypt(
            $text, 
            'AES-128-CBC',
            $salt);
}

$grp = filter_input(INPUT_GET,'group');
$uid = filter_input(INPUT_GET,'id');
$modDate = date ("m/d/Y", filemtime("../paging/list.xml"));
$xml = simplexml_load_file("../paging/list.xml");
$groups = $xml->groups;
$groupfull = array();
foreach ($groups->children() as $grp0) {
    $groupfull[$grp0->getName()] = $grp0->attributes()->full;
}
$group = $xml->groups->$grp;
?>

<!-- Start of first page -->
<div data-role="page" id="procmain" data-dom-cache="true"> <!-- page -->
    <div data-role="header" data-theme="b" data-add-back-btn="true" >
        <a href="javascript:window.history.go(-1)" data-ajax="false" class="ui-btn ui-shadow ui-icon-arrow-l ui-btn-icon-notext ui-corner-all" ><small>Back</small></a>
        <h3><?php echo $groupfull[$grp]; ?></h3>
    </div><!-- /header -->

<form action="submit.php" method="POST" name="sendForm" id="sendForm" data-prefetch>
<div data-role="content">
    <div data-role="fieldcontain" >
        <?php 
        $liUser = $group->xpath("user[@uid='".$uid."']")[0];
        $liUid = $uid;
        $liNameL = $liUser['last'];
        $liNameF = $liUser['first'];

        $liSec = '';
        $liOpt = $liUser->option['mode'];
        $liOptStr = substr(uniqid("",true),-(rand(10,20)));
        if (($liOpt == "B") || ($liOpt == "C")) {
            $liOptSvc = $liUser->option['sys'];
            if ($liOptSvc == "sms") {
                $liOptStr = simple_decrypt($liUser->option->sms['num']).
                    (($liUser->option->sms['sys']=="A") ? "@txt.att.net":'').
                    (($liUser->option->sms['sys']=="V") ? "@vtext.com":'').
                    (($liUser->option->sms['sys']=="T") ? "@tmomail.net":'');
            }
            if ($liOptSvc == "pbl") {
                $liOptStr = simple_decrypt($liUser->option->pushbul['eml']);
            }
            if ($liOptSvc == "pov") {
                $liOptStr = simple_decrypt($liUser->option->pushover['num']);
            }
            if ($liOptSvc == "bxc") {
                $liOptStr = simple_decrypt($liUser->option->boxcar['num']);
            }
            if ($liOptSvc == "tgt") {
                $liOptStr = simple_decrypt($liUser->option->tigertext['num']);
            }
        }
        $pagerline = array(
            $liUid,
            $liUser->pager['sys'],
            simple_decrypt($liUser->pager['num']),
            $liOpt,
            $liOptSvc,
            $liOptStr,
            simple_decrypt($liUser->auth['cis'])
        );
        ?>
        <label for="NUMBER" >To:</label>
        <p><b><?php echo $liNameF." ".$liNameL;?></b></p>
        <input type="hidden" name="NUMBER" id="NUMBER" value="<?php echo simple_encrypt(implode(",",$pagerline));?>">
        <label for="MYNAME">From:</label>
        <input type="text" name="MYNAME" id="MYNAME" value="" placeholder="REQUIRED field. Include callback number." maxlength="20"/>
    </div>

    <div data-role="fieldcontain" style="text-align: right">
        <textarea name="MESSAGE" id="MESSAGE" placeholder="MESSAGE" maxlength="200"></textarea>
    </div>
    <input type="hidden" name="GROUP" value="<?php echo $group; ?>">
    <div style="text-align: center">
        <input type="submit" value="SUBMIT!" data-inline="true" data-theme="b" />
    </div>
    <script type="text/javascript">
        $('#sendForm').submit(function()
        {
            if ($.trim($("#MYNAME").val()) === "") {
                $("#emptyFrom").popup("open");
                return false;
            }
            if ($.trim($("#NUMBER").val()) === "") {
                $("#emptyNum").popup("open");
                return false;
            }
        });
    </script>
    <div data-role="popup" id="emptyFrom" data-overlay-theme="b">
        <div data-role="header" style="background: red">
            <h4>ERROR</h4>
        </div>
        <div data-role="main" class="ui-content">
            <p style="text-align: center">FROM field is required.</p>
        </div>
    </div>
    <div data-role="popup" id="emptyNum" data-overlay-theme="b" >
        <div data-role="header" style="background: red">
            <h4>ERROR</h4>
        </div>
        <div data-role="main" class="ui-content">
            <p style="text-align: center">Must select a valid recipient.</p>
        </div>
    </div>
</div>
</form>

    <div data-role="footer" data-position="fixed">
        <h5><small>
            &COPY;(2007-2017) Terrence Chun, MD<br>
            Data revised: <?php echo $modDate; ?><br>
        </small></h5>
    </div><!-- /footer -->
</div><!-- /page -->

</BODY>
</HTML>
