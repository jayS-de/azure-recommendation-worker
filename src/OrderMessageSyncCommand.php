<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use Commercetools\Core\Model\Subscription\Delivery;
use Commercetools\Core\Model\Subscription\ResourceCreatedDelivery;
use Commercetools\Core\Request\Orders\OrderByIdGetRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderMessageSyncCommand extends IronAzureCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('recommendation:message-sync')

            // the short description shown while running "php bin/console list"
            ->setDescription('Imports an message to azure')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfigLoader()->load($input);
        $client = $this->getCtpClient($config);
        $httpClient = $this->getAzureClient($config);

        $payload = $this->getPayloadLoader()->load($input);
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
            $url = 'usage/events';

            try {
                $response = $httpClient->post(
                    $url,
                    ['headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($data)]
                );
            } catch (\Exception $e) {
                echo var_dump((string)$e->getResponse()->getBody());
                exit;
            }
            echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;
        }

        echo 'done' . PHP_EOL;
    }
}
