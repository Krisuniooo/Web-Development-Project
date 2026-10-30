(() => {
    const section = document.querySelector(".how-it-works");
    if (!section) return;

    const PLATFORM_MS = 3200;
    const MODEL_MS = 2600;
    const LIMIT_MS = 1800;
    // fade the arrow out, then draw it again towards the next platform
    const ARROW_REDRAW = [
        { strokeDashoffset: 0, opacity: 1 },
        { strokeDashoffset: 0, opacity: 0, offset: 0.2 },
        { strokeDashoffset: 1, opacity: 1, offset: 0.2 },
        { strokeDashoffset: 0, opacity: 1 },
    ];
    const LEVELS = { 0: "Not watching", 35: "Relaxed", 65: "Balanced", 100: "Strict" };
    // [slider index, new value], the last step brings back the initial state
    const LIMIT_STEPS = [[2, 35], [0, 65], [2, 100], [1, 65], [0, 100], [1, 100], [2, 0]];
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // without animation the initial state rendered by the view stays on screen
    if (reducedMotion) return;

    const platforms = section.querySelectorAll(".how-it-works-platform--targets > *");
    const arrow = section.querySelector(".how-it-works-platform--arrow path");
    const models = section.querySelectorAll(".how-it-works-models--item");
    const sliders = section.querySelectorAll(".how-it-works-limits--slider");

    let platformIdx = 0;
    let modelIdx = 0;
    let limitStep = 0;
    let isVisible = false;
    let timers = [];

    const nextPlatform = () => {
        platforms[platformIdx].classList.remove("is-active");
        platformIdx = (platformIdx + 1) % platforms.length;
        arrow.animate(ARROW_REDRAW, { duration: 900, easing: "ease-in-out" }).onfinish = () => {
            platforms[platformIdx].classList.add("is-active");
        };
    };

    const nextModel = () => {
        modelIdx = (modelIdx + 1) % models.length;
        models.forEach((model, idx) => {
            // items past the right end all wait off the card in slot 3
            model.dataset.slot = Math.min((idx - modelIdx + models.length + 2) % models.length - 2, 3);
        });
    };

    const nextLimit = () => {
        const [sliderIdx, value] = LIMIT_STEPS[limitStep];
        limitStep = (limitStep + 1) % LIMIT_STEPS.length;

        const slider = sliders[sliderIdx];
        slider.style.setProperty("--value", value);
        slider.classList.toggle("is-off", value === 0);
        slider.querySelector(".how-it-works-limits--level").textContent = LEVELS[value];
        sliders.forEach((other) => other.classList.toggle("is-dragging", other === slider));
    };

    const update = () => {
        timers.forEach(clearInterval);
        timers = [];
        if (isVisible && !document.hidden) {
            timers = [
                setInterval(nextPlatform, PLATFORM_MS),
                setInterval(nextModel, MODEL_MS),
                setInterval(nextLimit, LIMIT_MS),
            ];
        }
    };

    new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        update();
    }).observe(section);

    document.addEventListener("visibilitychange", update);
})();
