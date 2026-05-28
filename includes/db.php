<?php
/**
 * Classe Database – Conexão PDO com padrão Singleton
 *
 * Garante uma única conexão por requisição HTTP e expõe
 * métodos convenientes com prepared statements.
 *
 * @package FORMA4
 */

class Database
{
    /** @var Database|null Instância única */
    private static $instance = null;

    /** @var PDO Conexão PDO ativa */
    private $pdo;

    /**
     * Construtor privado: inicializa a conexão PDO.
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção, registre o erro em log e exiba mensagem genérica
            error_log('[FORMA4] Falha na conexão com o banco: ' . $e->getMessage());
            die('Erro ao conectar com o banco de dados. Verifique as configurações.');
        }
    }

    /**
     * Retorna a instância única da classe.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Executa uma query com bind de parâmetros.
     *
     * @param string $sql    Query SQL com placeholders
     * @param array  $params Parâmetros para binding
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retorna todas as linhas de uma query.
     *
     * @param string $sql
     * @param array  $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Retorna uma única linha de uma query.
     *
     * @param string $sql
     * @param array  $params
     * @return array|false
     */
    public function fetchOne(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Retorna o ID do último INSERT.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Retorna o número de linhas afetadas pela última query.
     */
    public function rowCount(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Inicia uma transação.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Confirma uma transação.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Reverte uma transação.
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /** Bloqueia clonagem */
    private function __clone() {}

    /** Bloqueia unserialize */
    public function __wakeup() {}
}
