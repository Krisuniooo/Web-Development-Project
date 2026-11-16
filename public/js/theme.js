// The light and dark switch: themeInit.js sets the theme before the page shows, this script changes it on a click
// and remembers the choice.
(() => {
    const button = document.querySelector(".switch-mode");
    if (!button) return;

    const saveTheme = (theme) => {
        try {
            localStorage.setItem("theme", theme);
        } catch {
            // storage can be off, e.g. in a private window; the theme then lasts until the page closes
        }
    };

    // the label says what a click does, so it names the other theme
    const updateLabel = (theme) => {
        button.setAttribute("aria-label", theme === "light" ? button.dataset.labelDark : button.dataset.labelLight);
    };

    button.addEventListener("click", () => {
        const theme = document.documentElement.dataset.theme === "light" ? "dark" : "light";

        document.documentElement.dataset.theme = theme;
        updateLabel(theme);
        saveTheme(theme);
    });

    // the view renders the label for the dark theme, before anyone knows which one themeInit.js chose
    updateLabel(document.documentElement.dataset.theme);
})();
