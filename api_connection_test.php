<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$defaultUrl = 'https://api.leogcltd.com/post-va';
$url = trim((string)($_POST['url'] ?? $defaultUrl));
$run = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pretty(mixed $value): string {
    return h(json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ));
}

function parseTarget(string $url): array {
    $parts = parse_url($url);

    if (!is_array($parts) || empty($parts['host'])) {
        throw new RuntimeException('Invalid URL.');
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = (string)$parts['host'];
    $port = isset($parts['port'])
        ? (int)$parts['port']
        : ($scheme === 'https' ? 443 : 80);

    return [
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'path' => (string)($parts['path'] ?? '/'),
    ];
}

function runCurlTest(string $url, ?int $ipResolve = null): array {
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'PHP cURL extension is not installed.',
        ];
    }

    $verboseStream = fopen('php://temp', 'w+');
    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verboseStream,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'User-Agent: LEOGC-Connection-Test/1.0',
        ],
    ];

    if ($ipResolve !== null) {
        $options[CURLOPT_IPRESOLVE] = $ipResolve;
    }

    curl_setopt_array($ch, $options);

    $started = microtime(true);
    $raw = curl_exec($ch);
    $duration = round(microtime(true) - $started, 3);

    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);

    curl_close($ch);

    rewind($verboseStream);
    $verbose = stream_get_contents($verboseStream);
    fclose($verboseStream);

    $headerSize = (int)($info['header_size'] ?? 0);
    $headers = is_string($raw) ? substr($raw, 0, $headerSize) : '';
    $body = is_string($raw) ? substr($raw, $headerSize) : '';

    return [
        'success' => $raw !== false,
        'duration_seconds' => $duration,
        'curl_errno' => $errno,
        'curl_error' => $error,
        'http_code' => (int)($info['http_code'] ?? 0),
        'primary_ip' => $info['primary_ip'] ?? null,
        'primary_port' => $info['primary_port'] ?? null,
        'local_ip' => $info['local_ip'] ?? null,
        'local_port' => $info['local_port'] ?? null,
        'namelookup_time' => $info['namelookup_time'] ?? null,
        'connect_time' => $info['connect_time'] ?? null,
        'appconnect_time' => $info['appconnect_time'] ?? null,
        'total_time' => $info['total_time'] ?? null,
        'ssl_verify_result' => $info['ssl_verify_result'] ?? null,
        'content_type' => $info['content_type'] ?? null,
        'headers' => $headers,
        'body' => $body,
        'verbose_log' => $verbose,
    ];
}

$results = [];
$error = '';

if ($run) {
    try {
        $target = parseTarget($url);
        $host = $target['host'];
        $port = $target['port'];

        $results['environment'] = [
            'date_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'server_name' => $_SERVER['SERVER_NAME'] ?? null,
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? null,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'curl_extension' => extension_loaded('curl'),
            'openssl_extension' => extension_loaded('openssl'),
            'curl_version' => function_exists('curl_version') ? curl_version() : null,
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
        ];

        $aRecords = function_exists('dns_get_record')
            ? @dns_get_record($host, DNS_A)
            : false;

        $aaaaRecords = function_exists('dns_get_record')
            ? @dns_get_record($host, DNS_AAAA)
            : false;

        $results['dns'] = [
            'host' => $host,
            'gethostbyname' => gethostbyname($host),
            'A_records' => $aRecords === false ? 'dns_get_record failed' : $aRecords,
            'AAAA_records' => $aaaaRecords === false ? 'dns_get_record failed' : $aaaaRecords,
        ];

        $socketErrno = 0;
        $socketError = '';
        $socketStarted = microtime(true);
        $socket = @fsockopen($host, $port, $socketErrno, $socketError, 15);
        $socketDuration = round(microtime(true) - $socketStarted, 3);

        $results['tcp_socket'] = [
            'target' => $host . ':' . $port,
            'success' => is_resource($socket),
            'duration_seconds' => $socketDuration,
            'errno' => $socketErrno,
            'error' => $socketError,
        ];

        if (is_resource($socket)) {
            fclose($socket);
        }

        $results['curl_default'] = runCurlTest($url);

        if (defined('CURL_IPRESOLVE_V4')) {
            $results['curl_ipv4'] = runCurlTest($url, CURL_IPRESOLVE_V4);
        } else {
            $results['curl_ipv4'] = ['error' => 'CURL_IPRESOLVE_V4 is unavailable.'];
        }

        if (defined('CURL_IPRESOLVE_V6')) {
            $results['curl_ipv6'] = runCurlTest($url, CURL_IPRESOLVE_V6);
        } else {
            $results['curl_ipv6'] = ['error' => 'CURL_IPRESOLVE_V6 is unavailable.'];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>API Connection Diagnostics</title>
<style>
body{margin:0;background:#0f1115;color:#e8e8e8;font:14px/1.5 Consolas,Monaco,monospace}
.wrap{max-width:1200px;margin:0 auto;padding:24px}
.panel{background:#171923;border:1px solid #2b3040;border-radius:12px;padding:16px;margin:16px 0}
input{width:min(760px,95%);padding:10px;border-radius:8px;border:1px solid #353b4b;background:#10131a;color:#fff}
button{padding:10px 15px;border:0;border-radius:8px;background:#2b7cff;color:#fff;cursor:pointer}
pre{overflow:auto;white-space:pre-wrap;word-break:break-word;background:#10131a;border:1px solid #292e3b;border-radius:9px;padding:13px}
.ok{color:#7ee787}.bad{color:#ff7b72}.muted{color:#9da7b3}
h1,h2{font-family:Arial,sans-serif}
</style>
</head>
<body>
<div class="wrap">
    <h1>API Connection Diagnostics</h1>

    <div class="panel">
        <form method="post">
            <label for="url">Target URL</label><br><br>
            <input id="url" name="url" type="url" required value="<?= h($url) ?>">
            <button type="submit">Run tests</button>
        </form>
        <p class="muted">
            Tests DNS, TCP port 443, default cURL, forced IPv4 and forced IPv6.
            No API keys or secrets are sent.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="panel bad">
            <strong>Error:</strong> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($run && $error === ''): ?>
        <?php foreach ($results as $title => $data): ?>
            <div class="panel">
                <h2><?= h(str_replace('_', ' ', strtoupper($title))) ?></h2>
                <?php
                $success = is_array($data) && array_key_exists('success', $data)
                    ? (bool)$data['success']
                    : null;
                ?>
                <?php if ($success === true): ?>
                    <p class="ok">SUCCESS</p>
                <?php elseif ($success === false): ?>
                    <p class="bad">FAILED</p>
                <?php endif; ?>
                <pre><?= pretty($data) ?></pre>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>