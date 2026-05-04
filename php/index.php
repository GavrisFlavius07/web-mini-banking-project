<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AccountController.php';

$app = AppFactory::create();

$app->get('/account',"AccountController:accounts");
$app->get('/currency',"AccountController:currencies");
$app->get('/trans',"AccountController:transactions");

$app->run();