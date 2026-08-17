-- Enable the pgvector extension in Supabase
CREATE EXTENSION IF NOT EXISTS vector;

-- Structured knowledge base table (Task 5 deliverable)
CREATE TABLE IF NOT EXISTS knowledge_base (
    id           BIGSERIAL PRIMARY KEY,
    source_url   TEXT NOT NULL,
    category     TEXT NOT NULL,            -- Task 4: classification label
    content      TEXT NOT NULL,            -- cleaned text chunk
    embedding    VECTOR(384),              -- matches all-MiniLM-L6-v2 / gte-small dimension
    target_users TEXT,                     -- e.g. "Students & Graduates"
    update_freq  TEXT,                     -- e.g. "Quarterly", "Monthly"
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- Speeds up similarity search for the RAG retrieval step
CREATE INDEX IF NOT EXISTS knowledge_base_embedding_idx
    ON knowledge_base
    USING ivfflat (embedding vector_cosine_ops)
    WITH (lists = 100);

-- Optional: table to log chat gaps, referenced in Task 13 (Gap Analysis)
CREATE TABLE IF NOT EXISTS chat_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_query  TEXT NOT NULL,
    matched     BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);