# What is an AI MCP Server?

**MCP** stands for **Model Context Protocol**. It is an open standard that enables AI models (like Claude, ChatGPT, or GitHub Copilot) to interact securely and standardized with your local environment, data, and tools.

Think of it as a **Universal API** or a "Driver" that lets an AI "plug in" to your specific server, database, or application.

## How It Works
1.  **The AI (Client)**: The AI interface you use (e.g., VS Code with Copilot, Claude Desktop).
2.  **The Protocol (MCP)**: A standard language for asking "What tools do you have?" and "Please run this tool."
3.  **The Server (MCP Server)**: A lightweight program running on your machine (e.g., this Linux server) that exposes specific capabilities to the AI.

## Why Use It Here? (IrmaJosh.com & Game Servers)

Since you run multiple websites and game servers on this machine, an MCP server can act as a **Central Command Center** for your AI assistant.

### 1. Website Management (IrmaJosh.com, etc.)
Instead of asking the AI to write a PHP script to check the database, an MCP server can expose tools like:
-   `query_database(sql)`: Safely run read-only queries against your MySQL DB.
-   `tail_logs(file)`: Watch `app.log` or Apache error logs in real-time.
-   `clear_cache()`: Trigger your `bust_cache.sh` script directly.

### 2. Game Server Management
If you host game servers (Minecraft, Ark, Rust, etc.), an MCP server can give the AI "hands" to manage them:
-   **Process Control**: `start_server('minecraft')`, `restart_service('ark-survival')`.
-   **Config Editing**: The AI can read and safely edit `server.properties` or `Game.ini` files.
-   **Log Analysis**: "Why did the server crash?" -> The AI pulls the latest crash report via the MCP server and analyzes it instantly.

### 3. System Administration
-   **Nginx/Apache**: Manage vhosts for multiple domains (`/etc/apache2/sites-available/`).
-   **System Health**: Check CPU/RAM usage, disk space, or active connections.

## Example Scenario
**Without MCP:**
> *You:* "Check why the Minecraft server is lagging."
> *AI:* "Please run `top` and paste the output. Then check the logs at `/home/mc/logs/latest.log` and paste the last 50 lines."

**With MCP:**
> *You:* "Check why the Minecraft server is lagging."
> *AI:* (Silently calls `get_system_stats` and `read_game_log`) "I see CPU usage is at 98% and the logs show 'Can't keep up!' warnings. It seems a backup script is running. Shall I stop the backup?"

## How to Implement
You can run an MCP server using Node.js or Python.
1.  **Install**: `npm install -g @modelcontextprotocol/server-filesystem` (for file access) or custom servers for specific tools.
2.  **Configure**: Tell your AI client (e.g., Claude Desktop or a VS Code extension) where the server is running.
3.  **Use**: The AI will automatically detect the tools available on your server.

By setting up an MCP server, you turn your AI from a "text generator" into a **System Administrator** that knows your specific infrastructure.
