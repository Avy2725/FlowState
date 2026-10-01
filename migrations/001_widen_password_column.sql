-- Bcrypt and other password_hash() output needs up to 255 characters.
-- Run this once. After it, new signups use password_hash() and existing md5
-- passwords are upgraded the next time each user logs in.
ALTER TABLE ca_users MODIFY ca_userPass VARCHAR(255) NOT NULL;
