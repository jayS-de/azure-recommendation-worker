<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use Commercetools\Core\Request\Orders\OrderQueryRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderFullSyncCommand extends IronAzureCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('recommendation:order-sync')

            // the short description shown while running "php bin/console list"
            ->setDescription('Imports all orders to azure')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = __DIR__ . '/../orders.csv';

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
                'usage?usageDisplayName=%s-%s',
                $config[static::CTP_PROJECT],
                $part
            );
            $part++;
            $data = [];
            $request = OrderQueryRequest::of();
            $request->sort('id')->limit(static::DEFAULT_PAGE_SIZE)->withTotal(false);
            if ($lastId != null) {
                $request->where('id > "' . $lastId . '"');
            }
            $response = $client->execute($request);
            $orders = $request->mapFromResponse($response);
            if ($response->isError() || is_null($orders)) {
                break;
            }

            foreach ($orders as $order) {
                if ($order->getCustomerId() || count($order->getLineItems()) > 1) {
                    foreach ($order->getLineItems() as $lineItem) {
                        $customerId = $order->getCustomerId() ? $order->getCustomerId() : $order->getId();
                        $row = [
                            sha1($customerId),
                            $lineItem->getProductId(),
                            $order->getLastModifiedAt()->getUtcDateTime()->format('c'),
                            'Purchase'
                        ];
                        $data[] = implode(',', $row);
                    }
                }
            }
            $results = $response->toArray()['results'];
            $lastId = end($results)['id'];

            file_put_contents($fileName, implode(PHP_EOL, $data), FILE_APPEND);

            $this->postFileData($fileName, $url, $httpClient);
        } while (count($orders) >= static::DEFAULT_PAGE_SIZE);

        $this->postFileData($fileName, $url, $httpClient, true);

        echo 'done' . PHP_EOL;    }
}
