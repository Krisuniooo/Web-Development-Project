BEGIN;

CREATE TYPE channel_status AS ENUM ('active', 'paused', 'disconnected');
CREATE TYPE moderation_category AS ENUM ('racism', 'harassment', 'profanity');
CREATE TYPE moderation_action AS ENUM ('flag', 'delete', 'timeout', 'ban');

CREATE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE accounts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    platform TEXT NOT NULL,
    platform_user_id TEXT NOT NULL,
    display_name TEXT NOT NULL,
    access_token_enc TEXT,
    refresh_token_enc TEXT,
    token_expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (platform, platform_user_id)
);

CREATE TABLE channels (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    platform TEXT NOT NULL,
    platform_channel_id TEXT NOT NULL,
    name TEXT NOT NULL,
    model_slug TEXT NOT NULL,
    status channel_status NOT NULL DEFAULT 'active',
    connected_by_account_id BIGINT REFERENCES accounts (id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (platform, platform_channel_id)
);

CREATE INDEX channels_connected_by_account_id_idx ON channels (connected_by_account_id);

CREATE TABLE channel_rules (
    channel_id BIGINT NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    category moderation_category NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    action moderation_action NOT NULL,
    threshold DOUBLE PRECISION,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (channel_id, category)
);

CREATE TABLE moderation_events (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    channel_id BIGINT NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    platform_message_id TEXT NOT NULL,
    author_platform_id TEXT NOT NULL,
    author_name TEXT NOT NULL,
    category moderation_category NOT NULL,
    model_slug TEXT NOT NULL,
    action moderation_action NOT NULL,
    content TEXT,
    score DOUBLE PRECISION,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX moderation_events_channel_id_created_at_idx ON moderation_events (channel_id, created_at DESC);

CREATE TRIGGER accounts_set_updated_at BEFORE UPDATE ON accounts
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER channels_set_updated_at BEFORE UPDATE ON channels
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER channel_rules_set_updated_at BEFORE UPDATE ON channel_rules
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMIT;
