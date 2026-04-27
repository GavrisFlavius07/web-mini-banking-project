<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../database/Database.php';

class BalanceController
{
  public function show(Request $request, Response $response, $args) {
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      return $response->withBody("Invalid account id")->withStatus(400);
    }
    $id_account = $args['id_account'];

    $stmt = $conn->prepare("SELECT * FROM `transaction` WHERE `id_account` = ?");
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      return $response->withBody('Query error')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    // TODO: test on dummy data

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
}
