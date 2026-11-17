-- whether the bot is a moderator of the server, on a platform where the bot is an account that must be made one, such as
-- a Twitch channel: the bot checks it every minute, listens only where it is one, and the panel warns where it is not.
-- Null while it is not known: before the bot's first check, and on platforms whose bot is invited, such as Discord
ALTER TABLE servers ADD COLUMN bot_is_moderator BOOLEAN;
