-- ============================================================================
-- Mfano Bora Africa Chatbot - Local Knowledge Base & System Schema + Seed Data
-- Engine: SQLite 3
-- File Location: database/schema.sql (Combined with seed data)
-- ============================================================================

-- Enable foreign keys for relational integrity
PRAGMA foreign_keys = ON;

-- 1. Category Taxonomy
CREATE TABLE IF NOT EXISTS kb_categories (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    source_type TEXT NOT NULL CHECK (source_type IN (
        'primary_web_portal',
        'institutional_program_record',
        'organisational_registry',
        'dynamic_admin_log'
    )),
    parent_group TEXT,
    update_frequency TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Knowledge Base Entries (Dublin-Core-inspired metadata with JSON embeddings)
CREATE TABLE IF NOT EXISTS knowledge_base (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    category_id TEXT,
    dc_title TEXT NOT NULL,
    dc_creator TEXT DEFAULT 'Mfano Bora Africa',
    dc_subject TEXT,                       -- Stored as JSON array (e.g., '["logistics", "awards"]')
    dc_description TEXT,
    dc_source TEXT,
    dc_date DATE DEFAULT CURRENT_DATE,
    dc_format TEXT DEFAULT 'text/plain',
    dc_language TEXT DEFAULT 'en',
    dc_rights TEXT DEFAULT 'Internal use - Mfano Bora Africa',
    content_chunk TEXT NOT NULL,
    target_audience TEXT,
    embedding TEXT,                        -- Stored as JSON array (e.g., '[0.12, -0.45, ...]')
    is_active INTEGER NOT NULL DEFAULT 1,  -- 1 = True, 0 = False
    version INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE SET NULL
);

-- 3. Curated FAQ Pairs (Fast-path bypasses LLM)
CREATE TABLE IF NOT EXISTS faq_entries (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category_id TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE SET NULL
);

-- 4. Admin Users (Role-Based Access Control)
CREATE TABLE IF NOT EXISTS admin_users (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'editor' CHECK (role IN ('super_admin', 'editor', 'viewer')),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME
);

-- 5. Dynamic Chat Logs (For telemetry, audit, and gap analysis)
CREATE TABLE IF NOT EXISTS chat_logs (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    session_id TEXT NOT NULL,
    user_message TEXT NOT NULL,
    matched_kb_ids TEXT,                   -- Stored as JSON array of matched UUID strings
    bot_response TEXT,
    was_fallback INTEGER NOT NULL DEFAULT 0,
    confidence_score REAL,
    response_time_ms INTEGER,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 6. Admin Analytics & Aggregated Metrics
CREATE TABLE IF NOT EXISTS admin_metrics (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    metric_date DATE NOT NULL DEFAULT CURRENT_DATE UNIQUE,
    total_queries INTEGER NOT NULL DEFAULT 0,
    fallback_count INTEGER NOT NULL DEFAULT 0,
    avg_confidence REAL,
    top_gap_topics TEXT,                   -- Stored as JSON array
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 7. Performance & Query Indexes
CREATE INDEX IF NOT EXISTS idx_kb_category ON knowledge_base(category_id);
CREATE INDEX IF NOT EXISTS idx_kb_active ON knowledge_base(is_active);
CREATE INDEX IF NOT EXISTS idx_faq_category ON faq_entries(category_id);
CREATE INDEX IF NOT EXISTS idx_chat_logs_fallback ON chat_logs(was_fallback, created_at);
CREATE INDEX IF NOT EXISTS idx_chat_logs_session ON chat_logs(session_id);

-- 8. Seed Default Taxonomy Categories
INSERT OR IGNORE INTO kb_categories (slug, name, source_type, parent_group, update_frequency) VALUES
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
    ('admin-faq-logs', 'Admin FAQ & Chat Audit Logs', 'dynamic_admin_log', 'System', 'Continuous');

-- 9. Seed Fast-Path Curated FAQ Entries
INSERT INTO faq_entries (question, answer, category_id) VALUES 
(
    'How can I apply for industrial attachment?',
    'Visit our Attachment section to learn about available opportunities, requirements, and the application process, then submit the Attachment Application Form.',
    (SELECT id FROM kb_categories WHERE slug = 'attachment-portal')
), 
(
    'Where can I find current opportunities?',
    'Current attachment, internship, employment, and other opportunities are published through our Careers section.',
    (SELECT id FROM kb_categories WHERE slug = 'careers')
), 
(
    'Can I download Mfano Bora Africa documents?',
    'Yes. Available forms, guides, publications, and other official documents can be downloaded from the Resources Centre.',
    (SELECT id FROM kb_categories WHERE slug = 'forms-templates')
), 
(
    'Where can I find information about the Transport, Logistics and Road Safety Awards?',
    'Visit the Logistics & Transport Awards section for information about the awards programme, participation, nominations, and related resources.',
    (SELECT id FROM kb_categories WHERE slug = 'awards-events')
), 
(
    'How can I access ICT training resources?',
    'Visit the Mfano Africa ICT Hub section for information about technology training, digital skills, and available learning opportunities.',
    (SELECT id FROM kb_categories WHERE slug = 'ict-hub')
), 
(
    'Where is Mfano Bora Africa located and what are your office hours?',
    'Mfano Bora Africa is located at Mfano House, Ole Sein Road, Nairobi, Kenya. Office hours are Monday to Friday, 7:00 AM to 5:00 PM, and Saturday, 8:00 AM to 1:00 PM.',
    (SELECT id FROM kb_categories WHERE slug = 'location-contact')
);