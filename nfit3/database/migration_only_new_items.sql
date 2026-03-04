-- Execute com a base selecionada no phpMyAdmin (ou descomente uma opcao abaixo).
-- USE `edua0932_nutremfit`;
-- USE `edua6319_nutremfit`;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

DELIMITER $$

DROP PROCEDURE IF EXISTS nf_add_column_if_missing_new $$
CREATE PROCEDURE nf_add_column_if_missing_new(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE BINARY TABLE_SCHEMA = BINARY DATABASE()
          AND BINARY TABLE_NAME = BINARY p_table
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY DATABASE()
          AND BINARY TABLE_NAME = BINARY p_table
          AND BINARY COLUMN_NAME = BINARY p_column
    ) THEN
        SET @sql := CONCAT(
            'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS nf_add_index_if_missing_new $$
CREATE PROCEDURE nf_add_index_if_missing_new(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_ddl TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE BINARY TABLE_SCHEMA = BINARY DATABASE()
          AND BINARY TABLE_NAME = BINARY p_table
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE BINARY TABLE_SCHEMA = BINARY DATABASE()
          AND BINARY TABLE_NAME = BINARY p_table
          AND BINARY INDEX_NAME = BINARY p_index
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` ', p_ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL nf_add_column_if_missing_new('users', 'phone', 'VARCHAR(30) NULL');
CALL nf_add_index_if_missing_new('exercicios', 'idx_exercicios_nome', 'ADD KEY `idx_exercicios_nome` (`nome_exercicio`)');

DROP PROCEDURE IF EXISTS nf_add_column_if_missing_new;
DROP PROCEDURE IF EXISTS nf_add_index_if_missing_new;
