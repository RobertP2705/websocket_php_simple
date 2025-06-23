<?php
// Increase memory limit for large databases
ini_set('memory_limit', '256M');

require_once('websockets.php');
require_once('config.php');

class DatabaseChangeServer extends WebSocketServer {
    private $pdo;
    private $monitorInterval = 2; // Check every 2 seconds
    private $lastCheck = 0;
    private $changeCallbacks = [];
    
    // Track table hashes and client subscriptions
    private $tableHashes = []; // [tableName => hash]
    private $clientSubscriptions = []; // [userId => [table1, table2, ...]]
    private $activeTables = []; // [tableName => true] - only tables with active subscribers
    
    public function __construct($address, $port) {
        parent::__construct($address, $port);
        $this->pdo = getPDO();
        // Don't discover tables at startup - wait for client subscriptions
        $this->stdout("Server started - waiting for client subscriptions");
    }
    
    /**
     * Register a callback to be called when database changes are detected
     * @param callable $callback Function to call with the changed data
     */
    public function onDatabaseChange($callback) {
        $this->changeCallbacks[] = $callback;
    }
    
    function process($user, $message) {
        $data = json_decode($message, true);
        if (!$data || !isset($data['action'])) {
            return;
        }
        
        switch ($data['action']) {
            case 'ping':
                $this->send($user, json_encode(['action' => 'pong']));
                break;
                
            case 'subscribe':
                $this->handleSubscribe($user, $data);
                break;
                
            case 'unsubscribe':
                $this->handleUnsubscribe($user, $data);
                break;
                
            case 'get_table_data':
                $this->sendTableData($user, $data);
                break;
                
            case 'check_table_exists':
                $this->checkTableExists($user, $data);
                break;
        }
    }
    
    function connected($user) {
        $this->send($user, json_encode([
            'action' => 'welcome',
            'message' => 'Connected to Dynamic Table Monitor Server',
            'user_id' => $user->id
        ]));
        
        // Initialize empty subscriptions for this user
        $this->clientSubscriptions[$user->id] = [];
    }
    
    function closed($user) {
        // Clean up user subscriptions
        if (isset($this->clientSubscriptions[$user->id])) {
            // Unsubscribe from all tables this user was monitoring
            foreach ($this->clientSubscriptions[$user->id] as $tableName) {
                $this->removeTableSubscription($tableName, $user->id);
            }
            unset($this->clientSubscriptions[$user->id]);
        }
        
        // Optional: Notify other clients that user disconnected
        foreach ($this->users as $u) {
            if ($u !== $user) {
                $this->send($u, json_encode([
                    'action' => 'user_disconnected',
                    'user_id' => $user->id
                ]));
            }
        }
    }
    
    protected function tick() {
        $this->checkForDatabaseChanges();
        
        // Periodic garbage collection to free memory
        if (time() % 60 == 0) { // Every minute
            gc_collect_cycles();
        }
    }
    
    private function checkForDatabaseChanges() {
        $currentTime = time();
        if ($currentTime - $this->lastCheck < $this->monitorInterval) {
            return;
        }
        $this->lastCheck = $currentTime;
        
        // Only check tables that have active subscribers
        foreach (array_keys($this->activeTables) as $tableName) {
            $this->checkTableForChanges($tableName);
        }
    }
    
    private function checkTableForChanges($tableName) {
        try {
            // Use a more memory-efficient approach for large tables
            $currentHash = $this->getTableHash($tableName);
            
            if (!isset($this->tableHashes[$tableName]) || $currentHash !== $this->tableHashes[$tableName]) {
                $this->stdout("Change detected in table '{$tableName}', broadcasting to subscribers");
                
                // Only load full data if there are subscribers
                $records = $this->getTableData($tableName);
                $this->broadcastTableChange($tableName, $records);
                $this->tableHashes[$tableName] = $currentHash;
            }
        } catch (Exception $e) {
            $this->stdout("Error checking table '{$tableName}': " . $e->getMessage());
        }
    }
    
    private function handleSubscribe($user, $data) {
        $tableName = $data['table'] ?? null;
        
        if (!$tableName) {
            $this->send($user, json_encode([
                'action' => 'error',
                'message' => "No table name specified"
            ]));
            return;
        }
        
        // Check if table exists in database
        if (!$this->tableExists($tableName)) {
            $this->send($user, json_encode([
                'action' => 'error',
                'message' => "Table '{$tableName}' does not exist in the database"
            ]));
            return;
        }
        
        // Add subscription
        if (!in_array($tableName, $this->clientSubscriptions[$user->id])) {
            $this->clientSubscriptions[$user->id][] = $tableName;
            $this->addTableSubscription($tableName, $user->id);
        }
        
        $this->send($user, json_encode([
            'action' => 'subscribed',
            'table' => $tableName,
            'subscriptions' => $this->clientSubscriptions[$user->id]
        ]));
        
        // Send current data for the subscribed table
        $this->sendTableData($user, ['table' => $tableName]);
    }
    
    private function handleUnsubscribe($user, $data) {
        $tableName = $data['table'] ?? null;
        
        if ($tableName && isset($this->clientSubscriptions[$user->id])) {
            $key = array_search($tableName, $this->clientSubscriptions[$user->id]);
            if ($key !== false) {
                unset($this->clientSubscriptions[$user->id][$key]);
                $this->clientSubscriptions[$user->id] = array_values($this->clientSubscriptions[$user->id]);
                $this->removeTableSubscription($tableName, $user->id);
            }
        }
        
        $this->send($user, json_encode([
            'action' => 'unsubscribed',
            'table' => $tableName,
            'subscriptions' => $this->clientSubscriptions[$user->id]
        ]));
    }
    
