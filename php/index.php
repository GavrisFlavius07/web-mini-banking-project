<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/BalanceController.php';

$app = AppFactory::create();

$app->get('/account/{id_account}/balance', 'BalanceController:show');
$app->get('/account/{id_account}/balance/convert/fiat', 'BalanceController:convert_fiat');
$app->get('/account/{id_account}/balance/convert/crypto', 'BalanceController:convert_crypto');

$app->run();
