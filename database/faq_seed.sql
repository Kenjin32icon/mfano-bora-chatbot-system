-- ============================================================================
-- Mfano Bora Africa Chatbot - Database Seed Data
-- Target Engine: SQLite 3
-- File Location: database/seed.sql
-- Dependencies: Run schema.sql prior to running this script
-- ============================================================================

PRAGMA foreign_keys = ON;

-- 1. Seed System Taxonomy Categories
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

-- 2. Seed Fast-Path Curated FAQ Entries using Dynamic Subqueries
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