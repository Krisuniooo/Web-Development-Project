(() => {
    let theme = null;

    try {
        theme = localStorage.getItem('theme');
    } catch (e) {
        // nothing ever happens
    }

    if (theme !== 'light' && theme !== 'dark') {
        if (window.matchMedia('(prefers-color-scheme: light)').matches) {
            theme = 'light';
        } else {
            theme = 'dark';
        }
    }

    document.documentElement.dataset.theme = theme;
})();