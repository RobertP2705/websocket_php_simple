<?php
// Database configuration for demo use
define('DB_HOST', ''); // Change to your SQL Server host
define('DB_NAME', ''); // Change to your database name
define('DB_USER', ''); // Change to your username
define('DB_PASS', ''); // Change to your password
define('DB_PORT', ''); // Default SQL Server port

function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $server_IP = DB_HOST;
            $server_PORT = DB_PORT;
            $server_DB = DB_NAME;
            $sql_USERNAME = DB_USER;
            $sql_PASSWORD = DB_PASS;
            
            // Validate required configuration
            if (empty($server_IP) || empty($server_DB) || empty($sql_USERNAME)) {
                throw new Exception("Database configuration is incomplete. Please check DB_HOST, DB_NAME, and DB_USER settings.");
            }
            
            $pdo = new PDO("odbc:Driver=FreeTDS; Server=$server_IP; Port=$server_PORT; Database=$server_DB; UID=$sql_USERNAME; PWD=$sql_PASSWORD;", 
                          null, null, 
                          array(PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Configuration error: " . $e->getMessage());
        }
    }
    
    return $pdo;
}