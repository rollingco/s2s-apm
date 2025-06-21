<?php
echo "<h3>🟢 File cancel.php starting...</h3>";

// Log cancellation by user
file_put_contents(
    __DIR__ . '/cancel_log.txt',
    date('Y-m-d H:i:s') . " CANCEL:\n" . print_r($_POST ?: $_GET, true) . "\n\n",
    FILE_APPEND
);

echo "<h2>⚠️ Payment was canceled by the user.</h2>";
