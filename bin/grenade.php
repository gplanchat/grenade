#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Luni\Console\Grenade\ConfigureCommand;
use Luni\Console\Grenade\PushCommand;
use Luni\Console\Grenade\UpdateCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ConfigureCommand());
$application->add(new PushCommand());
$application->add(new UpdateCommand());
$application->run();