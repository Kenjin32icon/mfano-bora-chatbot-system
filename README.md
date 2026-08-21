# Mfano Bora Africa Chatbot System — PHP & SQLite Edition

## 📖 1. System Overview

The **Mfano Bora Africa Chatbot System** is an intelligent, event-driven digital assistant engineered to handle online visitor queries, guide candidates to industrial attachment and career portals, explain ICT Hub training and logistics consulting services, and seamlessly embed into the official Mfano Bora Africa web interface.

### Strategic Architectural Refactoring

The original system scaffold was built on a React.js frontend, Node.js/Express backend API, and a cloud PostgreSQL/Supabase instance. While powerful, that stack required a separate Node.js server daemon and build tools, introducing unnecessary hosting costs and deployment friction on PHP-based hosting environments.

The codebase on the **`PHP-Refactor_test`** branch refactors the architecture into a native **HTML/CSS/JS + Vanilla PHP + SQLite** stack. This aligns directly with the host web application, eliminating external build tools (like npm or Webpack), reducing memory overhead, and allowing the chatbot to run directly on standard shared hosting or local PHP environments.

---

## ⚙️ 2. Key Features & How It Works

```
┌────────────────────────────────────────────────────────────────────────┐
│                        User Inquiry (Widget)                           │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      PHP API (api/chat.php)                            │
└──────┬──────────────────────────────────────────────────────────┬──────┘
       │                                                          │
       ▼ (1. FAQ Fast-Path Search)                                 ▼ (2. Knowledge Base Search)
┌───────────────────────────────┐                          ┌───────────────────────────────┐
│ SQLite: faq_entries Table     │                          │ SQLite: knowledge_base Table  │
└──────────────┬────────────────┘                          └──────────────┬────────────────┘
               │                                                          │
   [Match Found?]                                             [Context Found?]
   ├── YES ──► Instant Return (0 LLM Cost)                    ├── YES ──► Pass Context to Groq API
   │                                                          │           (Llama-3 LLM Generation)
   └── NO  ──► Proceed to Knowledge Base                      └── NO  ──► Trigger Anti-Hallucination
                                                                          Fallback + Log Gap in chat_logs

```

* **Curated FAQ Fast-Path:** Before executing complex retrieval logic, incoming queries pass through a fuzzy/trigram similarity check against the `faq_entries` table. Common questions are answered instantly with zero API latency and zero LLM cost.


* **Retrieval-Augmented Generation (RAG):** For complex queries, the system retrieves relevant textual context from the `knowledge_base` SQLite table and feeds it into the Groq API (Llama-3 model) to construct an accurate answer.


* **Zero-Hallucination Fallback:** If no relevant context is retrieved from the database, the bot refrains from generating unverified guesses. Instead, it delivers a standard contact message (`info@mfanoboraafrica.com`) and flags the query in the `chat_logs` table for administrative review.


* **Admin Dashboard & Analytics:** Includes a secure PHP administrative portal (`admin/`) protected by bcrypt password hashing and session control, allowing administrators to manage knowledge entries and inspect unanswered query logs.



---

## 📂 3. Repository Directory Structure

```text
mfano-bora-chatbot-system/
├── .env.example              # Environment configuration template
├── .htaccess                 # Security rules to prevent browser access to sensitive files
├── database/
│   ├── schema.sql            # Core SQLite table schemas (KB, FAQs, Logs, Admin)
│   ├── faq_seed.sql          # Seed dataset for common FAQ fast-path entries
│   └── chatbot.sqlite        # Active SQLite database file (created on setup)
├── api/                      # PHP Backend API endpoints
│   ├── config.php            # Environment loader (.env reader)
│   ├── db.php                # SQLite PDO connection helper with WAL mode
│   ├── embeddings.php        # Semantic search & fallback text matcher logic
│   ├── groq.php              # Groq API Llama-3 client
│   └── chat.php              # Primary POST endpoint for the chat widget
├── admin/                    # Password-protected Knowledge Base Management Portal
│   ├── index.php             # Admin authentication login
│   ├── kb_manage.php         # CRUD dashboard for Knowledge Base entries
│   ├── analytics.php         # Gap analysis & unanswered question reporter
│   ├── logout.php            # Session termination
│   └── admin-style.css       # Portal styling
├── widget/                   # Frontend widget components for website embedding
│   ├── mfano-chatbot-widget.js  # Core Chatbot UI logic & state manager
│   ├── mfano-chatbot-widget.css # Floating chat bubble & window styles
│   └── embed-snippet.html       # HTML snippet for website footer integration
└── scripts/                  # Command-line administrative scripts
    ├── ingest_kb.php         # CSV bulk-ingestion tool for knowledge entries
    ├── sample_content.csv    # Starter knowledge base content
    └── create_admin.php      # CLI script to generate initial admin login

```

---

## 📥 4. Cloning the Repository

To clone the project and switch specifically to the active refactored branch, run the following commands in your terminal:

```bash
# Clone the repository
git clone https://github.com/Kenjin32icon/mfano-bora-chatbot-system.git

# Navigate into the project directory
cd mfano-bora-chatbot-system

# Switch to the PHP-Refactor_test branch
git checkout PHP-Refactor_test

```

---

## 🛠️ 5. Tool Installation & Setup Guide

### System Requirements

* **PHP:** Version 8.1 or higher


