<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionController
{
    private function json(Response $response, $data, int $status = 200)
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function accountExists(PDO $conn, int $accountId): bool
    {
        $stmt = $conn->prepare("
            SELECT id
            FROM account
            WHERE id = ?
        ");

        $stmt->execute([$accountId]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCurrentBalance(PDO $conn, int $accountId): float
    {
        $stmt = $conn->prepare("
            SELECT balance_after
            FROM transactions
            WHERE id_account = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->execute([$accountId]);

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        return $transaction
            ? (float)$transaction['balance_after']
            : 0;
    }

    public function showLogs(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        if (!$this->accountExists($conn, $accountId)) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $stmt = $conn->prepare("
            SELECT *
            FROM transactions
            WHERE id_account = ?
            ORDER BY created_at DESC, id DESC
        ");

        if (!$stmt->execute([$accountId])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json($response, $transactions);
    }

    public function showTransaction(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (
            !is_numeric($args['id']) ||
            !is_numeric($args['id_account'])
        ) {
            return $this->json($response, [
                "error" => "Invalid id"
            ], 400);
        }

        $transactionId = (int)$args['id'];
        $accountId = (int)$args['id_account'];

        $stmt = $conn->prepare("
            SELECT *
            FROM transactions
            WHERE id = ?
            AND id_account = ?
        ");

        if (!$stmt->execute([$transactionId, $accountId])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            return $this->json($response, [
                "error" => "Transaction not found"
            ], 404);
        }

        return $this->json($response, $transaction);
    }

    public function deposit(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        if (!$this->accountExists($conn, $accountId)) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $requestBody = json_decode($request->getBody(), true);

        if (!$requestBody) {
            return $this->json($response, [
                "error" => "Invalid JSON body"
            ], 400);
        }

        $amount = (float)($requestBody['amount'] ?? 0);
        $description = trim($requestBody['description'] ?? '');

        if ($amount <= 0) {
            return $this->json($response, [
                "error" => "Invalid amount"
            ], 400);
        }

        $currentBalance = $this->getCurrentBalance($conn, $accountId);

        $newBalance = $currentBalance + $amount;

        $stmt = $conn->prepare("
            INSERT INTO transactions
            (
                id_account,
                type,
                amount,
                description,
                balance_after
            )
            VALUES
            (
                ?,
                'DEPOSIT',
                ?,
                ?,
                ?
            )
        ");

        if (!$stmt->execute([
            $accountId,
            $amount,
            $description,
            $newBalance
        ])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        return $this->json($response, [
            "message" => "Deposit successful",
            "new_balance" => $newBalance
        ], 201);
    }

    public function withdraw(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        if (!$this->accountExists($conn, $accountId)) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $requestBody = json_decode($request->getBody(), true);

        if (!$requestBody) {
            return $this->json($response, [
                "error" => "Invalid JSON body"
            ], 400);
        }

        $amount = (float)($requestBody['amount'] ?? 0);
        $description = trim($requestBody['description'] ?? '');

        if ($amount <= 0) {
            return $this->json($response, [
                "error" => "Invalid amount"
            ], 400);
        }

        $currentBalance = $this->getCurrentBalance($conn, $accountId);

        if ($amount > $currentBalance) {
            return $this->json($response, [
                "error" => "Insufficient funds",
                "current_balance" => $currentBalance
            ], 422);
        }

        $newBalance = $currentBalance - $amount;

        $stmt = $conn->prepare("
            INSERT INTO transactions
            (
                id_account,
                type,
                amount,
                description,
                balance_after
            )
            VALUES
            (
                ?,
                'WITHDRAWAL',
                ?,
                ?,
                ?
            )
        ");

        if (!$stmt->execute([
            $accountId,
            $amount,
            $description,
            $newBalance
        ])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        return $this->json($response, [
            "message" => "Withdrawal successful",
            "new_balance" => $newBalance
        ], 201);
    }

    public function updateTransaction(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (
            !is_numeric($args['id']) ||
            !is_numeric($args['id_account'])
        ) {
            return $this->json($response, [
                "error" => "Invalid id"
            ], 400);
        }

        $transactionId = (int)$args['id'];
        $accountId = (int)$args['id_account'];

        $body = json_decode($request->getBody(), true);

        if (!$body) {
            return $this->json($response, [
                "error" => "Invalid JSON body"
            ], 400);
        }

        $description = trim($body['description'] ?? '');

        $stmt = $conn->prepare("
            UPDATE transactions
            SET description = ?
            WHERE id = ?
            AND id_account = ?
        ");

        if (!$stmt->execute([
            $description,
            $transactionId,
            $accountId
        ])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        if ($stmt->rowCount() === 0) {
            return $this->json($response, [
                "error" => "Transaction not found"
            ], 404);
        }

        return $this->json($response, [
            "message" => "Transaction updated"
        ]);
    }

    public function deleteTransaction(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (
            !is_numeric($args['id']) ||
            !is_numeric($args['id_account'])
        ) {
            return $this->json($response, [
                "error" => "Invalid id"
            ], 400);
        }

        $transactionId = (int)$args['id'];
        $accountId = (int)$args['id_account'];

        $stmt = $conn->prepare("
            SELECT *
            FROM transactions
            WHERE id_account = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->execute([$accountId]);

        $lastTransaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastTransaction) {
            return $this->json($response, [
                "error" => "No transactions found"
            ], 404);
        }

        if ((int)$lastTransaction['id'] !== $transactionId) {
            return $this->json($response, [
                "error" => "Only the last transaction can be deleted"
            ], 422);
        }

        $stmtDelete = $conn->prepare("
            DELETE FROM transactions
            WHERE id = ?
            AND id_account = ?
        ");

        if (!$stmtDelete->execute([
            $transactionId,
            $accountId
        ])) {
            return $this->json($response, [
                "error" => "Query error"
            ], 500);
        }

        return $this->json($response, [
            "message" => "Transaction deleted"
        ]);
    }

    public function getBalance(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.owner_name,
                c.name AS currency
            FROM account a
            INNER JOIN currency c
                ON c.id = a.id_currency
            WHERE a.id = ?
        ");

        $stmt->execute([$accountId]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $balance = $this->getCurrentBalance($conn, $accountId);

        return $this->json($response, [
            "account_id" => $account['id'],
            "owner_name" => $account['owner_name'],
            "currency" => $account['currency'],
            "balance" => $balance
        ]);
    }

    public function convertFiat(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        $params = $request->getQueryParams();

        $to = strtoupper(trim($params['to'] ?? ''));

        if (!$to) {
            return $this->json($response, [
                "error" => "Missing target currency"
            ], 400);
        }

        $stmt = $conn->prepare("
            SELECT
                a.id,
                c.name AS currency
            FROM account a
            INNER JOIN currency c
                ON c.id = a.id_currency
            WHERE a.id = ?
        ");

        $stmt->execute([$accountId]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $from = strtoupper($account['currency']);

        $balance = $this->getCurrentBalance($conn, $accountId);

        $url =
            "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";

        $json = @file_get_contents($url);

        if ($json === false) {
            return $this->json($response, [
                "error" => "Frankfurter API unavailable"
            ], 502);
        }

        $data = json_decode($json, true);

        if (!isset($data['rates'][$to])) {
            return $this->json($response, [
                "error" => "Unsupported target currency"
            ], 400);
        }

        $rate = (float)$data['rates'][$to];

        $convertedBalance = round($balance * $rate, 2);

        return $this->json($response, [
            "account_id" => $accountId,
            "provider" => "Frankfurter",
            "conversion_type" => "fiat",
            "from_currency" => $from,
            "to_currency" => $to,
            "original_balance" => $balance,
            "rate" => $rate,
            "converted_balance" => $convertedBalance,
            "date" => $data['date'] ?? null
        ]);
    }

    public function convertCrypto(Request $request, Response $response, $args)
    {
        $conn = Database::instance();

        if (!is_numeric($args['id_account'])) {
            return $this->json($response, [
                "error" => "Invalid account id"
            ], 400);
        }

        $accountId = (int)$args['id_account'];

        $params = $request->getQueryParams();

        $to = strtoupper(trim($params['to'] ?? ''));

        if (!$to) {
            return $this->json($response, [
                "error" => "Missing target crypto"
            ], 400);
        }

        $stmt = $conn->prepare("
            SELECT
                a.id,
                c.name AS currency
            FROM account a
            INNER JOIN currency c
                ON c.id = a.id_currency
            WHERE a.id = ?
        ");

        $stmt->execute([$accountId]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return $this->json($response, [
                "error" => "Account not found"
            ], 404);
        }

        $fromCurrency = strtoupper($account['currency']);

        $balance = $this->getCurrentBalance($conn, $accountId);

        $marketSymbol = $to . $fromCurrency;

        $exchangeInfoUrl =
            "https://api.binance.com/api/v3/exchangeInfo?symbol={$marketSymbol}";

        $exchangeJson = @file_get_contents($exchangeInfoUrl);

        if ($exchangeJson === false) {
            return $this->json($response, [
                "error" => "Binance API unavailable"
            ], 502);
        }

        $exchangeData = json_decode($exchangeJson, true);

        if (
            !isset($exchangeData['symbols']) ||
            empty($exchangeData['symbols'])
        ) {
            return $this->json($response, [
                "error" => "Invalid Binance market symbol"
            ], 400);
        }

        $priceUrl =
            "https://api.binance.com/api/v3/ticker/price?symbol={$marketSymbol}";

        $priceJson = @file_get_contents($priceUrl);

        if ($priceJson === false) {
            return $this->json($response, [
                "error" => "Unable to fetch crypto price"
            ], 502);
        }

        $priceData = json_decode($priceJson, true);

        if (!isset($priceData['price'])) {
            return $this->json($response, [
                "error" => "Crypto price unavailable"
            ], 400);
        }

        $price = (float)$priceData['price'];

        $convertedAmount = round($balance / $price, 8);

        return $this->json($response, [
            "account_id" => $accountId,
            "provider" => "Binance",
            "conversion_type" => "crypto",
            "from_currency" => $fromCurrency,
            "to_crypto" => $to,
            "market_symbol" => $marketSymbol,
            "original_balance" => $balance,
            "price" => $price,
            "converted_amount" => $convertedAmount
        ]);
    }
}