    private function addTableSubscription($tableName, $userId) {
        $this->activeTables[$tableName] = true;
        
        // Initialize hash if this is the first subscriber
        if (!isset($this->tableHashes[$tableName])) {
            $this->tableHashes[$tableName] = '';
        }
        
        $this->stdout("User {$userId} subscribed to table '{$tableName}' - now monitoring this table");
    }
    
    private function removeTableSubscription($tableName, $userId) {
        // Check if any other users are still subscribed to this table
        $hasOtherSubscribers = false;
        foreach ($this->clientSubscriptions as $uid => $subscriptions) {
            if ($uid !== $userId && in_array($tableName, $subscriptions)) {
                $hasOtherSubscribers = true;
                break;
            }
        }
        
        // If no other subscribers, stop monitoring this table
        if (!$hasOtherSubscribers) {
            unset($this->activeTables[$tableName]);
            unset($this->tableHashes[$tableName]);
            $this->stdout("No more subscribers for table '{$tableName}' - stopped monitoring");
        }
    }
    
    private function checkTableExists($user, $data) {
        $tableName = $data['table'] ?? '';
        
        if ($this->tableExists($tableName)) {
            $this->send($user, json_encode([
                'action' => 'table_exists',
                'table' => $tableName,
                'exists' => true
            ]));
        } else {
            $this->send($user, json_encode([
                'action' => 'table_exists',
                'table' => $tableName,
                'exists' => false
            ]));
        }
    }
    
    private function tableExists($tableName) {
        try {
            $query = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
            $sql = $this->pdo->prepare($query);
            $sql->execute([$tableName]);
            $result = $sql->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getTableHash($tableName) {
        try {
            // Use a more efficient hash calculation that doesn't load all data into memory
            $query = "SELECT COUNT(*) as count FROM {$tableName}";
            $sql = $this->pdo->prepare($query);
            $sql->execute();
            $countResult = $sql->fetch(PDO::FETCH_ASSOC);
            $count = $countResult['count'];
            
            // For very large tables, use a sample-based hash
            if ($count > 1000) {
                // Use a sample of records for hash calculation
                $query = "SELECT TOP 100 * FROM {$tableName} ORDER BY NEWID()";
                $sql = $this->pdo->prepare($query);
                $sql->execute();
                $sampleRecords = $sql->fetchAll(PDO::FETCH_ASSOC);
                
                $dataString = "count:" . $count . "|";
                foreach ($sampleRecords as $record) {
                    foreach ($record as $value) {
                        $dataString .= (string)$value;
                    } 
                }
                return md5($dataString);
            } else {
                // For smaller tables, load all data
                $query = "SELECT * FROM {$tableName}";
                $sql = $this->pdo->prepare($query);
                $sql->execute();
                $records = $sql->fetchAll(PDO::FETCH_ASSOC);
                return $this->createTableHash($records);
            }
        } catch (Exception $e) {
            $this->stdout("Error getting hash for table '{$tableName}': " . $e->getMessage());
            return '';
        }
    }
    
    private function getTableData($tableName) {
        try {
            // Limit the number of records to prevent memory issues
            $query = "SELECT TOP 1000 * FROM {$tableName}";
            $sql = $this->pdo->prepare($query);
            $sql->execute();
            $records = $sql->fetchAll(PDO::FETCH_ASSOC);
            
            // If we hit the limit, add a warning
            if (count($records) >= 1000) {
                $this->stdout("Warning: Table '{$tableName}' has more than 1000 records. Only showing first 1000.");
            }
            
            return $records;
        } catch (Exception $e) {
            $this->stdout("Error getting data for table '{$tableName}': " . $e->getMessage());
            return [];
        }
    }
    
    private function sendTableData($user, $data) {
        $tableName = $data['table'] ?? null;
        
        if (!$tableName) {
            $this->send($user, json_encode([
                'action' => 'error',
                'message' => "No table name specified"
            ]));
            return;
        }
        
        if (!$this->tableExists($tableName)) {
            $this->send($user, json_encode([
                'action' => 'error',
                'message' => "Table '{$tableName}' not found"
            ]));
            return;
        }
        
        try {
            $records = $this->getTableData($tableName);
            
            $this->send($user, json_encode([
                'action' => 'table_data',
                'table' => $tableName,
                'data' => $records
            ]));
        } catch (Exception $e) {
            $this->send($user, json_encode([
                'action' => 'error',
                'message' => "Failed to fetch table data: " . $e->getMessage()
            ]));
        }
    }
    
    private function createTableHash($records) {
        $dataString = '';
        foreach ($records as $record) {
            foreach ($record as $value) {
                $dataString .= (string)$value;
            }
        }
        return md5($dataString);
    }
    
    private function broadcastTableChange($tableName, $records) {
        $message = json_encode([
            'action' => 'table_changed',
            'table' => $tableName,
            'data' => $records,
            'timestamp' => time()
        ]);
        
        // Only send to clients subscribed to this table
        foreach ($this->users as $user) {
            if (isset($this->clientSubscriptions[$user->id]) && 
                in_array($tableName, $this->clientSubscriptions[$user->id])) {
                $this->send($user, $message);
            }
        }
        
        // Call registered callbacks
        foreach ($this->changeCallbacks as $callback) {
            try {
                $callback($tableName, $records);
            } catch (Exception $e) {
                $this->stdout("Error in change callback: " . $e->getMessage());
            }
        }
    }
}

// Create and start the server
$server = new DatabaseChangeServer("127.0.0.1", "9000");

// Example: Register a callback for custom logic when database changes
$server->onDatabaseChange(function($tableName, $records) {
    echo "Table '{$tableName}' changed - " . count($records) . " records\n";
});

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
}