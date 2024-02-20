<?php

$pdo = new PDO(
    'pgsql:host=db;port=5432;dbname=rinhadb;',
    'postgre',
    'postgre',
    [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);

$statement = $pdo->prepare("CALL inserir_transacao_2(:id, :valor, :tipo, :descricao, :saldo_atualizado, :limite_atualizado)");
$statement2 = $pdo->prepare("
        SELECT 
            clients.limit AS limite, 
            clients.balance, 
            transactions.value, 
            transactions.type, 
            transactions.description, 
            transactions.created_at 
        FROM 
            clients 
        LEFT JOIN 
            transactions ON clients.client_id = transactions.client_id 
        WHERE 
            clients.client_id = :id 
        ORDER BY 
            transactions.created_at DESC 
        LIMIT 
        10
");

function transacoes()
{
    ngx_header_set('Content-Type', 'application/json');

    try {
        if (ngx_request_method() != "POST") {
            ngx_exit(405);
        }

        $transactionData = (array) json_decode(ngx_request_body());
        if (
            !isset($transactionData['valor']) || !is_int($transactionData['valor']) ||
            !isset($transactionData['tipo']) || !in_array($transactionData['tipo'], ['c', 'd']) ||
            !isset($transactionData['descricao']) || !is_string($transactionData['descricao']) ||
            strlen($transactionData['descricao']) < 1 || strlen($transactionData['descricao']) > 10
        ) {
            return ngx_exit(422);
        }

        $uri = ngx_request_uri();
        $parts = explode('/', $uri);
        $id = $parts[2];
        if ($id > 5) {
            return ngx_exit(404);
        }

        $saldo = 0;
        $limite = 0;
        global $statement;
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->bindParam(':valor', $transactionData['valor'], PDO::PARAM_INT);
        $statement->bindParam(':tipo', $transactionData['tipo'], PDO::PARAM_STR);
        $statement->bindParam(':descricao', $transactionData['descricao'], PDO::PARAM_STR);
        $statement->bindParam(':saldo_atualizado', $saldo, PDO::PARAM_INT);
        $statement->bindParam(':limite_atualizado', $limite, PDO::PARAM_INT);
        $statement->execute();
        $response = (array) $statement->fetchObject();
        // $saldo = 0;
        // $limite = 0;
        // $stmt = $pdo->query("CALL INSERIR_TRANSACAO_2($id, {$transactionData['valor']}, '{$transactionData['tipo']}', '{$transactionData['descricao']}', $saldo, $limite)");
        // $response = (array) $stmt->fetchObject();

        echo json_encode([
            'saldo' => $response['saldo_atualizado'],
            'limite' => $response['limite_atualizado'],
        ]);
    } catch (\Exception $e) {
        var_dump($e->getMessage());
    }

    return ngx_exit(200);
}

function extrato()
{
    ngx_header_set('Content-Type', 'application/json');

    try {
        if (ngx_request_method() != "GET") {
            ngx_exit(405);
        }

        $uri = ngx_request_uri();
        $parts = explode('/', $uri);
        $id = $parts[2];
        if ($id > 5) {
            return ngx_exit(404);
        }

        global $statement2;
        $statement2->execute(['id' => $id]);
        $clientWithTransactions = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $transactionsClient = array_map(function ($transaction) {
            return [
                'valor' => $transaction['value'],
                'tipo' => $transaction['type'],
                'descricao' => $transaction['description'],
                'realizada_em' => date('c', strtotime($transaction['created_at'])),
            ];
        }, $clientWithTransactions);

        $response = [
            'saldo' => [
                'total' => $clientWithTransactions[0]['balance'],
                'data_extrato' => date('c'), // Usando a data atual
                'limite' => $clientWithTransactions[0]['limite'],
            ],
            'ultimas_transacoes' => $transactionsClient,
        ];

        echo json_encode($response);
        return ngx_exit(200);

    } catch (\Exception $e) {
        var_dump($e->getMessage());
    }
}