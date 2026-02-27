<?php
/**
 * database.php - การเชื่อมต่อฐานข้อมูล
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $queryCount = 0;
    private $queryLog = [];
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prepare and execute query with parameters
     */
    public function query($sql, $params = []) {
        try {
            $this->queryCount++;
            
            if (APP_ENV === 'development') {
                $this->queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'time' => microtime(true)
                ];
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * SELECT one row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * SELECT all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * SELECT single value
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * INSERT data
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . $placeholders . ")";
        
        try {
            $this->query($sql, $data);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $data);
            return false;
        }
    }
    
    /**
     * UPDATE data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = '';
        foreach (array_keys($data) as $field) {
            $fields .= "{$field} = :{$field}, ";
        }
        $fields = rtrim($fields, ', ');
        
        $sql = "UPDATE {$table} SET {$fields} WHERE {$where}";
        
        try {
            $params = array_merge($data, $whereParams);
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * DELETE data
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Get number of affected rows
     */
    public function rowCount() {
        return $this->queryCount;
    }
    
    /**
     * Get query log
     */
    public function getQueryLog() {
        return $this->queryLog;
    }
    
    /**
     * Log error
     */
    private function logError($e, $sql, $params) {
        $message = "SQL Error: " . $e->getMessage() . "\n";
        $message .= "SQL: " . $sql . "\n";
        $message .= "Params: " . print_r($params, true) . "\n";
        $message .= "Trace: " . $e->getTraceAsString() . "\n";
        
        error_log($message);
        
        if (APP_ENV === 'development') {
            echo "<pre>";
            echo "Database Error: " . $e->getMessage() . "\n";
            echo "SQL: " . $sql . "\n";
            echo "Params: " . print_r($params, true);
            echo "</pre>";
        }
    }
    
    /**
     * Escape string for safe use
     */
    public function escape($value) {
        return substr($this->connection->quote($value), 1, -1);
    }
    
    /**
     * Quote string for safe use
     */
    public function quote($value) {
        return $this->connection->quote($value);
    }
}

// Helper function for quick database access
function db() {
    return Database::getInstance();
}