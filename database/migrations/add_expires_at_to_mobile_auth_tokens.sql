-- Bounds how long a mobile bearer token stays valid if a device is lost/
-- stolen and never explicitly logged out. Sliding expiry: refreshed forward
-- on every authenticated request (see includes/mobile_auth.php), so an
-- actively-used device never gets logged out, but an abandoned one expires.
ALTER TABLE mobile_auth_tokens
    ADD COLUMN expires_at TIMESTAMP NULL AFTER token_hash;
