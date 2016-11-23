<?php
namespace Commercetools\IronIO\Recommendation;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use Commercetools\Core\Request\Products\ProductProjectionQueryRequest;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use IronWorker\Runtime;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit', '2G');

const DEFAULT_PAGE_SIZE = 500;
$config = Runtime::getConfig();
$appConfig = [
    'client_id' => $config['CTP_CLIENT_ID'],
    'client_secret' => $config['CTP_CLIENT_SECRET'],
    'project' => $config['CTP_PROJECT'],
    'scope' => isset($config['CTP_SCOPE']) ? $config['CTP_SCOPE'] : 'manage_project'
];
$modelId = $config['RECOMMENDATION_ID'];
$subscriptionKey = $config['RECOMMENDATION_KEY'];

$context = Context::of()->setLanguages(['en'])->setGraceful(true);

// create the api client config object
$config = Config::fromArray($appConfig)->setContext($context);


$logger = new Logger('product_full_sync');
$logger->pushHandler(new ErrorLogHandler());

$filesystemAdapter = new Local(__DIR__.'/');
$filesystem        = new Filesystem($filesystemAdapter);
$cache = new FilesystemCachePool($filesystem);

$client = Client::ofConfigCacheAndLogger($config, $cache, $logger);



$headers = array(
    // Request headers
    'Content-Type' => 'application/octet-stream',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
);

$lastId = null;
$handler = HandlerStack::create();
$handler->push(Middleware::log($logger, new MessageFormatter()), 'logger');

$httpClient = new \GuzzleHttp\Client(['handler' => $handler]);

$part = 0;
do {
    $url = sprintf(
        'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s' .
        '/catalog?catalogDisplayName=%s-%s',
        $modelId,
        $config->getProject(),
        $part
    );
    $part++;
    $data = [];
    $request = ProductProjectionQueryRequest::of();
    $request->sort('id')->limit(DEFAULT_PAGE_SIZE)->withTotal(false);
    if ($lastId != null) {
        $request->where('id > "' . $lastId . '"');
    }
    $response = $client->execute($request);
    $products = $request->mapFromResponse($response);
    if ($response->isError() || is_null($products)) {
        break;
    }

    foreach ($products as $product) {
        $name = $product->toArray()['name']['en'];
        $row = [
            $product->getId(),
            str_replace(',', '', $name),
            ''
        ];
        $data[] = implode(',', $row);
    }
    $results = $response->toArray()['results'];
    $lastId = end($results)['id'];

    try {
        $response = $httpClient->post($url, ['headers' => $headers, 'body' => implode(PHP_EOL, $data)]);
    } catch (\Exception $e) {
        echo var_dump((string)$e->getResponse()->getBody());
        exit;
    }
    echo (string)$response->getBody() . PHP_EOL;

} while (count($products) >= DEFAULT_PAGE_SIZE);

echo 'done' . PHP_EOL;


