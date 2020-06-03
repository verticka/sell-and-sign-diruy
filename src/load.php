<?php
include_once(__DIR__ . '/../vendor/autoload.php');

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

/**** Mailer **********/
$transport = Transport::fromDsn($_SERVER['MAILER_URL']);

print_r($transport);
exit;
$mailer = new Mailer($transport);
/************Log **********/



// create a log channel
$log = new Logger('app_' . $_SERVER['APP_ENV']);
$log->pushHandler(new StreamHandler(__DIR__ . '/../var/' . $_SERVER['APP_ENV'] . '.log', Logger::WARNING));

include_once(__DIR__ . '/cutly.php');
