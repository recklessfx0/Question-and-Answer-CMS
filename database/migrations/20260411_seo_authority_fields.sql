ALTER TABLE categories
    ADD COLUMN seo_title VARCHAR(120) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN seo_description VARCHAR(180) NOT NULL DEFAULT '' AFTER seo_title,
    ADD COLUMN intro TEXT NULL AFTER seo_description;

ALTER TABLE tags
    ADD COLUMN description VARCHAR(240) NOT NULL DEFAULT '' AFTER slug,
    ADD COLUMN seo_title VARCHAR(120) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN seo_description VARCHAR(180) NOT NULL DEFAULT '' AFTER seo_title,
    ADD COLUMN intro TEXT NULL AFTER seo_description;

ALTER TABLE users
    ADD COLUMN website_url VARCHAR(255) NOT NULL DEFAULT '' AFTER bio,
    ADD COLUMN expertise VARCHAR(240) NOT NULL DEFAULT '' AFTER website_url,
    ADD COLUMN credentials VARCHAR(240) NOT NULL DEFAULT '' AFTER expertise;
