<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Channel;
use App\Model\ChannelRule;
use App\Discord\DiscordClient;
use App\Repository\ChannelRepository;
use App\Repository\ServerRepository;
use RuntimeException;

/**
 * The forms of the panel that change the channels of the chosen server, and whom the bot leaves alone on them.
 * Each one checks again that the form came from this site, that the user may manage the server and that the channel
 * it names belongs to it, then goes back to its section.
 */
final class ChannelController extends AbstractPanelController
{
    // back to the card of the exemptions, which is below the channels, so the page does not jump to its top
    private const EXEMPTIONS_URL = '/app/limits#exemptions';

    public function setLanguage(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $language = $_POST['language'] ?? null;
        $savedChannelId = null;

        try {
            $repository = new ChannelRepository($this->database());
            $channel = $this->postedChannel($repository, $user['platform'], $managedServer['id']);

            // only a language the channel's model has questions in
            if ($channel !== null && in_array($language, $this->modelLanguages($channel->getModel()), true)) {
                $repository->setLanguage($channel->getId(), $language);
                $savedChannelId = $channel->getId();
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel language: ' . $exception->getMessage());
        }

        if ($savedChannelId !== null) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $savedChannelId;
        } else {
            $_SESSION['notice'] = ['text' => 'channels.save_error', 'isError' => true];
        }

        $this->redirect('/app/channels');
    }

