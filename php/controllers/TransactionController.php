<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class transactionContoller {
  public function showLogs(Request $request, Response $response, $args){
    $conn = Database::instance();

    if(!is_numeric($args['id_account']) ){
      return $response-> withBody() -> withStatus(400);
    }

    $accountID = $args['id_account'];
    $stmt = $conn -> prepare("SELECT * FROM 'transaction' WHERE 'id_account' = ?"); 



    return ;
  }
}
