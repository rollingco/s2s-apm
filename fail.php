<?php
// Log failed payment attempt
file_put_contents(
    __DIR__ . '/fail_log.txt',
    date('Y-m-d H:i:s') . " FAIL:\n" . print_r($_POST ?: $_GET, true) . "\n\n",
    FILE_APPEND
);

echo "<h2>❌ Payment failed. Please try again.</h2>";
