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
    "id"                SERIAL,
    "limit"             INT NOT NULL,
    "balance"           INT DEFAULT 0,
    "client_id"    INT NOT null,
    PRIMARY KEY (id)
);

CREATE UNLOGGED TABLE IF NOT EXISTS transactions (
    "id"           SERIAL,
    "value"        INT NOT NULL,
    "type"         VARCHAR(1) NOT NULL,
    "description"  VARCHAR(10) NOT NULL,
    "created_at"   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    "client_id"    INT NOT NULL
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
begin
	PERFORM pg_advisory_xact_lock(p_id_cliente);
    -- Atualiza o saldo e o limite em uma única operação e obtém os valores atualizados
INSERT INTO transactions (client_id, value, "type", description)
    VALUES (p_id_cliente, ABS(p_valor), p_tipo, p_descricao);
    UPDATE clients
    SET balance = balance + p_valor
    WHERE client_id = p_id_cliente AND balance + p_valor >= - "limit"
    RETURNING balance, "limit" INTO saldo_atualizado, limite_atualizado;

    -- Insere a transação
    
    
    -- Retorna os valores atualizados se necessário
    -- (geralmente não é necessário, pois já foram atribuídos acima)
    IF NOT FOUND THEN
        SELECT balance, "limit" INTO saldo_atualizado, limite_atualizado
        FROM clients c
        WHERE client_id = p_id_cliente;
    END IF;
END;
$$;
