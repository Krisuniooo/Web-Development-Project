// Runs in <head>, before the page shows, so it never flashes in the wrong theme:
// the theme chosen before, or else the one the system prefers.
(() => {
    let theme = null;

    try {
        theme = localStorage.getItem("theme");
    } catch {
        // storage can be off, e.g. in a private window; the system's preference is used then
    }

    if (theme !== "light" && theme !== "dark") {
        theme = window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
    }

    document.documentElement.dataset.theme = theme;
})();
