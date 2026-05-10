<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class BalanceController
{
  public function show(Request $request, Response $response, $args) {
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid account id', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_account = intval($args['id_account']);

    $sql = "SELECT `c`.`name` `currency`, `t`.`balance_after` `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      LEFT JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `a`.`id` = ?
      ORDER BY `t`.`created_at` DESC, `t`.`id` DESC
      LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    if ($row === null) {
      return null;
    }

    if ($row['balance'] === null) {
      $row['balance'] = '0.00';
    }

    return $row;
  }

  private function getCurrencyNames(mysqli $conn): array {
    $result = $conn->query("SELECT `name` FROM `currency` ORDER BY `name`");

    return array_column($result->fetch_all(MYSQLI_ASSOC), 'name');
  }

  private function fetchFiatRate(string $from, string $to): array {
    $url = self::FRANKFURTER_URL . '?base=' . rawurlencode($from) . '&quotes=' . rawurlencode($to);
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'ignore_errors' => true,
      ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
      throw new RuntimeException('Currency provider is unavailable');
    }

    $payload = json_decode($body, true);
    if (!is_array($payload) || !isset($payload[0]['rate']) || !is_numeric($payload[0]['rate'])) {
      throw new RuntimeException('Currency provider returned an invalid response');
    }

    return [
      'rate' => (float) $payload[0]['rate'],
      'date' => $payload[0]['date'] ?? null,
    ];
  }

  public function show(Request $request, Response $response, $args) {
    $id_account = $this->parseAccountId($args);
    if ($id_account === null) {
      return $this->json($response, ['error' => 'Invalid account id', 'code' => 400], 400);
    }

    $currency = $results[0]['currency'];
    $balance = $results[0]['balance'];

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'currency' => $currency,
      'balance' => $balance
    ]));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function convert_fiat(Request $request, Response $response, $args) {
    $id_account = $this->parseAccountId($args);
    if ($id_account === null) {
      return $this->json($response, ['error' => 'Invalid account id', 'code' => 400], 400);
    }

    $params = $request->getQueryParams();
    $to = strtoupper(trim($params['to'] ?? ''));
    if ($to === '') {
      return $this->json($response, ['error' => 'Invalid or missing param "to"', 'code' => 400], 400);
    }

    try {
      $conn = Database::instance();
      if (!in_array($to, $this->getCurrencyNames($conn), true)) {
        return $this->json($response, ['error' => 'Currency "to" not found', 'code' => 404], 404);
      }

    $sql = "SELECT `c`.`name` `curr`, `t`.`balance_after` `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `id_account` = ?
      ORDER BY `t`.`created_at` DESC
      LIMIT 1;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      return $response->withBody('Query error')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($results)) {
      $response->getBody()->write(json_encode(['error' => 'Transaction for given account id not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

      $from = $latest['currency'];
      $balance = (float) $latest['balance'];

    if ($from == $to) {
      $response->getBody()->write(json_encode([
        'id_account' => $id_account,
        'provider' => 'Frankfurter',
        'conversion_type' => 'fiat',
        'from_currency' => $from,
        'to_currency' => $to,
        'original_balance' => $balance,
        'converted_balance' => $balance,
        'rate' => 1.0,
        'date' => null
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $conversion = json_decode(file_get_contents("https://api.frankfurter.dev/v2/rates?base=$from&quotes=$to"), true)[0];
    $rate = $conversion['rate'];
    $date = $conversion['date'] ?? null;

    $converted = round($balance * $rate, 2);

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'provider' => 'Frankfurter',
      'conversion_type' => 'fiat',
      'from_currency' => $from,
      'to_currency' => $to,
      'original_balance' => $balance,
      'converted_balance' => $converted,
      'rate' => $rate,
      'date' => $date
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }

  // TODO: this whole method
  // TODO: fetch base assets

  // APIs:
  // https://api.binance.com/api/v3/ticker/price?symbol=BTCUSD
  // https://api.binance.com/api/v3/exchangeInfo
  public function convert_crypto(Request $request, Response $response, $args) {
    $params = $request->getQueryParams();
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid account id', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_account = intval($args['id_account']);

    if (!isset($params['to'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid or not present param "to"', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $to = $params['to'];

    $currencies = array_map(function ($x) {return $x[0];}, $conn->query("SELECT `name` FROM `currency`")->fetch_all());

    if (!in_array($to, $currencies)) {
      $response->getBody()->write(json_encode(['error' => 'Currency "to" not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $sql = "SELECT `c`.`name` `curr`, `t`.`balance_after` `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `id_account` = ?
      ORDER BY `t`.`created_at` DESC
      LIMIT 1;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      return $response->withBody('Query error')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($results)) {
      $response->getBody()->write(json_encode(['error' => 'Transaction for given account id not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $from = $results[0]['curr'];
    $balance = floatval($results[0]['balance']);

    if ($from == $to) {
      $response->getBody()->write(json_encode(['currency' => $to, 'balance' => $balance]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $conversion = json_decode(file_get_contents("https://api.frankfurter.dev/v2/rates?base=$from&quotes=$to"), true)[0];
    $rate = $conversion['rate'];
    $date = $conversion['date'] ?? null;

    $converted = $balance * $rate;

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'provider' => 'Frankfurter',
      'conversion_type' => 'fiat',
      'from_currency' => $from,
      'to_currency' => $to,
      'original_balance' => $balance,
      'converted_balance' => $converted,
      'rate' => $rate,
      'date' => $date
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }
}
