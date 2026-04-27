<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/TransactionController.php';

$app = AppFactory::create();
//GET `/account/{id_account}/transaction` per ottenere l'elenco dei movimenti
$app->get('/account/{id_account}/transaction','transactionController:showLogs');

//GET `/account/{id_account}/transaction/{id}` per ottenere il dettaglio di un movimento
$app->get('/account/{id_account}/transaction/{id}','transactionController:showTrasaction');

//POST `/account/{id_account}/deposit` per registrare un deposito
$app->post('/account/{id_account}/deposit','transactionController:deposit');

//POST `/account/{id_account}/withdrawal` per registrare un prelievo
$app->post('/account/{id_account}/withdrawal','transactionController:withdraw');
//PUT `/account/{id_account}/transaction/{id}` per modificare la descrizione di un movimento
$app->put('/account/{id_account}/transaction/{id}','transactionController:editDescription');

//DELETE `/account/{id_account}/transaction/{id}` per eliminare un movimento (solo l'ultima transazione)
$app->delete('/account/{id_account}/transaction/{id}','transactionController:deleteLast');

$app->run();