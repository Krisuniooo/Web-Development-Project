<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Database;
use App\Model\ChannelRule;
use App\Moderation\MessageClassifier;
use App\Repository\ChannelRepository;
use App\Service\ServerAccess;
use PDO;
use RuntimeException;

/**
 * What the controllers of the panel share: the request's database connection, the check of who may manage
 * which server, and the choices the settings offer.
 */
abstract class AbstractPanelController extends AppController
{
    // the actions the bot performs now; timeout and ban come later
    protected const RULE_ACTIONS = [ChannelRule::ACTION_FLAG, ChannelRule::ACTION_DELETE];
    // the longest message Discord allows without Nitro
    protected const TEST_MAX_LENGTH = 2000;

    private ?PDO $pdo = null;
    private ?ServerAccess $access = null;

    /**
     * The logged-in user and the server they chose. A visitor who is not logged in goes to the login page,
     * and one without a server they may still manage to the list of servers.
     *
     * @return array{array{platform: string, id: string, name: string, avatarUrl: ?string}, array{id: string, name: string}}
     *     the user and the chosen server
     */
    protected function requireManagedServer(): array
    {
        $user = $this->requireLogin();
        $managedServer = $this->access()->managedServer($user);

        if ($managedServer === null) {
            $this->redirect('/app/servers');
        }

        return [$user, $managedServer];
    }

    /** One database connection for the whole request, opened when first needed. */
    protected function database(): PDO
    {
        return $this->pdo ??= (new Database())->connect();
    }

    /** For a request that goes on to wait for something else, such as the model, and needs the database no more. */
    protected function closeDatabase(): void
    {
        $this->pdo = null;
    }

    /** Which servers the user may manage, and what the bot sees on them; shares the request's connection. */
    protected function access(): ServerAccess
    {
        return $this->access ??= new ServerAccess($this->database(...));
    }

    /**
     * How the panel differs for a platform someone can log in with, from config/platforms.php.
     *
     * @return array{name: string, slug: string, authPath: string, connect: string, channelSlots: int, exemptions: bool, botIsAccount: bool}
     */
    protected function platform(string $slug): array
    {
        return array_column(Config::load('platforms'), null, 'slug')[$slug];
    }

    /** @return list<int> the places for channels a server of the platform has: both of them, or only the first */
    protected function channelSlotsOf(string $platform): array
    {
        return array_slice(ChannelRepository::SLOTS, 0, $this->platform($platform)['channelSlots']);
    }

    /** @return array{model: string, rules: array<string, array{level: string, action: string}>} the settings of a new channel */
    protected function channelDefaults(): array
    {
        return Config::load('channel-defaults');
    }

    /** @return list<string> the models the bot can ask: those with settings in config/moderation.php */
    protected function availableModels(): array
    {
        return array_keys(Config::load('moderation'));
    }

    /** @return list<string> the languages a model has questions in; none for a model without settings in config/moderation.php */
    protected function modelLanguages(string $model): array
    {
        try {
            return (new MessageClassifier($model))->languages();
        } catch (RuntimeException) {
            return [];
        }
    }

    /** @return list<string> the categories with a rule: those every new channel gets */
    protected function ruleCategories(): array
    {
        return array_keys($this->channelDefaults()['rules']);
    }

    /** @return list<string> off, and then from the fewest blocked messages to the most */
    protected function ruleLevels(): array
    {
        return [MessageClassifier::LEVEL_OFF, ...MessageClassifier::LEVELS];
    }

    /** The model of the test box: the one new channels get, as bin/check-message.php uses too. */
    protected function testClassifier(): MessageClassifier
    {
        return new MessageClassifier($this->channelDefaults()['model']);
    }
}
