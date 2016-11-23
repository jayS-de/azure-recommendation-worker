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

$headers = array(
    // Request headers
    'Content-Type' => 'application/json',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
);
$url = sprintf(
    'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s' .
    '/builds',
    $modelId);

$logger = new Logger('build');
$logger->pushHandler(new ErrorLogHandler());

$handler = HandlerStack::create();
$handler->push(Middleware::log($logger, new MessageFormatter()), 'logger');

$httpClient = new \GuzzleHttp\Client(['handler' => $handler]);


$data = [
    'description' => 'Recommendation Build:' . date('Y-m-d'),
    'buildType' => 'recommendation',
    'buildParameters' => ['recommendation' => []]
];
try {
    $response = $httpClient->post($url, ['headers' => $headers, 'body' => json_encode($data)]);
    echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;
} catch (\Exception $e) {
    echo var_dump((string)$e->getResponse()->getBody()) . PHP_EOL;
    exit;
}

$location = current($response->getHeader('Operation-Location'));

$worker = new IronWorker();
$worker->postTask('activate_build', ['url' => $location], ['delay' => 30]);
echo 'done' . PHP_EOL;
