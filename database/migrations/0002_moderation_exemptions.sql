-- whom the bot leaves alone on a server: their messages are not checked on any of its channels

-- the owner and members with the Administrator permission; on from the start, since they set the rules themselves
ALTER TABLE servers ADD COLUMN admins_exempt BOOLEAN NOT NULL DEFAULT true;

-- the roles chosen in the panel; only the id, the name is asked from the platform when the panel shows it
CREATE TABLE server_exempt_roles (
    server_id BIGINT NOT NULL REFERENCES servers (id) ON DELETE CASCADE,
    platform_role_id TEXT NOT NULL,
    PRIMARY KEY (server_id, platform_role_id)
);
