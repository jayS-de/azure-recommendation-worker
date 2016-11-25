#!/usr/bin/env php
<?php
require __DIR__.'/../vendor/autoload.php';

use Commercetools\IronIO\Recommendation\ActivateBuildCommand;
use Commercetools\IronIO\Recommendation\OrderFullSyncCommand;
use Commercetools\IronIO\Recommendation\OrderMessageSyncCommand;
use Commercetools\IronIO\Recommendation\ProductFullSyncCommand;
use Commercetools\IronIO\Recommendation\StartBuildCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new ActivateBuildCommand());
$application->add(new OrderFullSyncCommand());
$application->add(new OrderMessageSyncCommand());
$application->add(new ProductFullSyncCommand());
$application->add(new StartBuildCommand());

$application->run();

