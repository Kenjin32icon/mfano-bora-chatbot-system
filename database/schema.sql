-- ============================================================================
-- Mfano Bora Africa Chatbot - Knowledge Base & System Schema
-- Target: Supabase (PostgreSQL 15+)
-- Run this in: Supabase Dashboard -> SQL Editor -> New Query -> paste -> Run
-- ============================================================================

-- 1. Extensions -------------------------------------------------------------
create extension if not exists vector;      -- pgvector, for semantic search
create extension if not exists pg_trgm;     -- trigram, for fuzzy keyword search
create extension if not exists pgcrypto;    -- for gen_random_uuid()

-- 2. Category taxonomy -------------------------------------------------------
-- Matches the SSOT structural categories identified in Task 2/4:
-- Primary Web Portal | Institutional & Program Records |
-- Organisational Registries | Dynamic Admin & User Logs
create table if not exists kb_categories (
    id            uuid primary key default gen_random_uuid(),
    slug          text unique not null,           -- e.g. 'attachment-internship'
    name          text not null,                   -- e.g. 'Attachment & Internship Resources'
    source_type   text not null check (source_type in (
                    'primary_web_portal',
                    'institutional_program_record',
                    'organisational_registry',
                    'dynamic_admin_log'
                  )),
    parent_group  text,                             -- Resources.pdf top-level grouping
    update_frequency text,                          -- Monthly / Quarterly / Bi-Annually / As Needed
    created_at    timestamptz not null default now()
);

-- 3. Knowledge base entries (Dublin-Core-inspired metadata) -----------------
create table if not exists knowledge_base (
    id              uuid primary key default gen_random_uuid(),
    category_id     uuid references kb_categories(id) on delete set null,

    -- Dublin Core fields
    dc_title        text not null,
    dc_creator      text default 'Mfano Bora Africa',
    dc_subject      text[],                          -- keywords/tags
    dc_description  text,
    dc_source       text,                             -- originating URL/document
    dc_date         date default current_date,
    dc_format       text default 'text/plain',
    dc_language     text default 'en',
    dc_rights       text default 'Internal use - Mfano Bora Africa',

    -- Chatbot content
    content_chunk   text not null,                   -- cleaned, chunked text passed to the LLM
    target_audience text,                             -- e.g. 'Students & Graduates'
    embedding       vector(384),                       -- sentence-transformers/all-MiniLM-L6-v2 size
    search_vector   tsvector generated always as (to_tsvector('english', coalesce(dc_title,'') || ' ' || coalesce(content_chunk,''))) stored,

    is_active       boolean not null default true,
    version         int not null default 1,
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now()
);

create index if not exists idx_kb_search_vector on knowledge_base using gin (search_vector);
create index if not exists idx_kb_trgm on knowledge_base using gin (content_chunk gin_trgm_ops);
create index if not exists idx_kb_embedding on knowledge_base using ivfflat (embedding vector_cosine_ops) with (lists = 100);

-- 4. Curated FAQ pairs (fast-path, bypasses retrieval + LLM entirely) --------
create table if not exists faq_entries (
    id          uuid primary key default gen_random_uuid(),
    question    text not null,
    answer      text not null,
    category_id uuid references kb_categories(id) on delete set null,
    is_active   boolean not null default true,
    created_at  timestamptz not null default now()
);

-- 5. Admin users + simple role-based access control --------------------------
create table if not exists admin_users (
    id            uuid primary key default gen_random_uuid(),
    email         text unique not null,
    password_hash text not null,
    role          text not null default 'editor' check (role in ('super_admin','editor','viewer')),
    is_active     boolean not null default true,
    created_at    timestamptz not null default now(),
    last_login_at timestamptz
);

-- 6. Dynamic logs: every chat turn, for gap analysis & continuous improvement
create table if not exists chat_logs (
    id                uuid primary key default gen_random_uuid(),
    session_id        text not null,
    user_message      text not null,
    matched_kb_ids    uuid[],                 -- which knowledge_base rows were retrieved
    bot_response      text,
    was_fallback      boolean not null default false,  -- true = "I don't have that info" / handed to human
    confidence_score  numeric(4,3),
    response_time_ms  int,
    user_agent        text,
    created_at        timestamptz not null default now()
);

