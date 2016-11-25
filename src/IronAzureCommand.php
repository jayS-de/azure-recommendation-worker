<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

abstract class IronAzureCommand extends Command
{
    const DEFAULT_PAGE_SIZE = 500;
    const CTP_CLIENT_ID = 'CTP_CLIENT_ID';
    const CTP_CLIENT_SECRET = 'CTP_CLIENT_SECRET';
    const CTP_PROJECT = 'CTP_PROJECT';
    const CTP_SCOPE = 'CTP_SCOPE';
    const RECOMMENDATION_MODEL_ID = 'RECOMMENDATION_MODEL_ID';
    const SUBSCRIPTION_KEY = 'SUBSCRIPTION_KEY';
    const MAX_FILE_SIZE = 1024 * 1024 * 100;

    private $configLoader;
    private $payloadLoader;
    private $ironLoader;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->configLoader = new ConfigLoader('config', 'CONFIG_FILE');
        $this->payloadLoader = new ConfigLoader('payload', 'PAYLOAD_FILE');
        $this->ironLoader = new ConfigLoader('iron-file', 'IRON_FILE');

    }

    protected function configure()
    {
        $this
            ->addOption('iron-file', 'i', InputArgument::OPTIONAL, '', __DIR__ . '/../iron.json')
            ->addOption('config', 'c', InputArgument::OPTIONAL, '', __DIR__ . '/../config.json')
            ->addOption('payload', 'p', InputArgument::OPTIONAL, '', __DIR__ . '/../payload.json')
        ;
    }

    /**
     * @return ConfigLoader
     */
    public function getIronLoader()
    {
        return $this->ironLoader;
    }

    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    public function getPayloadLoader()
    {
        return $this->payloadLoader;
    }

    private function getAzureHeaders(array $config)
    {
        $subscriptionKey = getenv(static::SUBSCRIPTION_KEY) ?
            getenv(static::SUBSCRIPTION_KEY) : $config[static::SUBSCRIPTION_KEY];
        return [
            'Content-Type' => 'application/octet-stream',
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
        ];
    }

    /**
     * @param array $config
     * @return HttpClient
     */
    protected function getAzureClient(array $config)
    {
        $modelId = getenv(static::RECOMMENDATION_MODEL_ID) ?
            getenv(static::RECOMMENDATION_MODEL_ID) : $config[static::RECOMMENDATION_MODEL_ID];
        $handler = HandlerStack::create();
        $handler->push(Middleware::log($this->getLogger(), new MessageFormatter()), 'logger');

        $url = sprintf(
            'https://westus.api.cognitive.microsoft.com/recommendations/v4.0/models/%s/',
            $modelId
        );

        $httpClient = new HttpClient(
            ['handler' => $handler, 'base_uri' => $url, 'headers' => $this->getAzureHeaders($config)]
        );

        return $httpClient;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        $logger = new Logger($this->getName());
        $logger->pushHandler(new ErrorLogHandler());

        return $logger;
    }

    private function getCtpConfig(array $config)
    {
        $clientConfig = [
            'client_id' => getenv(static::CTP_CLIENT_ID) ? getenv(static::CTP_CLIENT_ID) : $config[static::CTP_CLIENT_ID],
            'client_secret' => getenv(static::CTP_CLIENT_SECRET) ? getenv(static::CTP_CLIENT_SECRET) : $config[static::CTP_CLIENT_SECRET],
            'project' => getenv(static::CTP_PROJECT) ? getenv(static::CTP_PROJECT) : $config[static::CTP_PROJECT],
            'scope' => getenv(static::CTP_SCOPE) ? getenv(static::CTP_SCOPE) : (isset($config[static::CTP_SCOPE]) ? $config[static::CTP_SCOPE] : 'manage_project')
        ];
        return $clientConfig;
    }

    /**
     * @param array $clientConfig
     * @return Client
     */
    protected function getCtpClient(array $config)
    {
        $clientConfig = $this->getCtpConfig($config);

        $context = Context::of()->setLanguages(['en'])->setGraceful(true);

        // create the api client config object
        $config = Config::fromArray($clientConfig)->setContext($context);


        $logger = $this->getLogger();

        $filesystemAdapter = new Local(__DIR__.'/');
        $filesystem        = new Filesystem($filesystemAdapter);
        $cache = new FilesystemCachePool($filesystem);

        $client = Client::ofConfigCacheAndLogger($config, $cache, $logger);
        return $client;
    }

    protected function postFileData($fileName, $url, HttpClient $httpClient, $force = false, $headers = [])
    {
        if ($force || static::MAX_FILE_SIZE < filesize($fileName)) {
            if (is_file($fileName)) {
                $body = fopen($fileName, 'r');
                try {
                    $response = $httpClient->post($url, ['body' => $body, 'headers' => []]);
                } catch (\Exception $e) {
                    echo var_dump((string)$e->getResponse()->getBody());
                    exit;
                }
                echo (string)$response->getBody() . PHP_EOL;
                unlink($fileName);
            }
        }
    }
}
