(() => {
    const composer = document.querySelector(".CTA-composer");
    if (!composer) return;

    const TYPE_MS = 50;
    const DELETE_MS = 25;
    const HOLD_MS = 2400;
    const RESUME_MS = 400;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // without animation the first platform rendered by the view stays on screen
    if (reducedMotion) return;

    const icons = composer.querySelectorAll(".CTA-composer--icon");
    const typed = composer.querySelector(".CTA-composer--typed");
    const messages = [...composer.querySelectorAll(".CTA-composer--message")].map((message) => message.textContent);

    let current = 0;
    let length = 0;
    let isDeleting = false;
    let isVisible = false;
    let timer = null;

    const showNextPlatform = () => {
        icons[current].classList.remove("is-active");
        current = (current + 1) % messages.length;
        icons[current].classList.add("is-active");
    };

    const step = () => {
        length += isDeleting ? -1 : 1;
        typed.textContent = messages[current].slice(0, length);

        const isTyped = length === messages[current].length;
        const isErased = length === 0;
        typed.classList.toggle("is-typing", !isTyped && !isErased);

        if (isTyped) {
            isDeleting = true;
            timer = setTimeout(step, HOLD_MS);
        } else if (isErased) {
            isDeleting = false;
            showNextPlatform();
            timer = setTimeout(step, RESUME_MS);
        } else {
            timer = setTimeout(step, isDeleting ? DELETE_MS : TYPE_MS);
        }
    };

    const update = () => {
        clearTimeout(timer);
        if (isVisible && !document.hidden) {
            timer = setTimeout(step, RESUME_MS);
        }
    };

    typed.textContent = "";

    new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        update();
    }).observe(composer);

    document.addEventListener("visibilitychange", update);
})();
