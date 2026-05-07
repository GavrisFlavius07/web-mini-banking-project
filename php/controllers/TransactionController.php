<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class transactionController {
  public function showLogs(Request $request, Response $response, $args){
    $conn = Database::instance();

    if(!is_numeric($args['id_account']) ){
      $response->getBody()->write(json_encode(["error" => "invalid id"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }

    $accountId = $args['id_account'];

    $stmt = $conn -> prepare("SELECT * FROM `transaction` WHERE account_id = ? ORDER BY created_at DESC"); 
    
    if(!$stmt->execute([$accountId])){
      $response->getBody()->write(json_encode(["error" => "query error"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC); //per array-zzarlo

    $response->getBody()->write(json_encode($transactions));
    return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
  }



  public function showTrasaction(Request $request, Response $response, $args){
    $conn = Database::instance();

    if(!(is_numeric($args['id'])&& is_numeric($args['id_account'])) ){
      $response->getBody()->write(json_encode(["error" => "invalid id"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $transactionId = $args['id'];

    $stmt = $conn -> prepare("SELECT * FROM `transaction` WHERE id = ?"); 
    if(!$stmt->execute([$transactionId])){
      $response->getBody()->write(json_encode(["error" => "query error"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $transaction = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($transaction));
    return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);
  }



  public function deposit(Request $request, Response $response, $args){
    $conn = Database::instance();
    if(!is_numeric($args['id_account']) ){
      $response->getBody()->write(json_encode(["error" => "invalid id"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $requestBody = json_decode($request->getBody());
    $amount = $requestBody['amount'];
    if ($amount<=0){
      $response->getBody()->write(json_encode(["error" => "invalid amount"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
      
    $accountId = $args['id_account'];

    $sql = "INSERT INTO `transaction` (id_account, amount, type, balance_after, created_at) 
        SELECT 
            ?, -- id_account
            ?, -- amount
            'DEPOSIT',
            COALESCE(MAX(balance_after), 0) + ?,
            NOW()
        FROM `transaction` 
        WHERE id_account = ?
        ORDER BY created_at DESC
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if(!$stmt->execute([$accountId, $amount, $amount, $accountId])){
      $response->getBody()->write(json_encode(["error" => "query error"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $response->getBody()->write(json_encode('Deposit was successful'));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
  }

  

  public function withdraw(Request $request, Response $response, $args){
    $conn = Database::instance();
    
    if(!is_numeric($args['id_account']) ){
      $response->getBody()->write(json_encode(["error" => "invalid id"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $accountId = $args['id_account'];
    
    $requestBody = json_decode($request->getBody(),true);
    $amount = $requestBody['amount'];
    if ($amout<=0){
      $response->getBody()->write(json_encode(["error" => "invalid amount"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $stmtCheck = $conn->prepare("SELECT balance_after FROM `transaction` WHERE id_account = ? ORDER BY created_at DESC LIMIT 1");
    $stmtCheck->execute([$accountId]);
    $lastTransactionsValue = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $currentBalance = $lastTransactionsValue ? $lastTransactionsValue['balance_after'] : 0;

    if ($currentBalance < $amount) {
      $response->getBody()->write(json_encode([
        "error" => "Insufficient funds",
        "current_balance" => $currentBalance
      ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    
    $sql = "INSERT INTO `transaction` (id_account, amount, type, balance_after, created_at) 
        SELECT 
            ?, -- id_account
            ?, -- amount
            'WITHDRAW',
            COALESCE(MAX(balance_after), 0) - ?,
            NOW()
        FROM `transaction` 
        WHERE id_account = ?
        ORDER BY created_at DESC
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if(!$stmt->execute([$accountId, $amount, $amount, $accountId])){
      $response->getBody()->write(json_encode(["error" => "query error"]));
      return $response -> withHeader('Content-Type', 'application/json') -> withStatus(400);
    }
    $response->getBody()->write(json_encode('Deposit was successful'));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
  }
}
