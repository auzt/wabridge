const fs = require('fs');
const path = require('path');

// Struktur folder dan file
const structure = {
    "config": ["database.php", "config.php", "node_api.php"],
    "includes": ["auth.php", "functions.php", "api_client.php", "webhook_handler.php"],
    "api": ["devices.php", "messages.php", "webhook.php", "auth.php", "status.php"],
    "admin": ["login.php", "dashboard.php", "devices.php", "messages.php", "webhooks.php", "logout.php"],
    "assets/css": ["style.css"],
    "assets/js": ["app.js"],
    "assets/images": [],
    "database": ["whatsapp_bridge.sql"],
    "logs": ["api.log", "webhook.log", "error.log"],
    "webhooks": ["receiver.php"],
    ".": [".htaccess", "index.php", "README.md"]
};

// Root direktori proyek
const root = path.join(__dirname, 'whatsapp-php-bridge');

for (const [folder, files] of Object.entries(structure)) {
    const folderPath = path.join(root, folder);
    fs.mkdirSync(folderPath, { recursive: true });

    files.forEach((file) => {
        const filePath = path.join(folderPath, file);
        fs.writeFileSync(filePath, '', 'utf8'); // Buat file kosong
        console.log(`Created: ${filePath}`);
    });
}
