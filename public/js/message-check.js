(() => {
    const box = document.querySelector(".message-check");
    if (!box) return;

    // how often to ask again while the model is not ready
    const STATUS_RETRY_MS = 5000;

    const texts = box.dataset;
    const status = box.querySelector("[data-model-status]");
    const form = box.querySelector(".message-check--form");
    const button = form.querySelector(".message-check--submit");
    const error = box.querySelector("[data-error]");
    const empty = box.querySelector("[data-empty]");
    const result = box.querySelector("[data-result]");
    const rows = result.querySelector("[data-rows]");
    const rowTemplate = box.querySelector("[data-row-template]");

    let isReady = false;
    let isChecking = false;
    let hasAskedStatus = false;
    let statusTimer = null;

    // the answer as JSON, or null when the server sent something else, such as an error page
    const readJson = async (response) => {
        try {
            return await response.json();
        } catch {
            return null;
        }
    };

    const updateButton = () => {
        button.disabled = !isReady || isChecking;
    };

    const setModelStatus = (ready) => {
        isReady = ready;
        status.textContent = ready ? texts.textModelReady : texts.textModelDown;
        status.classList.toggle("is-ready", ready);
        status.classList.toggle("is-down", !ready);
        updateButton();
    };

    const showError = (message) => {
        error.textContent = message;
        error.hidden = false;
        // the last result was for another message
        result.hidden = true;
        empty.hidden = false;
    };

    const askStatusLater = () => {
        clearTimeout(statusTimer);
        statusTimer = setTimeout(askStatus, STATUS_RETRY_MS);
    };

    // GET: whether the model can check a message now; asked again until it can
    const askStatus = async () => {
        try {
            const response = await fetch(texts.statusUrl, { headers: { Accept: "application/json" } });
            const data = await readJson(response);

            if (response.ok && data?.ready === true) {
                setModelStatus(true);
                // an error from the time the model was down no longer holds
                error.hidden = true;
                return;
            }

            setModelStatus(false);

            // an ended session does not come back by waiting, so it is said and not asked again;
            // the model's state is not known then, so the status is left empty
            if (response.status === 401) {
                status.textContent = "";
                showError(data?.error ?? texts.textUnexpected);
                return;
            }
        } catch {
            setModelStatus(false);
        }

        askStatusLater();
    };

    // a mark in a level's column: whether the message breaks the rule at that level
    const verdict = (breaksRule) => {
        const mark = document.createElement("span");
        const icon = document.createElement("i");
        mark.className = breaksRule ? "message-check--breaks" : "message-check--allowed";
        mark.setAttribute("role", "img");
        mark.setAttribute("aria-label", breaksRule ? texts.textBreaks : texts.textAllowed);
        mark.title = mark.getAttribute("aria-label");
        icon.className = breaksRule ? "fa-solid fa-xmark" : "fa-solid fa-check";
        icon.setAttribute("aria-hidden", "true");
        mark.append(icon);
        return mark;
    };

    const showResult = (categories) => {
        rows.replaceChildren(...categories.map((category) => {
            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            const percent = Math.round(category.score * 100);
            row.querySelector("[data-name]").textContent = category.name;
            row.querySelector("[data-bar]").style.width = `${percent}%`;
            row.querySelector("[data-score]").textContent = `${percent}%`;
            row.querySelectorAll("[data-level]").forEach((cell) => {
                cell.replaceChildren(verdict(category.breaks?.[cell.dataset.level] === true));
            });
            return row;
        }));

        error.hidden = true;
        empty.hidden = true;
        result.hidden = false;
    };

    // POST: the message goes to the model, and its answer replaces the last result
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!isReady || isChecking) return;

        const label = button.textContent;
        isChecking = true;
        updateButton();
        button.textContent = texts.textChecking;
        button.classList.add("is-loading");
        box.setAttribute("aria-busy", "true");

        try {
            const response = await fetch(texts.checkUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify({ text: form.elements.text.value, language: form.elements.language.value }),
            });
            const data = await readJson(response);

            if (response.ok && Array.isArray(data?.categories)) {
                showResult(data.categories);
                return;
            }

            showError(data?.error ?? texts.textUnexpected);

            // the model stopped answering, so the button waits until it is back
            if (response.status === 503) {
                setModelStatus(false);
                askStatusLater();
            }
        } catch {
            showError(texts.textNetwork);
        } finally {
            isChecking = false;
            button.textContent = label;
            button.classList.remove("is-loading");
            box.removeAttribute("aria-busy");
            updateButton();
        }
    });

    // the model is asked about only when someone opens the box, and only the first time
    box.addEventListener("toggle", () => {
        if (box.open && !hasAskedStatus) {
            hasAskedStatus = true;
            askStatus();
        }
    });
})();
