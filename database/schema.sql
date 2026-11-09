BEGIN;

-- nothing about the people who log in is stored: their platform account lives only in the session
CREATE TYPE server_status AS ENUM ('active', 'removed');
CREATE TYPE channel_status AS ENUM ('active', 'paused');
CREATE TYPE moderation_model AS ENUM ('basal-mini', 'basal-pico');
CREATE TYPE chat_language AS ENUM ('en', 'pl');
CREATE TYPE moderation_category AS ENUM ('racism', 'harassment', 'profanity');
CREATE TYPE moderation_level AS ENUM ('off', 'relaxed', 'balanced', 'strict');
CREATE TYPE moderation_action AS ENUM ('flag', 'delete', 'timeout', 'ban');

CREATE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- a server the bot was invited to, by anyone, through the site or not
CREATE TABLE servers (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    platform TEXT NOT NULL,
    platform_server_id TEXT NOT NULL,
    name TEXT NOT NULL,
    status server_status NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (platform, platform_server_id)
);

-- a channel the bot moderates, with its own model, language and rules; a server has at most two, one per slot
CREATE TABLE channels (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    server_id BIGINT NOT NULL REFERENCES servers (id) ON DELETE CASCADE,
    slot SMALLINT NOT NULL CHECK (slot IN (1, 2)),
    platform_channel_id TEXT NOT NULL,
    name TEXT NOT NULL,
    model moderation_model NOT NULL,
    language chat_language NOT NULL,
    status channel_status NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (server_id, slot),
    UNIQUE (server_id, platform_channel_id),
    -- pico misses too much in Polish
    CHECK (model <> 'basal-pico' OR language = 'en')
);

CREATE TABLE channel_rules (
    channel_id BIGINT NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    category moderation_category NOT NULL,
    level moderation_level NOT NULL,
    action moderation_action NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (channel_id, category)
);

CREATE TABLE moderation_events (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    channel_id BIGINT NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    -- the channel as it was then, since its slot can later point at another channel
    platform_channel_id TEXT NOT NULL,
    channel_name TEXT NOT NULL,
    platform_message_id TEXT NOT NULL,
    -- the author and the content are set to NULL by bin/cleanup.php once the event is 30 days old;
    -- the rest of the event stays for statistics
    author_platform_id TEXT,
    author_name TEXT,
    category moderation_category NOT NULL,
    level moderation_level NOT NULL,
    -- text, so an event keeps the name of a model that is later removed
    model TEXT NOT NULL,
    action moderation_action NOT NULL,
    content TEXT,
    score DOUBLE PRECISION,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX moderation_events_channel_id_created_at_idx ON moderation_events (channel_id, created_at DESC);

CREATE TRIGGER servers_set_updated_at BEFORE UPDATE ON servers
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER channels_set_updated_at BEFORE UPDATE ON channels
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER channel_rules_set_updated_at BEFORE UPDATE ON channel_rules
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMIT;
