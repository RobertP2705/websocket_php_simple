<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Chat Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 600px;
            height: 80vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
        }
        .connection-status {
            font-size: 14px;
            margin-top: 5px;
            opacity: 0.9;
        }
        .status-connected { color: #4CAF50; }
        .status-disconnected { color: #f44336; }
        .status-connecting { color: #ff9800; }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        .message.system {
            justify-content: center;
        }
        .message.system .message-content {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
            font-style: italic;
            text-align: center;
        }
        .message.user {
            justify-content: flex-end;
        }
        .message.other {
            justify-content: flex-start;
        }
        .message-content {
            background: #667eea;
            color: white;
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 70%;
            word-wrap: break-word;
            position: relative;
        }
        .message.user .message-content {
            background: #667eea;
        }
        .message.other .message-content {
            background: #e0e0e0;
            color: #333;
        }
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        .message-input:focus {
            border-color: #667eea;
        }
        .send-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .send-button:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .user-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-top: 5px;
        }
        @media (max-width: 600px) {
            .chat-container {
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div>WebSocket Chat Demo</div>
            <div class="connection-status" id="connectionStatus">Connecting...</div>
            <div class="user-count" id="userCount">🟢 Online</div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message system">
                <div class="message-content">
                    Welcome to the WebSocket Chat Demo!<br>
                    This demonstrates real-time messaging using WebSocket technology.
                </div>
            </div>
        </div>
        
        <div class="chat-input">
            <input type="text" 
                   id="messageInput" 
                   class="message-input" 
                   placeholder="Type your message here..." 
                   maxlength="500">
            <button id="sendButton" class="send-button">Send</button>
        </div>
    </div>

    <script>
        class ChatApp {
            constructor() {
                this.socket = null;
                this.connected = false;
                this.reconnectAttempts = 0;
                this.maxReconnectAttempts = 5;
                this.reconnectDelay = 3000;
                
                this.messageInput = document.getElementById('messageInput');
                this.sendButton = document.getElementById('sendButton');
                this.chatMessages = document.getElementById('chatMessages');
                this.connectionStatus = document.getElementById('connectionStatus');
                this.userCount = document.getElementById('userCount');
                
                this.setupEventListeners();
                this.connect();
            }
            
            setupEventListeners() {
                this.sendButton.addEventListener('click', () => this.sendMessage());
                this.messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.sendMessage();
                    }
                });
            }
            
            connect() {
                try {
                    this.socket = new WebSocket('ws://localhost:9000');
                    
                    this.socket.onopen = () => {
                        this.connected = true;
                        this.reconnectAttempts = 0;
                        this.updateStatus('Connected', 'connected');
                        this.sendButton.disabled = false;
                    };
                    
                    this.socket.onmessage = (event) => {
                        this.handleMessage(event.data);
                    };
                    
                    this.socket.onclose = (event) => {
                        this.connected = false;
                        this.sendButton.disabled = true;
                        
                        if (this.reconnectAttempts < this.maxReconnectAttempts) {
                            this.reconnectAttempts++;
                            this.updateStatus(`Reconnecting... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`, 'connecting');
                            setTimeout(() => this.connect(), this.reconnectDelay);
                        } else {
                            this.updateStatus('Disconnected', 'disconnected');
                            this.addSystemMessage('Connection lost. Please refresh the page to reconnect.');
                        }
                    };
                    
                    this.socket.onerror = (error) => {
                        console.error('WebSocket error:', error);
                        this.updateStatus('Connection Error', 'disconnected');
                    };
                    
                } catch (error) {
                    console.error('Failed to connect:', error);
                    this.updateStatus('Failed to Connect', 'disconnected');
                }
            }
            
            sendMessage() {
                const message = this.messageInput.value.trim();
                if (!message || !this.connected) return;
                
                this.socket.send(message);
                this.addMessage(message, 'user');
                this.messageInput.value = '';
            }
            
            handleMessage(data) {
                if (data.includes('Server received:')) {
                    return;
                } else if (data.includes('Welcome to the WebSocket server!')) {
                    this.addSystemMessage('Successfully joined the chat!');
                } else if (data.includes('says:')) {
                    this.addMessage(data, 'other');
                } else {
                    this.addSystemMessage(data);
                }
            }
            
            addMessage(message, type) {
                const messageElement = document.createElement('div');
                messageElement.className = `message ${type}`;
                
                const contentElement = document.createElement('div');
                contentElement.className = 'message-content';
                contentElement.textContent = message;
                
                const timeElement = document.createElement('div');
                timeElement.className = 'message-time';
                timeElement.textContent = new Date().toLocaleTimeString();
                
                messageElement.appendChild(contentElement);
                contentElement.appendChild(timeElement);
                
                this.chatMessages.appendChild(messageElement);
                this.scrollToBottom();
            }
            
            addSystemMessage(message) {
                const messageElement = document.createElement('div');
                messageElement.className = 'message system';
                
                const contentElement = document.createElement('div');
                contentElement.className = 'message-content';
                contentElement.textContent = message;
                
                messageElement.appendChild(contentElement);
                this.chatMessages.appendChild(messageElement);
                this.scrollToBottom();
            }
            
            updateStatus(status, statusClass) {
                this.connectionStatus.textContent = status;
                this.connectionStatus.className = `connection-status status-${statusClass}`;
            }
            
            scrollToBottom() {
                this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            }
        }
        
        window.addEventListener('DOMContentLoaded', () => {
            new ChatApp();
        });
    </script>
</body>
</html> 