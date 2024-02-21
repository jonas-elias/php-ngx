SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

CREATE UNLOGGED TABLE IF NOT EXISTS clients (
    "id"                SERIAL PRIMARY KEY,
    "limit"             INT NOT NULL,
    "balance"           INT DEFAULT 0,
    "client_id"    INT NOT null
);

CREATE UNLOGGED TABLE IF NOT EXISTS transactions (
    "id"           SERIAL PRIMARY KEY,
    "client_id"    INT NOT NULL,
    "value"        INT NOT NULL,
    "type"         VARCHAR(1) NOT NULL,
    "description"  VARCHAR(10) NOT NULL,
    "created_at"   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX ids_transacoes_ids_cliente_id ON transactions (client_id);
CREATE INDEX ids_saldos_ids_cliente_id ON clients (client_id);


DO $$
BEGIN
	INSERT INTO clients (client_id, "limit", balance)
	VALUES (1,   1000 * 100, 0),
		   (2,    800 * 100, 0),
		   (3,  10000 * 100, 0),
		   (4, 100000 * 100, 0),
		   (5,   5000 * 100, 0);
END;
$$;

CREATE OR REPLACE PROCEDURE INSERIR_TRANSACAO_2(
    p_id_cliente INTEGER,
    p_valor INTEGER,
    p_tipo TEXT,
    p_descricao TEXT,
    OUT saldo_atualizado INTEGER,
    OUT limite_atualizado INTEGER
)
LANGUAGE plpgsql
AS $$
DECLARE
	saldo_atual int;
	limite_atual int;
begin
	PERFORM pg_advisory_xact_lock(p_id_cliente);
    -- Atualiza o saldo e o limite em uma única operação e obtém os valores atualizados
    SELECT 
		c.limit,
		COALESCE(c.balance, 0)
	INTO
		limite_atual,
		saldo_atual
	FROM clients c
	WHERE c.client_id = p_id_cliente FOR UPDATE;

	IF (saldo_atual - p_valor >= limite_atual * -1) AND p_tipo = 'd' THEN
        INSERT INTO transactions
        VALUES (DEFAULT, p_id_cliente, (p_valor), p_tipo, p_descricao, NOW());

        UPDATE clients
        SET balance = balance - p_valor
        WHERE client_id = p_id_cliente;

    END IF;

    IF p_tipo = 'c' THEN
        INSERT INTO transactions
        VALUES (DEFAULT, p_id_cliente, (p_valor), p_tipo, p_descricao, NOW());

        UPDATE clients
		SET balance = balance + p_valor
		WHERE client_id = p_id_cliente;
    END IF;
    SELECT
        balance,
        "limit"
        into saldo_atualizado, limite_atualizado
        FROM clients
    WHERE client_id = p_id_cliente;
END;
$$;


