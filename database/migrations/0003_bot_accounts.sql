-- the account a platform's bot acts as when the platform has no token for a bot, such as the Twitch account that
-- moderates the chats; Discord's bot logs in with the token in .env, so it has no row here
CREATE TABLE bot_accounts (
    platform TEXT PRIMARY KEY,
    platform_user_id TEXT NOT NULL,
    name TEXT NOT NULL,
    -- both sealed with a key made from APP_KEY, see src/Secrets.php
    refresh_token TEXT NOT NULL,
    access_token TEXT NOT NULL,
    access_expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER bot_accounts_set_updated_at BEFORE UPDATE ON bot_accounts
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
