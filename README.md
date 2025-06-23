# PHP WebSocket Server with Database Integration

A lightweight, production-ready WebSocket server in PHP 8 for real-time communication and live database monitoring. No heavy libraries required.

## Features

- **Real-time messaging** and live database table monitoring
- **SQL Server integration** (see `config.php`)
- **Multi-user support** with table subscriptions
- **Production ready**: works behind Apache/Nginx reverse proxy
- **Sample HTML/JS client** included for easy testing

## Requirements

- PHP 8.0+ with `php-sockets` and `pdo_mysql`
- MySQL or MariaDB
- Apache/Nginx (for reverse proxy, optional)
- Linux or Windows

## Quick Start

### 1. Clone and Configure

```bash
git clone https://github.com/yourusername/websocket-php-simple.git
cd websocket-php-simple
cp config.php.example config.php
# Edit config.php with your database credentials
```

### 2. Set Up Database

Create a database (e.g., `websocket_demo`) and user, then import or let the server create the tables (`items`, `conductors`) as needed.

### 3. Start the WebSocket Server

```bash
php server.php
```

### 4. Try the Demo Client

Open `demo-client.html` in your browser (see below for code).

## WebSocket API

- `subscribe` to a table:  
  `{ "action": "subscribe", "table": "items" }`
- `unsubscribe` from a table:  
  `{ "action": "unsubscribe", "table": "items" }`
- Receive real-time updates when the table changes.

## Demo Client

A simple HTML+JS client for testing:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WebSocket PHP Simple Demo</title>
  <style>
    body { font-family: sans-serif; margin: 2em; }
    #log { border: 1px solid #ccc; padding: 1em; height: 300px; overflow-y: auto; background: #f9f9f9; }
    input, button { font-size: 1em; }
  </style>
</head>
<body>
  <h2>WebSocket PHP Simple Demo Client</h2>
  <div>
    <label>Table to subscribe: <input id="table" value="items"></label>
    <button onclick="subscribe()">Subscribe</button>
    <button onclick="unsubscribe()">Unsubscribe</button>
  </div>
  <div id="log"></div>
  <script>
    const log = msg => {
      document.getElementById('log').innerHTML += msg + '<br>';
    };
    let ws;
    function connect() {
      ws = new WebSocket('ws://localhost:9000');
      ws.onopen = () => log('<b>Connected</b>');
      ws.onmessage = e => log('<span style="color:green">' + e.data + '</span>');
      ws.onclose = () => log('<b>Disconnected</b>');
      ws.onerror = e => log('<span style="color:red">Error</span>');
    }
    function subscribe() {
      if (!ws || ws.readyState !== 1) connect();
      ws.onopen = () => {
        log('<b>Connected</b>');
        ws.send(JSON.stringify({action: 'subscribe', table: document.getElementById('table').value}));
      };
      if (ws.readyState === 1) {
        ws.send(JSON.stringify({action: 'subscribe', table: document.getElementById('table').value}));
      }
    }
    function unsubscribe() {
      if (ws && ws.readyState === 1) {
        ws.send(JSON.stringify({action: 'unsubscribe', table: document.getElementById('table').value}));
      }
    }
    connect();
  </script>
</body>
</html>
```

## Security

- Change all default credentials before deploying publicly.

## License

MIT

---

## 3. Save the Demo Client

Create a file named `demo-client.html` in your `websocket_php_simple/` directory and paste the HTML code above.

---

If you need further customization or want a more advanced client, just let me know!
