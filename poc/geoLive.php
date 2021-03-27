/*
 * [Depreacted] Geolocating a MAC address using the Microsoft Live API demo
 * @author: Elie Bursztein contact@elie.net
 * @see: https://elie.net/blog/privacy/using-the-microsoft-geolocalization-api-to-retrace-where-a-windows-laptop-has-been/
 * @disclamer: code provided "AS IS"
 * @note: This was fixed by Microsoft prior to the 2011 BlackHat presentation: https://elie.net/talk/beyond-files-recovery-owade-cloud-based-forensic/
 */

<html>
    <head>
        <title>Geolocating a MAC address using the Microsoft Live API demo</title>
    </head>
<body>

<script src="http://www.google.com/jsapi?key=ABQIAAAAI-YdQXo00a8-zoLDNeAkpxTQSUXDxPXDRUeBCw_KJjuT43wrghR2GxWFV78W2dMBjOfkCxUzE1jiBA" type="text/javascript"></script>
<script type="text/javascript">
    google.load("jquery", "1.3.2");
    google.load("maps", "2.x");
</script>

<h1>Geolocating a MAC address using the Microsoft Live API demo</h1>

Here is a simple demo of how to use the Microsoft live API to locate the physical coordinates of a MAC address.<br>
The MAC coordinates are pass to Google Map to generate the map</br>
Read my blog post on the subject to know more.<br><br>


<?php
if (isset($_REQUEST['mac'])) {
    echo "<h3>Results</h3>";
    $mac = $_REQUEST['mac'];
    $regex = "/^[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}$/";
    if (! preg_match ($regex, $mac))
    {
        die("Invalid mac address the format is AA:BB:CC:DD:EE:FF");
    } else {
        $res = `ruby ./geoQuery.rb $mac`;
        preg_match('/([^:]+):([^\s]+)/', $res, $matches);
       //print_r($matches);
        $lat = $matches[1];
        $lon = $matches[2];


        if ($lat == 0 && $lon == 0) {
           echo "MAC address not founded in the Live database<br/>";
           exit();
        }

        $acc = 150;
        echo "MAC address founded in the Live database<br/>";
        echo "longitude: $lon, lattitude: $lat<br>";

        //list($lat, $lon) = split('/,/', $res);

?>
    <script type="text/javascript">
        // <![CDATA[
        var map = null;
        var overlay = null;
        var geo = null;

        function st()
        {
            var g = {
                initialize: function() {
                    map = new GMap2(document.getElementById("map"));
                    map.setCenter(new GLatLng(<?php echo $lat ?>,<?php echo $lon ?>), 10);
                    map.addControl(new GSmallMapControl());
                    map.addControl(new GMapTypeControl());
                },
                getCircleOverlay: function(lat, lon, err) {
                    with (Math) {
                        var points = Array();
                        var d = err/6378800;// accuracy / meters of Earth radius = radians
                        var lat1 = (PI/180)* lat; // radians
                        var lng1 = (PI/180)* lon; // radians
                        for (var a = 0 ; a < 361 ; a+=10 ) {
                            var tc = (PI/180)*a;
                            var y = asin(sin(lat1)*cos(d)+cos(lat1)*sin(d)*cos(tc));
                            var dlng = atan2(sin(tc)*sin(d)*cos(lat1),cos(d)-sin(lat1)*sin(y));
                            var x = ((lng1-dlng+PI) % (2*PI)) - PI ; // MOD function
                            var point = new GLatLng(parseFloat(y*(180/PI)),parseFloat(x*(180/PI)));
                            points.push(point);
                        }
                    }
                    return new GPolygon(points,'#0000ff',1,1,'#0000ff',0.2)
                },
                zoomLevel: function(a, step) {
                    step++;
                    map.setCenter(new GLatLng(a.coords.latitude, a.coords.longitude), step);
                    if (step > 14) return;
                    window.setTimeout(function() { geo.zoomLevel(a, step) }, 250);
                },
                setMap: function(a) {
                    var zoomLevel = 14;
                    if (a.coords.accuracy > 500)
                        zoomLevel = 10;
                    map.setCenter(new GLatLng(a.coords.latitude, a.coords.longitude), zoomLevel);
                    //    if (overlay) map.removerOverlay(overlay);
                    overlay = geo.getCircleOverlay(a.coords.latitude, a.coords.longitude, a.coords.accuracy);
                    map.addOverlay(overlay);
                    $('#js-return').innerHTML = '';
                    $('#js-return').html('<h2>'+map.getCenter()+'</h2>');
                },
                setMap2: function(lat, lon, acc) {
                    var zoomLevel = 15;
                    if (acc> 500)
                        zoomLevel = 10;
                    map.setCenter(new GLatLng(lat, lon), zoomLevel);
                    //if (overlay) map.removerOverlay(overlay);
                    overlay = geo.getCircleOverlay(lat, lon, acc);
                    map.addOverlay(overlay);
                    $('#js-return').innerHTML = '';
                    $('#js-return').html('<h2>'+map.getCenter()+'</h2>');
                },
                handleError: function(a) {
                },
                locateMeOnMap: function() {
                    navigator.geolocation.getCurrentPosition(this.setMap, this.handleError);
                }
            }
            return g;
        }

        geo = st();
        $(document).ready(function() {
            geo.initialize();
            geo.setMap2(<?php echo $lat ?>, <?php echo $lon ?>, <?php echo $acc ?>);
        });
        // ]]>
    </script>


        <div id="js-return"></div>
        <div id="js">
            <div id="geo">
                <div id="map" style="position:absolute;width:500px;height:400px;"></div>
            </div>
        </div>
<?
    }

}
    ?>
        <form method="GET" action="">
    	  	    Mac address : <input type="text" name="mac" value="<?= $mac ? $mac : "00:21:E9:B8:C4:A8" ?>"/>
            <input type="submit" value="find"/>
        </form>

<br>
code by Elie Bursztein (<a href="http://twitter.com/elie">@elie</a> on twitter)
</body>
</html>