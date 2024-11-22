<?php
declare(strict_types=1);


require_once __DIR__.'/vendor/autoload.php';


use Rosreestr\Cadastral\Client;
use Swoole\Http\Server;


$http = new Server('0.0.0.0', 9501);

$http->on('request', function ($request, $response) {

    switch ($request->server['request_uri']) {
        case '/':
            $response->header('Content-Type', 'text/html; charset=utf-8');
            $response->end(file_get_contents(__DIR__ . '/index.html'));
            break;

        case '/search':
            $cadastralNumber = $request->get['number'];
            $client = new Client();
            $result = $client->searchByCadastral($cadastralNumber);
            $response->header('Content-Type', 'text/html; charset=utf-8');
            $response->end(print_r($result, true));
            break;

        default:
            $response->end('404 Not Found');
            break;
    }
});


$http->start();
