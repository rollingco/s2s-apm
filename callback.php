<?php
file_put_contents(
    'callback_log.txt',
    date('Y-m-d H:i:s') . "\n" . print_r($_POST, true),
    FILE_APPEND
);
echo "OK";
