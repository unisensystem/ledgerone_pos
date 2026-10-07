-- Short-lived, single-use nonces for the mobile print bridge, so the
-- long-lived mobile bearer token never has to travel inside a URL (server
-- logs, browser history, Referer headers to subresources). The app
-- exchanges its token for one of these via an authenticated POST (token in
-- the Authorization header, not the URL); only the nonce goes in the
-- browser-navigation URL, and it is consumed on first use.
CREATE TABLE mobile_print_nonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nonce_hash CHAR(64) NOT NULL UNIQUE,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
