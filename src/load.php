<?php
include_once(__DIR__ . '/../vendor/autoload.php');

use Symfony\Component\Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


//recu des valeur du fichier ennv
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

/**** Mailer **********/
$parse_mail_url = parse_url($_SERVER['MAILER_URL']);
parse_str($parse_mail_url['query'],$parse_mail_url_query);

$mailer_transport = new Swift_SmtpTransport($parse_mail_url['host'], $parse_mail_url['port'],$parse_mail_url_query['encryption']);
$mailer_transport->setUsername($parse_mail_url_query['username'])->setPassword($parse_mail_url_query['password']);
// Create the Mailer using your created Transport
$mailer = new Swift_Mailer($mailer_transport);




/************Log **********/
// create a log channel
$log = new Logger('app_' . $_SERVER['APP_ENV']);
$log->pushHandler(new StreamHandler(__DIR__ . '/../var/' . $_SERVER['APP_ENV'] . '.log', Logger::WARNING));
//ajout de cutly
include_once(__DIR__ . '/cutly.php');

/******************* MYSQL **************/
$parse_mysql_url = parse_url($_SERVER['DATABASE_URL_INTRA']);
$mysql = mysqli_connect($parse_mysql_url['host'],$parse_mysql_url['user'],$parse_mysql_url['pass'],substr($parse_mysql_url['path'],1),$parse_mysql_url['port']);

