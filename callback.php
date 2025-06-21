<?php
echo "<h3>🟢 File callback.php starting...</h3>";

// Log callback data from payment system (successful transaction)
file_put_contents(
    __DIR__ . '/callback_log.txt',
    date('Y-m-d H:i:s') . " CALLBACK:\n" . print_r($_POST ?: $_GET, true) . "\n\n",
    FILE_APPEND
);

echo "<h2>✅ Payment completed successfully!</h2>";
