# Mfano Bora Africa Chatbot — Refactored (HTML/CSS/JS/PHP + Supabase)

This package replaces the original React + Node.js/MongoDB scaffold with a
stack that matches the main Mfano Bora Africa website (HTML/CSS/JS/PHP), so
it can be dropped straight into the existing hosting environment with no
build step, no Node runtime, and no separate server process.

---

## 1. Analysis of the current system

**What existed before this refactor**
- The compiled system (`mfano-bora-chatbot-system_compiled_system.txt`) showed a
  React `frontend-widget`, Node.js `backend-api`, and MongoDB `database` —
  every file was an empty stub. No working code existed yet.
- The Implementation Plan named **Groq (Llama-3) + Node.js/MongoDB + Firebase RBAC**
  as the intended stack, while the Task 2 knowledge-base sourcing document
  independently specified **PostgreSQL + pgvector + HuggingFace embeddings**
  as the retrieval layer. These two plans conflicted on database technology.
- The knowledge base itself is described as living in Supabase, but no schema,
  RLS policy, or ingestion pipeline had been built.
- `Resources.pdf` defines the actual content surface: 10 resource groupings and
  ~102 recommended documents/guides/forms — none of this is yet structured as
  retrievable chatbot knowledge.

**Gaps identified**
1. No unified data layer — Mongo vs. Postgres/pgvector conflict.
2. No RBAC/security implementation, despite Task 15 requiring it.
3. No knowledge base schema aligned to the SSOT categories from Task 2/4.
4. No FAQ fast-path, so every query would hit the LLM even for simple,
   repetitive questions (unnecessary cost/latency).
5. No gap-analysis mechanism (Task 13) to surface what users ask that the
   bot can't answer.
6. Tech stack (React/Node) doesn't match the live website (HTML/CSS/JS/PHP),
   which would have required a separate Node server/process to keep running
   alongside the existing PHP host — added hosting cost and complexity.

## 2. Improvements made in this refactor

| Area | Improvement |
|---|---|
| **Stack alignment** | Rebuilt entirely in HTML/CSS/JS (widget) + PHP (API/admin), so it runs on the same shared/PHP hosting as the main site — no Node process, no separate deploy pipeline. |
| **Single source of truth** | One Supabase Postgres schema (`database/schema.sql`) resolves the Mongo-vs-Postgres conflict and matches the four SSOT categories from Task 2/4. |
| **Security (Task 15)** | Row Level Security on every table; public (anon) role can only *read* active KB/FAQ rows; all writes require the service-role key used only server-side by PHP; admin panel enforces RBAC (`super_admin` / `editor` / `viewer`) with bcrypt-hashed passwords. |
| **Cost & speed** | Curated FAQ fast-path (`faq_entries` + trigram similarity) answers common questions instantly without calling the LLM at all. |
| **Anti-hallucination** | The Groq/Llama-3 call is only made when relevant KB context is retrieved; if nothing relevant is found, the bot returns a fallback message instead of guessing — and logs the gap. |
| **Hybrid retrieval** | `match_knowledge_base()` SQL function combines pgvector cosine similarity with Postgres full-text search, so it still works even if the embedding API is temporarily unreachable. |
| **Gap analysis (Task 13)** | `admin/analytics.php` surfaces the most frequent unanswered questions so editors know exactly which knowledge-base entries to add next. |
| **Metadata (Task 9)** | `knowledge_base` table carries Dublin Core fields (title, creator, subject, description, source, date, format, rights) as specified in the implementation plan. |
| **Content coverage** | `scripts/sample_content.csv` and `database/faq_seed.sql` seed the KB directly from the categories and FAQs already defined in `Resources.pdf`. |

---

## 3. Project structure

