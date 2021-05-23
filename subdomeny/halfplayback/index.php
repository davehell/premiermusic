<?php

// Uncomment this line if you must temporarily take down your site for maintenance.
//require '.maintenance.php';

// let bootstrap create Dependency Injection container
$container = require __DIR__ . '/../../app-2-1/halfplayback/bootstrap.php';
// $container = require __DIR__ . '/../../aplikace/hpb/app/bootstrap.php';

// run application
$container->getService('application')->run();
