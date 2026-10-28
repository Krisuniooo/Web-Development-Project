// x = cos(a) * r
// y = sin(a) * r
(() => {
    const display = document.querySelector(".models-display");
    const modelScene = document.querySelectorAll(".models-orbit-item");
    if (!display || modelScene.length === 0) return;

    const MAX_RADIUS = 250;
    const FULL_TURN_MS = 30000;
    const TAU = Math.PI * 2;
    // modelScene should have points equally between
    // 0 and 2 pi, since that is one function period
    const ANGLE_STEP = TAU / modelScene.length;
    const ANGLE_PER_MS = TAU / FULL_TURN_MS;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    let radius = MAX_RADIUS;
    let rotation = 0;
    let lastTime = null;
    let frameId = null;
    let isVisible = false;

    const setRotation = (rotation) => {
        for(let idx = 0; idx < modelScene.length; idx++) {
            const angle = rotation + idx * ANGLE_STEP;
            const x = Math.cos(angle) * radius;
            const y = Math.sin(angle) * radius;

            modelScene[idx].style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px)`;
        }
    }

    // keep whole items inside the display, whatever its current size
    const updateRadius = () => {
        const item = modelScene[0];
        const itemHalf = Math.max(item.offsetWidth, item.offsetHeight) / 2;
        const displayHalf = Math.min(display.clientWidth, display.clientHeight) / 2;

        radius = Math.max(0, Math.min(MAX_RADIUS, displayHalf - itemHalf));
        setRotation(rotation);
    };

    const animate = (time) => {
        // advance by elapsed time, so pausing does not make items jump
        if (lastTime !== null) {
            rotation = (rotation + (time - lastTime) * ANGLE_PER_MS) % TAU;
        }
        lastTime = time;
        setRotation(rotation);
        frameId = requestAnimationFrame(animate);
    };

    const start = () => {
        if (frameId !== null || !isVisible || reducedMotion.matches) return;
        lastTime = null;
        frameId = requestAnimationFrame(animate);
    };

    const stop = () => {
        if (frameId === null) return;
        cancelAnimationFrame(frameId);
        frameId = null;
    };

    new ResizeObserver(updateRadius).observe(display);

    new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        isVisible ? start() : stop();
    }).observe(display);

    reducedMotion.addEventListener("change", () => {
        reducedMotion.matches ? stop() : start();
    });

    updateRadius();
})()
