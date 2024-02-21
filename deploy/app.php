<?php

class DbRaw
{
    public static PDO $instance;
    public static PDOStatement|null $db = null;
    public static PDOStatement|null $statement1 = null;
    public static PDOStatement|null $statement2 = null;
    public static PDOStatement|null $statement3 = null;
    /**
     * @var []PDOStatement
     */
    private static $update;

    public static function init()
    {
        try {
            
            $pdo = new PDO(
                'pgsql:host=db;dbname=rinhadb',
                'postgre',
                'postgre',
                [
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
    
            self::$statement1 = $pdo->prepare("CALL inserir_transacao_2(:id, :valor, :tipo, :descricao, :saldo_atualizado, :limite_atualizado)");
            self::$statement2 = $pdo->prepare("
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
            self::$statement3 = $pdo->prepare("SELECT id from clients where client_id = :client_id FOR UPDATE");

            self::$instance = $pdo;
        } catch (\Exception $th) {
            $file = fopen('erro.log', 'a');

            // Escreve a mensagem de erro no arquivo
            fwrite($file, $th->getMessage() . "\n");

            // Fecha o arquivo
            fclose($file);
        }
    }

    /**
     * Postgres bulk update
     *
     * @param array $worlds
     * @return void
     */
    public static function update(array $worlds)
    {
        $rows = count($worlds);

        if (!isset(self::$update[$rows])) {
            $sql = 'UPDATE world SET randomNumber = CASE id'
                . str_repeat(' WHEN ?::INTEGER THEN ?::INTEGER ', $rows)
                . 'END WHERE id IN ('
                . str_repeat('?::INTEGER,', $rows - 1) . '?::INTEGER)';

            self::$update[$rows] = self::$instance->prepare($sql);
        }

        $val = [];
        $keys = [];
        foreach ($worlds as $world) {
            $val[] = $keys[] = $world['id'];
            $val[] = $world['randomNumber'];
        }

        self::$update[$rows]->execute([...$val, ...$keys]);
    }

    /**
     * Alternative bulk update in Postgres
     *
     * @param array $worlds
     * @return void
     */
    public static function update2(array $worlds)
    {
        $rows = count($worlds);

        if (!isset(self::$update[$rows])) {
            $sql = 'UPDATE world SET randomNumber = temp.randomNumber FROM (VALUES '
                . implode(', ', array_fill(0, $rows, '(?::INTEGER, ?::INTEGER)')) .
                ' ORDER BY 1) AS temp(id, randomNumber) WHERE temp.id = world.id';

            self::$update[$rows] = self::$instance->prepare($sql);
        }

        $val = [];
        foreach ($worlds as $world) {
            $val[] = $world['id'];
            $val[] = $world['randomNumber'];
            //$update->bindParam(++$i, $world['id'], PDO::PARAM_INT);
        }

        self::$update[$rows]->execute($val);
    }
}

function db()
{
    ngx_header_set('Content-Type', 'application/json');

    DbRaw::$random->execute([mt_rand(1, 10000)]);
    echo json_encode(DbRaw::$random->fetch(), JSON_NUMERIC_CHECK);
}

function transacoes1() {
    echo json_encode(['teste']);
    return 400;
}

function transacoes()
{
    ngx_header_set('Content-Type', 'application/json');

    try {
        if (ngx_request_method() != "POST") {
            return ngx_status(405);
        }

        $transactionData = (array) json_decode(ngx_request_body());
        if (
            !isset($transactionData['valor']) || !is_int($transactionData['valor']) ||
            !isset($transactionData['tipo']) || !in_array($transactionData['tipo'], ['c', 'd']) ||
            !isset($transactionData['descricao']) || !is_string($transactionData['descricao']) ||
            strlen($transactionData['descricao']) < 1 || strlen($transactionData['descricao']) > 10
        ) {
            return ngx_status(422);
        }

        $uri = ngx_request_uri();
        $parts = explode('/', $uri);
        $id = $parts[2];
        if ($id > 5) {
            return ngx_status(404);
        }

        if (!isset(DbRaw::$statement1) || is_null(DbRaw::$statement1)) {
            DbRaw::init();
        }

        $saldo = 0;
        $limite = 0;

        // DbRaw::$instance->beginTransaction();
        // DbRaw::$statement3->bindParam(':client_id', $id, PDO::PARAM_INT);
        // DbRaw::$statement3->execute();

        DbRaw::$statement1->bindParam(':id', $id, PDO::PARAM_INT);
        DbRaw::$statement1->bindParam(':valor', $transactionData['valor'], PDO::PARAM_INT);
        DbRaw::$statement1->bindParam(':tipo', $transactionData['tipo'], PDO::PARAM_STR);
        DbRaw::$statement1->bindParam(':descricao', $transactionData['descricao'], PDO::PARAM_STR);
        DbRaw::$statement1->bindParam(':saldo_atualizado', $saldo, PDO::PARAM_INT);
        DbRaw::$statement1->bindParam(':limite_atualizado', $limite, PDO::PARAM_INT);
        DbRaw::$statement1->execute();
        $response = (array) DbRaw::$statement1->fetchObject();
        // $saldo = 0;
        // $limite = 0;
        // $stmt = $pdo->query("CALL INSERIR_TRANSACAO_2($id, {$transactionData['valor']}, '{$transactionData['tipo']}', '{$transactionData['descricao']}', $saldo, $limite)");
        // $response = (array) $stmt->fetchObject();

        // DbRaw::$instance->commit();

        echo json_encode([
            'saldo' => $response['saldo_atualizado'],
            'limite' => $response['limite_atualizado'],
        ]);
    } catch (\Exception $e) {
        // DbRaw::$instance->rollBack();
        var_dump($e->getMessage());
    }

    // return ngx_exit(200);
}

function extrato()
{
    try {
        ngx_header_set('Content-Type', 'application/json');
        if (ngx_request_method() != "GET") {
            return ngx_status(405);
        }

        $uri = ngx_request_uri();
        $parts = explode('/', $uri);
        $id = $parts[2];
        if ($id > 5) {
            return ngx_status(404);
        }

        if (!isset(DbRaw::$statement2) || is_null(DbRaw::$statement2)) {
            DbRaw::init();
        }

        // DbRaw::$statement2->bindColumn(':id', $id, PDO::PARAM_INT);
        DbRaw::$statement2->execute(['id' => $id]);

        $clientWithTransactions = DbRaw::$statement2->fetchAll(PDO::FETCH_ASSOC);

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

    } catch (\Exception $e) {
        var_dump($e->getMessage());
    }
}