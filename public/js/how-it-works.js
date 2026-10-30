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
    const LIMIT_SHIFT = 35;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // without animation the initial state rendered by the view stays on screen
    if (reducedMotion) return;

    const platforms = section.querySelectorAll(".how-it-works-platform--targets > *");
    const arrow = section.querySelector(".how-it-works-platform--arrow path");
    const models = section.querySelectorAll(".how-it-works-models--item");
    const sliders = section.querySelectorAll(".how-it-works-limits--slider");
    const levels = JSON.parse(section.dataset.levels || "[]");
    const initialSlots = [...models].map((model) => model.dataset.slot);
    const initialLimits = [...sliders].map((slider) => Number(slider.style.getPropertyValue("--value")));

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
        // each item takes over the slot the view gave to the item modelIdx places before it
        models.forEach((model, idx) => {
            model.dataset.slot = initialSlots[(idx - modelIdx + models.length) % models.length];
        });
    };

    // sliders take turns moving away from their initial value, then back in the same order
    const nextLimit = () => {
        if (sliders.length === 0 || levels.length === 0) return;

        const sliderIdx = limitStep % sliders.length;
        const isLeaving = limitStep < sliders.length;
        limitStep = (limitStep + 1) % (sliders.length * 2);

        const initial = initialLimits[sliderIdx];
        const shifted = initial < 50 ? initial + LIMIT_SHIFT : initial - LIMIT_SHIFT;
        const value = isLeaving ? shifted : initial;

        const slider = sliders[sliderIdx];
        slider.style.setProperty("--value", value);
        slider.classList.toggle("is-off", value === 0);
        slider.querySelector(".how-it-works-limits--level").textContent = levels.find(({ max }) => value <= max).name;
        sliders.forEach((other) => other.classList.toggle("is-dragging", other === slider));
    };

    const update = () => {
        const isRunning = isVisible && !document.hidden;
        section.classList.toggle("is-paused", !isRunning);

        timers.forEach(clearInterval);
        timers = [];
        if (isRunning) {
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
