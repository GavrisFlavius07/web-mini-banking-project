<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class BalanceController
{
  private const FRANKFURTER_URL = 'https://api.frankfurter.dev/v2/rates';

  private function json(Response $response, array $payload, int $status = 200): Response {
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
  }

  private function parseAccountId($args) {
    if (!isset($args['id_account']) || !ctype_digit((string) $args['id_account'])) {
      return null;
    }

    return (int) $args['id_account'];
  }

  private function getLatestBalance(mysqli $conn, int $id_account): ?array {
    $sql = "SELECT `a`.`id` AS `id_account`, `c`.`name` AS `currency`, `t`.`balance_after` AS `balance`
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

    try {
      $balance = $this->getLatestBalance(Database::instance(), $id_account);
      if ($balance === null) {
        return $this->json($response, ['error' => 'Account not found', 'code' => 404], 404);
      }

      return $this->json($response, [
        'id_account' => $id_account,
        'currency' => $balance['currency'],
        'balance' => (float) $balance['balance'],
      ]);
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
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

      $latest = $this->getLatestBalance($conn, $id_account);
      if ($latest === null) {
        return $this->json($response, ['error' => 'Account not found', 'code' => 404], 404);
      }

      $from = $latest['currency'];
      $balance = (float) $latest['balance'];

      if ($from === $to) {
        return $this->json($response, [
          'id_account' => $id_account,
          'provider' => 'Frankfurter',
          'conversion_type' => 'fiat',
          'from_currency' => $from,
          'to_currency' => $to,
          'original_balance' => $balance,
          'converted_balance' => $balance,
          'rate' => 1.0,
          'date' => null,
        ]);
      }

      try {
        $conversion = $this->fetchFiatRate($from, $to);
      } catch (Throwable $e) {
        return $this->json($response, ['error' => $e->getMessage(), 'code' => 502], 502);
      }

      return $this->json($response, [
        'id_account' => $id_account,
        'provider' => 'Frankfurter',
        'conversion_type' => 'fiat',
        'from_currency' => $from,
        'to_currency' => $to,
        'original_balance' => $balance,
        'converted_balance' => round($balance * $conversion['rate'], 2),
        'rate' => $conversion['rate'],
        'date' => $conversion['date'],
      ]);
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }
}