```
mfano-bora-chatbot-php/
├── .env.example              # copy to .env and fill in secrets (never commit .env)
├── .htaccess                 # blocks direct access to .env/scripts/database
├── database/
│   ├── schema.sql            # run first, in Supabase SQL Editor
│   └── faq_seed.sql          # run second, seeds curated FAQ answers
├── api/                      # PHP backend — the only thing the widget talks to
│   ├── config.php
│   ├── db.php
│   ├── embeddings.php
│   ├── groq.php
│   └── chat.php              # POST endpoint: { message, session_id } -> { answer, sources }
├── admin/                    # password-protected KB management dashboard
│   ├── index.php             # login
│   ├── kb_manage.php         # add/edit/deactivate/delete KB entries
│   ├── analytics.php         # gap analysis + usage stats
│   ├── logout.php
│   ├── admin-style.css
│   └── includes/header.php
├── widget/                   # what gets embedded on the public website
│   ├── mfano-chatbot-widget.js
│   ├── mfano-chatbot-widget.css
│   └── embed-snippet.html    # copy-paste block for the site's footer template
└── scripts/                  # command-line only, never web-accessible
    ├── ingest_kb.php          # bulk load a CSV into knowledge_base with embeddings
    ├── sample_content.csv     # starter content derived from Resources.pdf
    └── create_admin.php       # create the first admin login
```

---

## 4. Deployment: exactly where each file goes

Assume the main Mfano Bora Africa website's PHP root is `public_html/`
(this is the standard cPanel/shared-hosting layout — adjust if different).

1. **Create a `chatbot/` folder inside the main site's web root:**
   ```
   public_html/
   └── chatbot/          <-- create this
   ```
2. **Copy the entire contents of this package into `public_html/chatbot/`:**
   - `api/`, `admin/`, `widget/`, `scripts/`, `database/`, `.env.example`, `.htaccess`
   - Result: `public_html/chatbot/api/chat.php`, `public_html/chatbot/admin/index.php`, etc.
3. **Create the real `.env` file** at `public_html/chatbot/.env` (copy from
   `.env.example`) and fill in your actual Supabase, Groq, and HuggingFace
   credentials. Confirm `.htaccess` is active so `.env` cannot be opened
   directly in a browser (test: `https://www.mfanoboraafrica.com/chatbot/.env`
   should return 403).
4. **Set up the database** — in the Supabase Dashboard SQL Editor, run, in order:
   - `database/schema.sql`
   - `database/faq_seed.sql`
5. **Seed initial knowledge** from your hosting terminal (SSH/cPanel Terminal):
   ```bash
   cd public_html/chatbot
   php scripts/ingest_kb.php scripts/sample_content.csv
   ```
6. **Create your first admin login:**
   ```bash
   php scripts/create_admin.php you@mfanoboraafrica.com "YourStrongPassword!" super_admin
   ```
   Admin dashboard is then live at:
   `https://www.mfanoboraafrica.com/chatbot/admin/`
7. **Embed the widget on the public site.** Open the main site's shared
   footer template — commonly `public_html/footer.php`, `includes/footer.php`,
   or your theme's `footer.php` if it's WordPress — and paste the contents of
   `widget/embed-snippet.html` immediately before `</body>`. This adds the
   chat bubble to every page automatically.
8. **Verify:** visit the live site, click the chat bubble bottom-right, and
   ask a seeded question (e.g. "How can I apply for industrial attachment?").

### Nginx note
If the live host runs Nginx instead of Apache, the `.htaccess` rules won't
apply. Add this to the site's Nginx server block instead:
```nginx
location ~ /chatbot/(\.env|scripts/|database/) {
    deny all;
}
```

### PHP requirements
- PHP 8.1+
- Extensions: `pdo_pgsql`, `curl`, `json` (all standard on most shared hosts —
  confirm via `phpinfo()` or ask your host to enable `pdo_pgsql` if missing).

---

## 5. Ongoing maintenance (Tasks 17–20)

- **Weekly:** check `admin/analytics.php` for new "knowledge gaps" and add a
  corresponding `knowledge_base` entry via `admin/kb_manage.php`.
- **Quarterly:** re-run `scripts/ingest_kb.php` with an updated CSV whenever
  attachment cycles, award dates, or ICT Hub course catalogues change.
- **RBAC:** only grant `super_admin` to staff who should be able to delete
  entries; `editor` can add/update; `viewer` can read analytics only.
- **Rotating keys:** if the Groq or HuggingFace API key is ever exposed,
  regenerate it and update `.env` only — no code changes needed.
