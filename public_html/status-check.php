<?php
http_response_code(200);
header("X-Debug-Status: " . http_response_code());
echo "Status: " . http_response_code();
?>