create index if not exists idx_chat_logs_fallback on chat_logs (was_fallback, created_at);
create index if not exists idx_chat_logs_session on chat_logs (session_id);

-- 7. Aggregated metrics for the admin analytics dashboard --------------------
create table if not exists admin_metrics (
    id              uuid primary key default gen_random_uuid(),
    metric_date     date not null default current_date,
    total_queries   int not null default 0,
    fallback_count  int not null default 0,
    avg_confidence  numeric(4,3),
    top_gap_topics  text[],                 -- populated by weekly gap-analysis job
    created_at      timestamptz not null default now(),
    unique (metric_date)
);

-- 8. Row Level Security (Supabase best practice) ------------------------------
alter table knowledge_base enable row level security;
alter table faq_entries    enable row level security;
alter table chat_logs      enable row level security;
alter table admin_metrics  enable row level security;
alter table admin_users    enable row level security;

-- Public (anon key, via the PHP widget) may only READ active KB/FAQ content
create policy "public_read_active_kb" on knowledge_base
    for select using (is_active = true);
create policy "public_read_active_faq" on faq_entries
    for select using (is_active = true);

-- Only the service_role key (used exclusively from the PHP backend, never the browser)
-- may write to knowledge_base, faq_entries, admin_users, or read chat_logs/admin_metrics.
-- No public policies are created for those actions/tables, so anon/authenticated
-- roles are denied by default once RLS is enabled.

-- 9. Helper RPC for semantic + keyword hybrid search --------------------------
create or replace function match_knowledge_base(
    query_embedding vector(384),
    query_text text,
    match_count int default 5
)
returns table (
    id uuid,
    dc_title text,
    content_chunk text,
    target_audience text,
    similarity float
)
language sql stable as $$
    select
        kb.id,
        kb.dc_title,
        kb.content_chunk,
        kb.target_audience,
        1 - (kb.embedding <=> query_embedding) as similarity
    from knowledge_base kb
    where kb.is_active = true
      and (
        kb.embedding is not null
        or kb.search_vector @@ plainto_tsquery('english', query_text)
      )
    order by
        case when kb.embedding is not null
             then kb.embedding <=> query_embedding
             else 1
        end asc,
        ts_rank(kb.search_vector, plainto_tsquery('english', query_text)) desc
    limit match_count;
$$;

-- 10. Seed the four SSOT categories + Resources.pdf top-level groupings -------
insert into kb_categories (slug, name, source_type, parent_group, update_frequency) values
    ('main-web-portal', 'Mfano Bora Main Web Portal', 'primary_web_portal', 'Company Resources', 'Monthly'),
    ('attachment-portal', 'Attachment & Internship Portal', 'primary_web_portal', 'Attachment & Internship Resources', 'Quarterly'),
    ('careers', 'Careers & Professional Development', 'institutional_program_record', 'Careers & Opportunities', 'Quarterly'),
    ('ict-digital-skills', 'ICT & Digital Skills', 'institutional_program_record', 'ICT & Technology Resources', 'Quarterly'),
    ('ict-hub', 'Mfano Africa ICT Hub', 'institutional_program_record', 'ICT & Technology Resources', 'Quarterly'),
    ('transport-logistics', 'Transport & Logistics', 'institutional_program_record', 'Transport & Logistics Resources', 'Bi-Annually'),
    ('road-safety', 'Road Safety', 'institutional_program_record', 'Transport & Logistics Resources', 'Bi-Annually'),
    ('awards-events', 'Awards & Events', 'institutional_program_record', 'Transport & Logistics Resources', 'Bi-Annually'),
    ('location-contact', 'Location & Contact Directory', 'organisational_registry', 'Company Resources', 'As Needed'),
    ('forms-templates', 'Forms & Templates', 'organisational_registry', 'Forms & Downloads', 'As Needed'),
    ('reports-publications', 'Reports, Publications & Research', 'institutional_program_record', 'Publications & Reports', 'Bi-Annually'),
    ('admin-faq-logs', 'Admin FAQ & Chat Audit Logs', 'dynamic_admin_log', 'System', 'Continuous')
on conflict (slug) do nothing;
