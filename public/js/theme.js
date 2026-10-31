const switchMode = document.querySelector(".switch-mode");

const saveTheme = (mode) => {
    try {
        localStorage.setItem("theme", mode);
    } catch (e) {
        // nothing ever happens
    }
}

const updateLabel = (mode) => {
    const newLabel = mode === "light" ? "Switch to dark mode" : "Switch to light mode";
    switchMode?.setAttribute("aria-label", newLabel);
}

switchMode?.addEventListener("click", () => {
    const newTheme = document.documentElement.dataset.theme === "light" ? "dark" : "light";

    document.documentElement.dataset.theme = newTheme;
    updateLabel(newTheme);
    saveTheme(newTheme);
});

// Since themeInit.js runs before button is created,
// this function call is needed to sync the label
updateLabel(document.documentElement.dataset.theme);