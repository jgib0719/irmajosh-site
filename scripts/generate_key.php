<?php
// Generate secure 64-character hex string for APP_SECRET
echo bin2hex(random_bytes(32)) . PHP_EOL;
