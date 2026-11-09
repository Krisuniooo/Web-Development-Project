<?php
$stylesheets[] = '/css/panel/message-check.css';
$scripts[] = '/js/panel/message-check.js';
?>

<?php // closed at the start; the script asks about the model only once it is opened. The addresses and the texts it shows on its own come from here ?>
<details
    class="message-check"
    data-status-url="/app/model-status"
    data-check-url="/app/check-message"
    data-text-model-ready="<?= $t('check.model.ready') ?>"
    data-text-model-down="<?= $t('check.model.down') ?>"
    data-text-checking="<?= $t('check.checking') ?>"
    data-text-allowed="<?= $t('check.allowed') ?>"
    data-text-breaks="<?= $t('check.breaks') ?>"
    data-text-network="<?= $t('check.error.network') ?>"
    data-text-unexpected="<?= $t('check.error.unexpected') ?>"
>
    <summary class="message-check--summary">
        <span class="message-check--icon" aria-hidden="true"><i class="fa-solid fa-vial"></i></span>
        <span class="message-check--heading">
            <span class="message-check--title"><?= $t('check.title') ?></span>
            <span class="message-check--hint"><?= $t('check.hint') ?></span>
        </span>
        <span class="message-check--chevron" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
    </summary>

    <?php // on a wide box the message is on the left and the result on the right, on a narrow one under it ?>
    <div class="message-check--body">
        <div class="message-check--input">
            <form class="message-check--form">
                <label for="message-check-text" class="message-check--label"><?= $t('check.text') ?></label>
                <textarea
                    id="message-check-text"
                    name="text"
                    class="message-check--text"
                    rows="3"
                    maxlength="<?= (int) $messageCheck['maxLength'] ?>"
                    placeholder="<?= $t('check.placeholder') ?>"
                    required
                ></textarea>

                <div class="message-check--actions">
                    <label class="message-check--field">
                        <span class="message-check--label"><?= $t('check.language') ?></span>
                        <select name="language" class="message-check--language">
                            <?php foreach ($messageCheck['languages'] as $code): ?>
                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $messageCheck['language'] ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <span class="message-check--status" role="status" data-model-status><?= $t('check.model.checking') ?></span>
                    <?php // the script turns it on once the model is ready ?>
                    <button type="submit" class="message-check--submit" disabled><?= $t('check.submit') ?></button>
                </div>
            </form>

            <p class="message-check--error" role="alert" data-error hidden></p>
        </div>

        <div class="message-check--output">
            <p class="message-check--empty" data-empty><?= $t('check.empty') ?></p>

            <div class="message-check--result" data-result hidden>
                <table class="message-check--table">
                    <thead>
                        <tr>
                            <th scope="col"><?= $t('check.category') ?></th>
                            <th scope="col"><?= $t('check.score') ?></th>
                            <?php foreach ($messageCheck['levels'] as $level): ?>
                                <th scope="col" class="message-check--level"><?= $t('level.' . $level) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody data-rows></tbody>
                </table>
                <p class="message-check--legend">
                    <span><i class="fa-solid fa-check message-check--allowed" aria-hidden="true"></i> <?= $t('check.allowed') ?></span>
                    <span><i class="fa-solid fa-xmark message-check--breaks" aria-hidden="true"></i> <?= $t('check.breaks') ?></span>
                    <span><?= $t('check.levels_hint') ?></span>
                </p>
            </div>
        </div>
    </div>

    <?php // one row of the table, filled in by the script for every category ?>
    <template data-row-template>
        <tr>
            <th scope="row" data-name></th>
            <td class="message-check--score-cell">
                <span class="message-check--score">
                    <span class="message-check--bar" aria-hidden="true"><span class="message-check--bar-fill" data-bar></span></span>
                    <span class="message-check--percent" data-score></span>
                </span>
            </td>
            <?php foreach ($messageCheck['levels'] as $level): ?>
                <?php // the label is shown next to the mark on a narrow box, where the table has no header row ?>
                <td class="message-check--level" data-level="<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>" data-label="<?= $t('level.' . $level) ?>"></td>
            <?php endforeach; ?>
        </tr>
    </template>
</details>
