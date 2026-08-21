-- Run AFTER schema.sql. Populates the fast-path FAQ table so the most common
-- questions never need embeddings or an LLM call at all.

insert into faq_entries (question, answer, category_id) values
('How can I apply for industrial attachment?',
 'Visit our Attachment section to learn about available opportunities, requirements, and the application process, then submit the Attachment Application Form.',
 (select id from kb_categories where slug = 'attachment-portal')),

('Where can I find current opportunities?',
 'Current attachment, internship, employment, and other opportunities are published through our Careers section.',
 (select id from kb_categories where slug = 'careers')),

('Can I download Mfano Bora Africa documents?',
 'Yes. Available forms, guides, publications, and other official documents can be downloaded from the Resources Centre.',
 (select id from kb_categories where slug = 'forms-templates')),

('Where can I find information about the Transport, Logistics and Road Safety Awards?',
 'Visit the Logistics & Transport Awards section for information about the awards programme, participation, nominations, and related resources.',
 (select id from kb_categories where slug = 'awards-events')),

('How can I access ICT training resources?',
 'Visit the Mfano Africa ICT Hub section for information about technology training, digital skills, and available learning opportunities.',
 (select id from kb_categories where slug = 'ict-hub')),

('Where is Mfano Bora Africa located and what are your office hours?',
 'Mfano Bora Africa is located at Mfano House, Ole Sein Road, Nairobi, Kenya. Office hours are Monday to Friday, 7:00 AM to 5:00 PM, and Saturday, 8:00 AM to 1:00 PM.',
 (select id from kb_categories where slug = 'location-contact'));
