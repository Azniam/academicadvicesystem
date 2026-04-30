<?php
echo "cURL Extension: " . (extension_loaded('curl') ? "✅ ENABLED" : "❌ NOT ENABLED");
echo "<br>";
echo "Internet Connection: " . (fsockopen("www.google.com", 80) ? "✅ CONNECTED" : "❌ NOT CONNECTED");
?>