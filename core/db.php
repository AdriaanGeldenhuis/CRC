<?php
/**
 * CRC Database Connection (PDO)
 * Singleton pattern for database connection
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Database {
    private static ?PDO $instance = null;

    /**
     * Get database connection instance
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Create database connection
     */
    private static function connect(): void {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            if (CRC_DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Database connection failed. Please try again later.');
        }
    }

    /**
     * Execute a query with optional parameters
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch single column value
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Insert and return last insert ID
     */
    public static function insert(string $table, array $data): int|string {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));

        return self::getInstance()->lastInsertId();
    }

    /**
     * Update rows
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $params = array_merge(array_values($data), $whereParams);
        $stmt = self::query($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Delete rows
     */
    public static function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool {
        return self::getInstance()->rollBack();
    }

    /**
     * Check if table exists
     */
    public static function tableExists(string $table): bool {
        $sql = "SHOW TABLES LIKE ?";
        $result = self::fetchColumn($sql, [$table]);
        return $result !== false;
    }
}

// Shorthand function
function db(): PDO {
    return Database::getInstance();
}
