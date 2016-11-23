<?php
namespace Commercetools\IronIO\Recommendation;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use Commercetools\Core\Model\Subscription\Delivery;
use Commercetools\Core\Model\Subscription\ResourceCreatedDelivery;
use Commercetools\Core\Request\Carts\CartQueryRequest;
use Commercetools\Core\Request\ClientRequestInterface;
use Commercetools\Core\Request\Orders\OrderByIdGetRequest;
use Commercetools\Core\Request\Products\ProductProjectionQueryRequest;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use IronWorker\IronWorker;
use IronWorker\Runtime;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

const DEFAULT_PAGE_SIZE = 500;
$args = Runtime::getArgs(true);
$config = Runtime::getConfig();

$subscriptionKey = $config['RECOMMENDATION_KEY'];
$modelId = $config['RECOMMENDATION_ID'];

$headers = array(
    // Request headers
    'Content-Type' => 'application/json',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
);

$logger = new Logger('build');
$logger->pushHandler(new ErrorLogHandler());

$handler = HandlerStack::create();
$handler->push(Middleware::log($logger, new MessageFormatter()), 'logger');

$httpClient = new \GuzzleHttp\Client(['handler' => $handler]);


$payload = Runtime::getPayload(true);
$url = $payload['url'];

try {
    $response = $httpClient->get($url, ['headers' => $headers]);

    $body = json_decode($response->getBody(), true);
} catch (\Exception $e) {
    var_dump((string)$e->getResponse()->getBody());
    exit;
}
echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

if (isset($body['result']['status']) && $body['result']['status'] == 'Succeeded') {
    $url = sprintf(
        'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s',
        $modelId
    );
    $body = ['activeBuildId' => $body['result']['id']];
    $response = $httpClient->patch($url, ['headers' => $headers, 'body' => json_encode($body)]);
    echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

    if ($response->getStatusCode() == 200) {
        $url = sprintf(
            'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s/builds',
            $modelId
        );
        $response = $httpClient->get($url, ['headers' => $headers]);
        echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

        echo "Yeah" . PHP_EOL;
    } else {
        echo "Boooohooo" . PHP_EOL;
    }
} else {
    $worker = new IronWorker();
    $worker->retryTask($args['task_id'], 30);
    echo "Boo" . PHP_EOL;
}
echo 'done' . PHP_EOL;
