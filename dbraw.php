<?php

class DbRaw
{
    public static PDO $instance;
    public static PDOStatement $db;
    public static PDOStatement $statement1;
    public static PDOStatement $statement2;
    public static PDOStatement $statement3;
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
                                    transactions ON clients.id = transactions.client_id 
                                WHERE 
                                    clients.id = :id 
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