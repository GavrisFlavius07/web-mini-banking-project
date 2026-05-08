<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AccountController.php';
require __DIR__ . '/controllers/BalanceController.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$accountController = new AccountController();
$balanceController = new BalanceController();

$app->get('/account', [$accountController, 'accounts']);
$app->get('/currency', [$accountController, 'currencies']);
$app->get('/trans', [$accountController, 'transactions']);
$app->get('/account/{id_account}/transaction', [$accountController, 'transactionsByAccount']);

$app->get('/account/{id_account}/balance', [$balanceController, 'show']);
$app->get('/account/{id_account}/balance/convert/fiat', [$balanceController, 'convert_fiat']);

$app->run();
