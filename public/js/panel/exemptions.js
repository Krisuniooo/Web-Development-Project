// The card of the people the bot leaves alone. The forms work without this script too:
// the checkbox then has a button of its own, and the list of roles opens and adds, only without the search.
(() => {
    const card = document.querySelector(".exemptions");
    if (!card) return;

    // the checkbox saves itself, as a switch does
    const admins = card.querySelector(".exemptions-admins");
    admins?.addEventListener("change", () => admins.requestSubmit());

    const picker = card.querySelector(".exemptions-picker");
    const search = picker?.querySelector(".exemptions-picker--search");
    // no search: the roles did not load, or there is none left to add
    if (!search) return;

    const opener = picker.querySelector("summary");
    const choices = [...picker.querySelectorAll(".exemptions-picker--choice")];
    const noMatch = picker.querySelector("[data-no-match]");

    const listed = () => choices.filter((choice) => !choice.parentElement.hidden);

    const narrow = () => {
        const wanted = search.value.trim().toLowerCase();

        for (const choice of choices) {
            choice.parentElement.hidden = !choice.textContent.toLowerCase().includes(wanted);
        }

        noMatch.hidden = listed().length > 0;
    };

    search.hidden = false;
    search.addEventListener("input", narrow);

    // Enter adds the first role still listed; left to the browser it would add the first of all, even a hidden one
    search.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;

        event.preventDefault();
        listed()[0]?.click();
    });

    // opened: the whole list again, and typing starts right away
    picker.addEventListener("toggle", () => {
        if (!picker.open) return;

        search.value = "";
        narrow();
        search.focus();
    });

    // Escape and a click anywhere else close the list, as they close a menu
    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape" || !picker.open) return;

        picker.open = false;
        opener.focus();
    });
    document.addEventListener("click", (event) => {
        if (picker.open && !picker.contains(event.target)) {
            picker.open = false;
        }
    });
})();
