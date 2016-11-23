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
use IronWorker\Runtime;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

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


$logger = new Logger('order_message_sync');
$logger->pushHandler(new ErrorLogHandler());

$filesystemAdapter = new Local(__DIR__.'/');
$filesystem        = new Filesystem($filesystemAdapter);
$cache = new FilesystemCachePool($filesystem);

$client = Client::ofConfigCacheAndLogger($config, $cache, $logger);



$headers = array(
    // Request headers
    'Content-Type' => 'application/json',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
);

$lastId = null;
$handler = HandlerStack::create();
$handler->push(Middleware::log($logger, new MessageFormatter()), 'logger');

$httpClient = new \GuzzleHttp\Client(['handler' => $handler]);

$payload = Runtime::getPayload(true);
$delivery = Delivery::fromArray($payload);

$type = $delivery->getResource()->getTypeId();

$data = [
    'buildId' => -1,
    'events' => []
];
switch (true) {
    case $delivery instanceof ResourceCreatedDelivery:
        switch ($type) {
            case 'order':
                $request = OrderByIdGetRequest::ofId($delivery->getResource()->getId());
                $response = $request->executeWithClient($client);
                $order = $request->mapFromResponse($response);
                if ($order->getCustomerId() || count($order->getLineItems()) > 1) {
                    $customerId = $order->getCustomerId() ? $order->getCustomerId() : $order->getId();
                    $data['userId'] = sha1($customerId);
                    foreach ($order->getLineItems() as $lineItem) {
                        $row = [
                            'itemId' => $lineItem->getProductId(),
                            'timestamp' => $order->getCreatedAt()->getUtcDateTime()->format('c'),
                            'eventType' => 'Purchase',
                            'count' => $lineItem->getQuantity(),
                            'unitPrice' => round($lineItem->getPrice()->getCurrentValue()->getCentAmount() / 100, 2)
                        ];
                        $data['events'][] = $row;
                    }
                }
                break;
        }
        break;
}

if (count($data['events']) > 0) {
    $url = sprintf(
        'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s' .
        '/usage/events',
        $modelId,
        $config->getProject()
    );

    try {
        $response = $httpClient->post($url, ['headers' => $headers, 'body' => json_encode($data)]);
    } catch (\Exception $e) {
        echo var_dump((string)$e->getResponse()->getBody());
        exit;
    }
    echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;
}

echo 'done' . PHP_EOL;


