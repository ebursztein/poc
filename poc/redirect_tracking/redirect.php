<?php
/*
 * redirect tracking method proof of concept
 * @author: Elie Bursztein contact@elie.net
 * @see: https://elie.net/blog/security/tracking-users-that-block-cookies-with-a-http-redirect/ for an explanation
 * @disclamer: code provided "AS IS". use it at your own risks :)
 */
$expires = 60*60*24*14;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
$value = time();
$code = $_REQUEST["code"];
//header("Location: http://172.16.229.2/test/index.php?code=$code&value=$value",TRUE, $code);
header("Location: http://localhost/test/index.php?code=$code&value=$value",TRUE, $code);
?>


<?php
/*
I've just discovered that Chrome doesn't perform a Location: instruction unless it gets a Status: first.  It's also sensitive to capitalisation.

<?php

    header("Status: 200");
    header("Location: /home.php");
    exit;

?>
 *
 * http://172.16.229.2/test/redirect.php?code=301

4. It cann't be relative:

wrong:  Location: /something.php?a=1
wrong:  Location: ?a=1

 Alternative idea: add caching

 // seconds, minutes, hours, days
$expires = 60*60*24*14;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');


*/
?>