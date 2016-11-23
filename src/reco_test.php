<?php
namespace Commercetools\IronIO\Recommendation;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use Commercetools\Core\Request\Carts\CartQueryRequest;
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
$modelId = $config['RECOMMENDATION_ID'];
$subscriptionKey = $config['RECOMMENDATION_KEY'];

$id = 'd7a1c5d2-c49f-4836-b78e-72e8a1213503';

$headers = array(
    // Request headers
    'Content-Type' => 'application/json',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
);
$url = sprintf(
    'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s' .
    '/recommend/item?itemIds=%s&numberOfResults=%s&minimalScore=10',
    $modelId,
    $id,
    10
);

$logger = new Logger('reco_test');
$logger->pushHandler(new ErrorLogHandler());

$handler = HandlerStack::create();
$handler->push(Middleware::log($logger, new MessageFormatter()), 'logger');

$httpClient = new \GuzzleHttp\Client(['handler' => $handler]);


$response = $httpClient->get($url, ['headers' => $headers]);
echo (string)$response->getBody() . PHP_EOL;

echo 'done' . PHP_EOL;


