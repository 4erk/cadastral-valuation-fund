<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Rosreestr\Cadastral\Client;

$number = $argv[1] ?? '78:11:0006113:5691';
$kind = $argv[2] ?? 'room';

try {
    $client = new Client();
    $history = $client->searchByCadastral($number);
    $diagram = $client->getHistoryDiagram($number);
    $current = $client->getCurrentDetails($number, $kind);

    $result = [
        'cadastralNumber' => $number,
        'proxyConfigured' => trim((string) getenv('CADASTRAL_PROXY')) !== '',
        'history' => $history,
        'diagram' => $diagram,
        'current' => $current,
    ];

    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("NSPD live probe failed: %s\n", $exception->getMessage()));
    exit(1);
}