* **Required PHP Extensions:** `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`

* **SQLite:** Version 3.x



---

### A. Setup on Linux (Linux Mint / Ubuntu)

1. **Install PHP CLI and Required Extensions:**
```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-curl php-mbstring sqlite3

```


2. **Verify Installation:**
```bash
php -v
php -m | grep -E "sqlite3|curl|mbstring"

```


Ensure `pdo_sqlite`, `sqlite3`, `curl`, and `mbstring` are listed in the output.


3. **Configure Environment Variables:**
```bash
cp .env.example .env
nano .env

```


Populate your `.env` file with your Groq API key (obtainable free from [console.groq.com](https://console.groq.com/keys)):


```ini
DB_DRIVER=sqlite
DB_PATH=database/chatbot.sqlite
GROQ_API_KEY=gsk_your_actual_groq_api_key_here
GROQ_MODEL=llama-3.3-70b-versatile
APP_ENV=development
ALLOWED_ORIGIN=http://localhost:8000

```


4. **Initialize and Seed the SQLite Database:**
```bash
# Create database schema and load FAQ seeds
sqlite3 database/chatbot.sqlite < database/schema.sql
sqlite3 database/chatbot.sqlite < database/faq_seed.sql

# Bulk ingest starter knowledge base content
php scripts/ingest_kb.php scripts/sample_content.csv

# Create the primary admin account
php scripts/create_admin.php admin@mfanoboraafrica.com "YourStrongPassword123!" super_admin

```


5. **Start the Built-in Development Server:**
```bash
php -S 127.0.0.1:8000

```


The backend API is now active at `[http://127.0.0.1:8000/api/chat.php](http://127.0.0.1:8000/api/chat.php)`.



---

### B. Setup on Windows (XAMPP or Standalone PHP)

1. **Using XAMPP:**
* Download and install XAMPP with PHP 8.1+ enabled.
* Move the cloned `mfano-bora-chatbot-system` directory into `C:\xampp\htdocs\chatbot`.


2. **Enable Required Extensions in `php.ini`:**
* Open `C:\xampp\php\php.ini` in a text editor.
* Remove the leading semicolon (`;`) from the following lines to enable them:
```ini
extension=pdo_sqlite
extension=sqlite3
extension=curl
extension=mbstring

```


* Save the file and restart Apache via the XAMPP Control Panel.


3. **Initialize Database via Windows Command Prompt / PowerShell:**
Navigate to `C:\xampp\htdocs\chatbot` and run:
```cmd
copy .env.example .env
php scripts/ingest_kb.php scripts/sample_content.csv
php scripts/create_admin.php admin@mfanoboraafrica.com "YourStrongPassword123!" super_admin

```


*(Note: If `sqlite3.exe` CLI tool is not installed on Windows, running `php scripts/ingest_kb.php` automatically creates the `.sqlite` file structure via PDO)*.



---

## 🔌 6. Integration into the Existing Mfano Bora Website

To deploy the chatbot onto the live or staging Mfano Bora Africa website (assuming a standard PHP/cPanel web root directory like `public_html/`):

1. **Upload Chatbot Files to a Subdirectory:**
Upload the repository files into a subfolder named `chatbot` inside your site's web root:


```text
public_html/
├── index.php
├── footer.php
└── chatbot/          <-- Subdirectory containing api/, widget/, admin/, etc.

```


2. **Verify Security Rules (`.htaccess`):**
Ensure the `.htaccess` file exists inside `public_html/chatbot/` to block public web browsers from downloading sensitive database or configuration files:


```apache
# Block direct access to .env, database files, and CLI scripts
<FilesMatch "^\.env|.*\.sqlite|.*\.sql$">
    Order allow,deny
    Deny from all
</FilesMatch>

```


3. **Embed the Chatbot Widget:**
Open the primary shared layout or footer file of the Mfano Bora website (e.g., `public_html/footer.php` or `public_html/index.html`). Paste the code snippet from `widget/embed-snippet.html` immediately before the closing `</body>` tag:


```html
<!-- Mfano Bora Chatbot Widget Embed Start -->
<link rel="stylesheet" href="/chatbot/widget/mfano-chatbot-widget.css">

<script>
  // Define the PHP Chat API Endpoint
  window.MFANO_CHAT_API = "https://www.mfanoboraafrica.com/chatbot/api/chat.php";
</script>

<script src="/chatbot/widget/mfano-chatbot-widget.js" defer></script>
<!-- Mfano Bora Chatbot Widget Embed End -->
</body>

```


4. **Verify the Live Embed:**
* Visit the Mfano Bora website in a web browser.
* Click the floating chat bubble in the bottom-right corner.


* Ask a seeded question (e.g., *"How do I apply for an industrial attachment?"*) and confirm an instant response is delivered.





---

## 📊 7. Admin Portal & Maintenance

* **Accessing Dashboard:** Navigate to `[https://www.mfanoboraafrica.com/chatbot/admin/]` and log in with your administrative credentials.


* **Knowledge Base Management (`kb_manage.php`):** Add, update, or deactivate knowledge chunks to keep the AI updated on course intakes, logistics services, and company news.


* **Gap Analysis (`analytics.php`):** Review queries logged under "Knowledge Gaps" where the bot was forced to fall back. Use these insights to continuously expand your database content.