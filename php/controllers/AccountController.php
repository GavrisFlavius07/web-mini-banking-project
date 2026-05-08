<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class AccountController{
  public function accounts(Request $request, Response $response, $args){
    $conn = Database::instance();
    $result = $conn->query("SELECT * FROM account");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($rows));
    return $response->withHeader("Content-Type", "application/json")->withStatus(200);
  }

  public function currencies(Request $request, Response $response, $args){
    $conn = Database::instance();
    $result = $conn->query("SELECT * FROM currency");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($rows));
    return $response->withHeader("Content-Type", "application/json")->withStatus(200);
  }

  public function transactions(Request $request, Response $response, $args){
    $conn = Database::instance();
    $result = $conn->query("SELECT * FROM transaction");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($rows));
    return $response->withHeader("Content-Type", "application/json")->withStatus(200);
  }
}