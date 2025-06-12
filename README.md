# WebSocket Chat Server

NO HEAVY WEBSOCKET LIBRARY REQUIRED. A lightweight, production-ready WebSocket server implementation in PHP 8 for real-time communication. Under the hood we are doing a reverse proxy (Apache) from https to localhost and down-grading the wss connection to simplify requirements for the connection. Has chat.php to test out chat feature but any bidirectional communication is possible. 



## Features

- **Real-time messaging** - Instant bidirectional communication
- **PHP 8 compatible** - Optimized for modern PHP versions
- **Multi-user support** - Handle multiple simultaneous connections
- **Apache integration** - Reverse proxy support with SSL termination
- **Production ready** - Built for CentOS 9 enterprise environments

## Requirements

- CentOS 9 / RHEL 9
- PHP 8.0+
- Apache 2.4+
- php-sockets extension
- Root/sudo access for setup

## Installation

### 1. Install PHP and Dependencies

```bash
sudo dnf update -y
sudo dnf install -y php php-cli php-sockets httpd
sudo systemctl enable httpd
sudo systemctl start httpd
```

### 2. Clone Repository

```bash
cd /var/www/html
sudo git clone https://github.com/yourusername/websocket-chat-server.git
sudo chown -R apache:apache websocket-chat-server
sudo chmod +x websocket-chat-server/server.php
```

### 3. Configure Apache Modules

```bash
sudo dnf install -y httpd-devel
sudo httpd -M | grep -E "(rewrite|proxy)"
```

Enable required modules in `/etc/httpd/conf/httpd.conf`:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule proxy_module modules/mod_proxy.so  
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
```

### 4. Configure SELinux

```bash
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_can_network_relay 1
```

### 5. Configure Firewall

```bash
sudo firewall-cmd --permanent --add-port=9000/tcp
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Usage

### Start WebSocket Server

```bash
cd /var/www/html/websocket-chat-server
php server.php
```

### Access Chat Interface

Open your browser and navigate to:
```
http://your-server-ip/websocket-chat-server/chat.php
```

### Test Direct WebSocket Connection

```bash
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" \
     -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: test" \
     http://localhost:9000/
```

## Production Deployment

### Create Systemd Service

Create `/etc/systemd/system/websocket-chat.service`:

```ini
[Unit]
Description=WebSocket Chat Server
After=network.target
Wants=network.target

[Service]
Type=simple
User=apache
Group=apache
WorkingDirectory=/var/www/html/websocket-chat-server
ExecStart=/usr/bin/php server.php
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Enable and start service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable websocket-chat.service
sudo systemctl start websocket-chat.service
sudo systemctl status websocket-chat.service
```

### Monitor Service

```bash
sudo journalctl -u websocket-chat.service -f
sudo systemctl status websocket-chat.service
```

## Configuration

### Server Configuration

Edit `config.php` to customize settings:

```php
$config = [
    'host' => 'localhost',
    'port' => 9000,
    'max_connections' => 100,
    'buffer_length' => 2048
];
```

### Apache Virtual Host

For production with SSL, create `/etc/httpd/conf.d/websocket.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/websocket-chat-server
    
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule websocket$ ws://127.0.0.1:9000/ [P,L]
    
    <Directory "/var/www/html/websocket-chat-server">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## File Structure

```
websocket-chat-server/
├── server.php          # Main WebSocket server
├── websockets.php      # WebSocket protocol implementation
├── users.php          # User management classes
├── config.php         # Configuration settings
├── chat.php           # Modern chat interface demo
├── .htaccess          # Apache proxy rules
└── README.md          # This file
```

## Architecture

```
Browser Client (WebSocket) → Apache (Reverse Proxy) → PHP WebSocket Server
                             ↓
                     SSL Termination & Load Balancing
```

## Troubleshooting

### Connection Issues

```bash
netstat -an | grep :9000
ps aux | grep server.php
sudo tail -f /var/log/httpd/error_log
```

### Permission Issues

```bash
sudo chown -R apache:apache /var/www/html/websocket-chat-server
sudo chmod +x server.php
```

### SELinux Issues

```bash
sudo ausearch -m avc -ts recent
sudo setsebool -P httpd_can_network_connect 1
```

### Service Management

```bash
sudo systemctl restart websocket-chat.service
sudo systemctl status websocket-chat.service
sudo journalctl -u websocket-chat.service --no-pager
```

## Performance Tuning

### PHP Configuration

Add to `/etc/php.ini`:
```ini
memory_limit = 256M
max_execution_time = 0
default_socket_timeout = 60
```

### Apache Configuration

Add to `/etc/httpd/conf/httpd.conf`:
```apache
MaxRequestWorkers 400
ThreadsPerChild 25
ServerLimit 16
```

### System Limits

Add to `/etc/security/limits.conf`:
```
apache soft nofile 65536
apache hard nofile 65536
```

## API Reference

### Server Methods

- `process($user, $message)` - Handle incoming messages
- `connected($user)` - Called when user connects
- `closed($user)` - Called when user disconnects
- `send($user, $message)` - Send message to specific user

### Client JavaScript API

```javascript
const socket = new WebSocket('ws://localhost:9000');
socket.onopen = () => console.log('Connected');
socket.onmessage = (event) => console.log(event.data);
socket.send('Hello Server!');
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

MIT License

## Support

- Create GitHub issues for bugs
- Check system logs for troubleshooting
- Verify all requirements are installed
- Test with direct WebSocket connection first 
