<?php
/**
 * Database.php - คลาสจัดการการเชื่อมต่อฐานข้อมูล PDO
 */

class Database {
    
    private static $instance = null;
    private $connection;
    private $queryCount = 0;
    private $queryLog = [];
    private $transactionCounter = 0;
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            $this->handleError($e, "Database Connection Error");
            die(json_encode([
                'error' => 'Database connection failed',
                'message' => APP_ENV === 'development' ? $e->getMessage() : 'Please contact administrator'
            ]));
        }
    }
    
    /**
     * Get database instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
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
                $startTime = microtime(true);
            }
            
            $stmt = $this->connection->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            
            if (APP_ENV === 'development') {
                $endTime = microtime(true);
                $this->queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
                    'timestamp' => date('H:i:s')
                ];
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Bind parameters to statement
     */
    private function bindParams($stmt, $params) {
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                // Positional parameter (index + 1)
                $paramType = $this->getParamType($value);
                $stmt->bindValue($key + 1, $value, $paramType);
            } else {
                // Named parameter
                $paramType = $this->getParamType($value);
                $stmt->bindValue($key, $value, $paramType);
            }
        }
    }
    
    /**
     * Get PDO parameter type
     */
    private function getParamType($value) {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
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
     * SELECT key-value pairs for dropdown
     */
    public function fetchPairs($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $results[$row[0]] = $row[1] ?? $row[0];
        }
        return $results;
    }
    
    /**
     * SELECT as objects
     */
    public function fetchObject($sql, $params = [], $className = 'stdClass') {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, $className);
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
            $this->handleError($e, $sql, $data);
            return false;
        }
    }
    
    /**
     * INSERT multiple rows
     */
    public function insertMultiple($table, $data) {
        if (empty($data)) {
            return false;
        }
        
        $fields = array_keys($data[0]);
        $placeholders = [];
        $values = [];
        
        foreach ($data as $row) {
            $rowPlaceholders = [];
            foreach ($fields as $field) {
                $rowPlaceholders[] = '?';
                $values[] = $row[$field] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES " . implode(', ', $placeholders);
        
        try {
            $stmt = $this->query($sql, $values);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $values);
            return false;
        }
    }
    
    /**
     * INSERT on DUPLICATE KEY UPDATE
     */
    public function insertOnDuplicate($table, $data, $updateFields = []) {
        if (empty($updateFields)) {
            $updateFields = array_keys($data);
        }
        
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        
        $updates = [];
        foreach ($updateFields as $field) {
            $updates[] = "{$field} = VALUES({$field})";
        }
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ") 
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        
        try {
            $this->query($sql, $data);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $data);
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
            $this->handleError($e, $sql, $params);
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
            $this->handleError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if (!$this->transactionCounter++) {
            return $this->connection->beginTransaction();
        }
        return $this->transactionCounter >= 0;
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if (!--$this->transactionCounter) {
            return $this->connection->commit();
        }
        return $this->transactionCounter >= 0;
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->transactionCounter >= 0) {
            $this->transactionCounter = 0;
            return $this->connection->rollBack();
        }
        $this->transactionCounter = 0;
        return false;
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
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
     * Escape string for safe use (not recommended, use prepared statements)
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
    
    /**
     * Handle database errors
     */
    private function handleError($e, $sql = '', $params = []) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Log error
        $logMessage = date('Y-m-d H:i:s') . " - Database Error: {$errorCode}\n";
        $logMessage .= "Message: {$errorMessage}\n";
        $logMessage .= "SQL: {$sql}\n";
        $logMessage .= "Params: " . print_r($params, true) . "\n";
        $logMessage .= "Trace: " . $e->getTraceAsString() . "\n";
        $logMessage .= str_repeat('-', 80) . "\n";
        
        error_log($logMessage, 3, LOGS_PATH . 'database.log');
        
        if (APP_ENV === 'development') {
            echo "<pre style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
            echo "<strong>Database Error:</strong> {$errorMessage}\n";
            echo "<strong>SQL:</strong> {$sql}\n";
            echo "<strong>Params:</strong> " . print_r($params, true) . "\n";
            echo "</pre>";
        }
    }
    
    /**
     * Get table columns info
     */
    public function getTableColumns($table) {
        $sql = "SHOW COLUMNS FROM {$table}";
        return $this->fetchAll($sql);
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        return $this->fetchValue($sql, [$table]) !== false;
    }
    
    /**
     * Get table size
     */
    public function getTableSize($table) {
        $sql = "SELECT 
                data_length + index_length as size
                FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = ?";
        return $this->fetchValue($sql, [DB_NAME, $table]);
    }
    
    /**
     * Optimize table
     */
    public function optimizeTable($table) {
        $sql = "OPTIMIZE TABLE {$table}";
        return $this->query($sql);
    }
    
    /**
     * Repair table
     */
    public function repairTable($table) {
        $sql = "REPAIR TABLE {$table}";
        return $this->query($sql);
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        $stats = [];
        
        // Table list with row count
        $sql = "SELECT 
                table_name,
                table_rows,
                data_length,
                index_length,
                (data_length + index_length) as total_size,
                create_time,
                update_time
                FROM information_schema.tables 
                WHERE table_schema = ?
                ORDER BY table_name";
        
        $tables = $this->fetchAll($sql, [DB_NAME]);
        
        foreach ($tables as $table) {
            $stats['tables'][$table['table_name']] = [
                'rows' => (int)$table['table_rows'],
                'data_size' => $this->formatBytes($table['data_length']),
                'index_size' => $this->formatBytes($table['index_length']),
                'total_size' => $this->formatBytes($table['total_size']),
                'created' => $table['create_time'],
                'updated' => $table['update_time']
            ];
        }
        
        // Total size
        $stats['total_size'] = $this->formatBytes(array_sum(array_column($tables, 'total_size')));
        $stats['total_tables'] = count($tables);
        $stats['total_rows'] = array_sum(array_column($tables, 'table_rows'));
        
        return $stats;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// Helper function for quick database access
if (!function_exists('db')) {
    function db() {
        return Database::getInstance();
    }
}