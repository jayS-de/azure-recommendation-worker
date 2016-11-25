<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use Commercetools\Core\Client;
use Commercetools\Core\Request\Products\ProductProjectionQueryRequest;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductFullSyncCommand extends IronAzureCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('recommendation:product-sync')

            // the short description shown while running "php bin/console list"
            ->setDescription('Imports an catalog to azure')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = __DIR__ . '/../products.csv';

        $config = $this->getConfigLoader()->load($input);
        $client = $this->getCtpClient($config);
        $httpClient = $this->getAzureClient($config);

        $lastId = null;

        $part = 0;
        if (is_file($fileName)) {
            unlink($fileName);
        }
        do {
            $url = sprintf(
                'catalog?catalogDisplayName=%s-%s',
                $client->getConfig()->getProject(),
                $part
            );
            $part++;
            $data = [];
            $request = ProductProjectionQueryRequest::of();
            $request->sort('id')->limit(static::DEFAULT_PAGE_SIZE)->withTotal(false);
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

            file_put_contents($fileName, implode(PHP_EOL, $data), FILE_APPEND);

            $this->postFileData($fileName, $url, $httpClient);
        } while (count($products) >= static::DEFAULT_PAGE_SIZE);

        $this->postFileData($fileName, $url, $httpClient, true);

        echo 'done' . PHP_EOL;
    }
}
