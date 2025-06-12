<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

require_once('websockets.php');
require_once('users.php');
require_once('config.php');

class SimpleServer extends WebSocketServer {
    function process($user, $message) {
        echo "User $user->id says $message\n";
        
        $this->send($user, "Server received: " . $message);
        
        foreach ($this->users as $u) {
            if ($u !== $user) {
                $this->send($u, "User $user->id says: $message");
            }
        }
    }
    
    function connected($user) {
        echo "User $user->id connected\n";
        $this->send($user, "Welcome to the WebSocket server!");
        
        foreach ($this->users as $u) {
            if ($u !== $user) {
                $this->send($u, "User $user->id has joined the chat.");
            }
        }
    }
    
    function closed($user) {
        echo "User $user->id disconnected\n";
        foreach ($this->users as $u) {
            $this->send($u, "User $user->id has left the chat.");
        }
    }
}

$server = new SimpleServer($config['host'], $config['port'], $config['buffer_length']);
$server->run();
?> 