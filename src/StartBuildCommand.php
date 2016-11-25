<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\Recommendation;

use IronWorker\IronWorker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartBuildCommand extends IronAzureCommand
{
    const ACTIVATE_TASK = 'ACTIVATE_TASK';

    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('recommendation:build')

            // the short description shown while running "php bin/console list"
            ->setDescription('Starts a new build')
            ->addOption('activateTask', 'a', InputArgument::OPTIONAL, 'Task name of update worker', 'activate_build')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = 'builds';

        $config = $this->getConfigLoader()->load($input);
        $httpClient = $this->getAzureClient($config);

        $activateTask = getenv(static::ACTIVATE_TASK) ? getenv(static::ACTIVATE_TASK) : $input->getOption('activateTask');

        $data = [
            'description' => 'Recommendation Build:' . date('Y-m-d'),
            'buildType' => 'recommendation',
            'buildParameters' => ['recommendation' => []]
        ];
        try {
            $response = $httpClient->post(
                $url,
                ['headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($data)]
            );
            echo \GuzzleHttp\Psr7\str($response) . PHP_EOL;
        } catch (\Exception $e) {
            echo var_dump((string)$e->getResponse()->getBody()) . PHP_EOL;
            exit;
        }

        $location = current($response->getHeader('Operation-Location'));

        $ironConfig = $this->getIronLoader()->load($input);
        $worker = new IronWorker($ironConfig);
        $worker->postTask($activateTask, ['url' => $location], ['delay' => 60]);
        echo 'done' . PHP_EOL;
    }
}