    /** Pauses a channel of the chosen server, or lets the bot moderate it again; its settings stay. */
    public function setStatus(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $status = $_POST['status'] ?? null;
        $isSaved = false;

        try {
            $repository = new ChannelRepository($this->database());
            $channel = $this->postedChannel($repository, $user['platform'], $managedServer['id']);

            // only a status the database knows
            if ($channel !== null && in_array($status, Channel::STATUSES, true)) {
                $repository->setStatus($channel->getId(), $status);
                $isSaved = true;
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel status: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isSaved
            ? ['text' => $status === Channel::STATUS_PAUSED ? 'channels.paused' : 'channels.resumed', 'isError' => false]
            : ['text' => 'channels.status_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Saves the model that checks one channel of the chosen server; its language and rules stay. */
    public function setModel(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $model = $_POST['model'] ?? null;
        $savedChannelId = null;

        try {
            $repository = new ChannelRepository($this->database());
            $channel = $this->postedChannel($repository, $user['platform'], $managedServer['id']);

            // only a model the bot has, and one with questions in the channel's language
            if (
                $channel !== null
                && in_array($model, $this->availableModels(), true)
                && in_array($channel->getLanguage(), $this->modelLanguages($model), true)
            ) {
                $repository->setModel($channel->getId(), $model);
                $savedChannelId = $channel->getId();
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel model: ' . $exception->getMessage());
        }

        if ($savedChannelId !== null) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $savedChannelId;
        } else {
            $_SESSION['notice'] = ['text' => 'models.save_error', 'isError' => true];
        }

        $this->redirect('/app/models');
    }

    /** Connects a channel the bot can see to a free slot of the chosen server, with the default rules. */
    public function connect(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $slot = filter_var($_POST['slot'] ?? null, FILTER_VALIDATE_INT);
        $platformChannelId = $_POST['channel'] ?? null;
        $language = $_POST['language'] ?? null;
        $defaults = $this->channelDefaults();
        $isConnected = false;

        try {
            $repository = new ChannelRepository($this->database());
            $connected = $repository->findForServer($user['platform'], $managedServer['id']);
            $takenSlots = array_map(fn(Channel $channel): int => $channel->getSlot(), $connected);

            // a free slot of those the platform has, and a language the model of a new channel has questions in
            if (
                in_array($slot, $this->channelSlotsOf($user['platform']), true)
                && !in_array($slot, $takenSlots, true)
                && in_array($language, $this->modelLanguages($defaults['model']), true)
            ) {
                // only a channel the bot can see and that is not connected yet; its name comes from Discord, not from the form
                foreach ($this->access()->availableChannels($managedServer['id'], $connected) as $channel) {
                    if ($channel['id'] === $platformChannelId) {
                        $isConnected = $repository->add(
                            $user['platform'],
                            $managedServer['id'],
                            $slot,
                            $channel['id'],
                            $channel['name'],
                            $defaults['model'],
                            $language,
                            $defaults['rules'],
                        );
                    }
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not connect a channel: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isConnected
            ? ['text' => 'channels.connected', 'isError' => false]
            : ['text' => 'channels.connect_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Moves a slot of the chosen server to another channel the bot can see; the language, model, rules and status stay. */
    public function change(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $slot = filter_var($_POST['slot'] ?? null, FILTER_VALIDATE_INT);
        $platformChannelId = $_POST['channel'] ?? null;
        $isChanged = false;

        try {
            $repository = new ChannelRepository($this->database());
            // a server that is its own only chat has no other channel to move to
            $connected = count($this->channelSlotsOf($user['platform'])) > 1
                ? $repository->findForServer($user['platform'], $managedServer['id'])
                : [];

            foreach ($connected as $current) {
                if ($current->getSlot() !== $slot) {
                    continue;
                }

                // only a channel the bot can see and that is not connected yet; its name comes from Discord, not from the form
                foreach ($this->access()->availableChannels($managedServer['id'], $connected) as $channel) {
                    if ($channel['id'] === $platformChannelId) {
                        $repository->setPlatformChannel($current->getId(), $channel['id'], $channel['name']);
                        $isChanged = true;
                    }
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not change a channel: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isChanged
            ? ['text' => 'channels.changed', 'isError' => false]
            : ['text' => 'channels.change_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    public function saveRules(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $posted = is_array($_POST['rules'] ?? null) ? $_POST['rules'] : [];
        $savedChannelId = null;

        try {
            $repository = new ChannelRepository($this->database());
            $channel = $this->postedChannel($repository, $user['platform'], $managedServer['id']);
            $rules = $channel === null ? null : $this->postedRules($channel->getId(), $posted);

            if ($rules !== null) {
                $repository->saveRules($rules);
                $savedChannelId = $channel->getId();
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the rules: ' . $exception->getMessage());
        }

        if ($savedChannelId !== null) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $savedChannelId;
        } else {
            $_SESSION['notice'] = ['text' => 'limits.save_error', 'isError' => true];
        }

        $this->redirect('/app/limits');
    }

    /** Turns on or off that the bot leaves the owner and administrators of the chosen server alone. */
    public function setAdminsExempt(): void
    {
        [$user, $managedServer] = $this->requireForm();

        // a checkbox, so it is sent only while ticked
        $isExempt = isset($_POST['exempt']);
        $isSaved = false;

        try {
            // only a platform whose servers have administrators to exempt
            if ($this->platform($user['platform'])['exemptions']) {
                (new ServerRepository($this->database()))->setAdminsExempt($user['platform'], $managedServer['id'], $isExempt);
                $isSaved = true;
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the exemption of administrators: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isSaved
            ? ['text' => $isExempt ? 'exemptions.admins_on' : 'exemptions.admins_off', 'isError' => false]
            : ['text' => 'exemptions.save_error', 'isError' => true];

        $this->redirect(self::EXEMPTIONS_URL);
    }

    /** Adds a role of the chosen server to those the bot leaves alone, or takes one off the list. */
    public function setRoleExempt(): void
    {
        [$user, $managedServer] = $this->requireForm();

        $roleId = $_POST['role'] ?? null;
        $isExempt = ($_POST['exempt'] ?? null) === '1';
        // only a platform whose servers have roles to exempt
        $hasRoles = $this->platform($user['platform'])['exemptions'];
        $isSaved = false;

        try {
            $repository = new ServerRepository($this->database());

            if (!$hasRoles) {
                // nothing is saved
            } elseif (!$isExempt && DiscordClient::isId($roleId)) {
                // Discord is not asked, so a role deleted there can still be taken off
                $repository->removeExemptRole($user['platform'], $managedServer['id'], $roleId);
                $isSaved = true;
            } elseif ($isExempt && in_array($roleId, array_column($this->access()->roles($managedServer['id']), 'id'), true)) {
                // only a role the server has now
                $repository->addExemptRole($user['platform'], $managedServer['id'], $roleId);
                $isSaved = true;
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save an exempt role: ' . $exception->getMessage());
        }

        // the list shows the change itself, so only a failure gets a notice
        if (!$isSaved) {
            $_SESSION['notice'] = ['text' => 'exemptions.save_error', 'isError' => true];
        }

        $this->redirect(self::EXEMPTIONS_URL);
    }

    /**
     * For every form here: refuses one from another site, and sends away a visitor who is not logged in
     * or has no server chosen.
     *
     * @return array{array{platform: string, id: string, name: string, avatarUrl: ?string}, array{id: string, name: string}}
     *     the user and the chosen server
     */
    private function requireForm(): array
    {
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            exit;
        }

        return $this->requireManagedServer();
    }

    /** The channel a form names in "channel", or null unless it is a channel of the chosen server: a form can change no other. */
    private function postedChannel(ChannelRepository $repository, string $platform, string $serverId): ?Channel
    {
        $id = filter_var($_POST['channel'] ?? null, FILTER_VALIDATE_INT);

        foreach ($repository->findForServer($platform, $serverId) as $channel) {
            if ($channel->getId() === $id) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * The rules a form sent for a channel: every category with a known level and an action the bot performs.
     * Null when any of them is missing or unknown, so nothing is saved then.
     *
     * @return list<ChannelRule>|null
     */
    private function postedRules(int $channelId, array $posted): ?array
    {
        $rules = [];

        foreach ($this->ruleCategories() as $category) {
            $level = $posted[$category]['level'] ?? null;
            $action = $posted[$category]['action'] ?? null;

            if (!in_array($level, $this->ruleLevels(), true) || !in_array($action, self::RULE_ACTIONS, true)) {
                return null;
            }

            $rules[] = new ChannelRule($channelId, $category, $level, $action);
        }

        return $rules;
    }
}
