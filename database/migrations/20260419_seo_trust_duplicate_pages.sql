ALTER TABLE questions
    ADD COLUMN duplicate_of_id BIGINT UNSIGNED NULL AFTER accepted_answer_id,
    ADD KEY idx_questions_duplicate_of (duplicate_of_id),
    ADD CONSTRAINT fk_questions_duplicate_of FOREIGN KEY (duplicate_of_id) REFERENCES questions(id) ON DELETE SET NULL;

INSERT INTO settings (`key`, value, updated_at) VALUES
('about_description', 'Learn about the community, moderation standards, and public question-and-answer content.', NOW()),
('about_body', 'This community publishes public questions, answers, clarifications, profiles, categories, and tags so people can find helpful discussions and cite canonical question pages.', NOW()),
('contact_description', 'Contact the site team about account, moderation, privacy, or community questions.', NOW()),
('contact_body', 'Use the contact information configured by the site owner for account, moderation, privacy, and community requests.', NOW()),
('privacy_description', 'Privacy information for community accounts, public posts, media, and moderation records.', NOW()),
('privacy_body', 'Public posts, answers, clarifications, profile details, uploaded media, votes, reactions, reports, and moderation records may be processed to operate the community and protect the site.', NOW()),
('terms_description', 'Terms for using the community question-and-answer platform.', NOW()),
('terms_body', 'By using the community, members agree to post lawful, respectful, original content and understand that public contributions may be indexed by search engines and AI retrieval systems.', NOW()),
('guidelines_description', 'Community guidelines for useful questions, answers, clarifications, citations, and moderation.', NOW()),
('guidelines_body', 'Ask clear questions, share complete answers, disclose uncertainty, avoid spam, respect other members, and report content that needs moderator attention.', NOW())
ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();
