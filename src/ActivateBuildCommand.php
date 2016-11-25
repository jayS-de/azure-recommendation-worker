<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use IronWorker\IronWorker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActivateBuildCommand extends IronAzureCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('recommendation:activate')

            // the short description shown while running "php bin/console list"
            ->setDescription('Activates a build')
            ->addOption('operation', 'o', InputArgument::OPTIONAL, 'URL to the operation status')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $payload = $this->getPayloadLoader()->load($input);
        $url = isset($payload['url']) ? $payload['url'] : $input->getOption('operation');

        $config = $this->getConfigLoader()->load($input);
        $httpClient = $this->getAzureClient($config);

        try {
            $response = $httpClient->get($url);

            $body = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            var_dump((string)$e->getResponse()->getBody());
            exit;
        }
        echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

        if (isset($body['result']['status']) && $body['result']['status'] == 'Succeeded') {
            $body = ['activeBuildId' => $body['result']['id']];
            $response = $httpClient->patch(
                '',
                ['headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($body)]
            );
            echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

            if ($response->getStatusCode() == 200) {
                $url = 'builds';
                $response = $httpClient->get($url);
                echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;

                echo "Yeah" . PHP_EOL;
            } else {
                echo "Boooohooo" . PHP_EOL;
            }
        } else {
            $worker = new IronWorker($this->getIronLoader()->load($input));
            $taskId = getenv('TASK_ID') ? getenv('TASK_ID') : 0;
            $worker->retryTask($taskId, 30);
            echo "Boo" . PHP_EOL;
        }
        echo 'done' . PHP_EOL;    }
}
