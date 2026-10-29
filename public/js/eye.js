(() => {
    const frame = document.querySelector(".hero-eye--frame");
    if (!frame) return;

    const rawFrames = JSON.parse(frame.dataset.frames || "[]");
    if (rawFrames.length === 0) return;

    const scleraLayer = frame.querySelector(".hero-eye--sclera-layer");
    const opening = frame.querySelector(".hero-eye--opening");
    const irisLayer = frame.querySelector(".hero-eye--iris-layer");

    const IRIS_CENTER_X = 53;
    const IRIS_RADIUS_X = 22;
    const OPENING_RADIUS_X = 26;
    const MAX_SHIFT_X = 3;
    const MAX_SHIFT_Y = 1;
    const FULL_LOOK_DISTANCE = 380;
    const IDLE_AFTER_MS = 5000;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const width = Math.max(...rawFrames.flatMap((rawFrame) => rawFrame.lines.map((line) => line.length)));
    const height = Math.max(...rawFrames.map((rawFrame) => rawFrame.lines.length));

    const escapeChar = (char) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;" })[char] ?? char;
    const random = (min, max) => min + Math.random() * (max - min);
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const median = (values) => {
        const sorted = [...values].sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
    };

    const findHighlight = (lines, isInIris) => {
        const zeros = new Set();
        lines.forEach((line, y) => {
            for (let x = 0; x < line.length; x++) {
                if (line[x] === "0" && isInIris(x, y)) zeros.add(`${x},${y}`);
            }
        });

        const visited = new Set();
        let blob = [];
        for (const start of zeros) {
            if (visited.has(start)) continue;

            const group = [];
            const stack = [start];
            visited.add(start);
            while (stack.length > 0) {
                const point = stack.pop();
                group.push(point);
                const [px, py] = point.split(",").map(Number);
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const next = `${px + dx},${py + dy}`;
                        if (zeros.has(next) && !visited.has(next)) {
                            visited.add(next);
                            stack.push(next);
                        }
                    }
                }
            }
            if (group.length > blob.length) blob = group;
        }
        if (blob.length === 0) return () => false;

        const rows = new Map();
        blob.forEach((point) => {
            const [x, y] = point.split(",").map(Number);
            const [min, max] = rows.get(y) ?? [Infinity, -Infinity];
            rows.set(y, [Math.min(min, x), Math.max(max, x)]);
        });

        const ys = [...rows.keys()];
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);
        const centerX = median([...rows.values()].map(([min, max]) => (min + max + 1) / 2));
        const centerY = (minY + maxY + 1) / 2;
        const radiusX = median([...rows.values()].map(([min, max]) => max - min + 1).sort((a, b) => a - b).slice(-3)) / 2;
        const radiusY = (maxY - minY + 1) / 2;
        const inBlob = new Set(blob);

        return (x, y) => {
            const mirrorX = Math.round(2 * centerX - x - 1);
            if (!inBlob.has(`${x},${y}`) || !inBlob.has(`${mirrorX},${y}`)) return false;

            const dx = (x + 0.5 - centerX) / radiusX;
            const dy = (y + 0.5 - centerY) / radiusY;
            return dx * dx + dy * dy <= 1.1;
        };
    };

    const toLayers = (rawFrame) => {
        const lines = Array.from({ length: height }, (_, y) => (rawFrame.lines[y] ?? "").padEnd(width, " "));
        const filledRows = lines.map((line, y) => (line.trim() ? y : -1)).filter((y) => y >= 0);
        const top = filledRows[0] ?? 0;
        const bottom = filledRows[filledRows.length - 1] ?? 0;
        const centerY = (top + bottom) / 2;
        const radiusY = Math.max(1, (bottom - top) / 2 + 1);

        const isInIris = (x, y) => {
            if (rawFrame.closed) return false;
            const dx = (x - IRIS_CENTER_X) / IRIS_RADIUS_X;
            const dy = (y - centerY) / radiusY;
            return dx * dx + dy * dy <= 1;
        };
        const isHighlight = findHighlight(lines, isInIris);

        const toHtml = (forIris) => lines.map((line, y) => {
            let html = "";
            let currentClass = null;
            let buffer = "";
            const flush = () => {
                if (!buffer) return;
                html += currentClass ? `<span class="${currentClass}">${buffer}</span>` : buffer;
                buffer = "";
            };

            for (let x = 0; x < line.length; x++) {
                let char = line[x];
                let className = null;
                if (char !== " ") {
                    const inIris = isInIris(x, y);
                    if (inIris !== forIris) {
                        char = " ";
                    } else if (!inIris) {
                        className = "hero-eye--sclera";
                    } else {
                        className = isHighlight(x, y) ? "hero-eye--highlight" : "hero-eye--iris";
                    }
                }
                if (className !== currentClass) {
                    flush();
                    currentClass = className;
                }
                buffer += escapeChar(char);
            }
            flush();
            return html.trimEnd();
        }).join("\n");

        return {
            duration: rawFrame.duration,
            sclera: toHtml(false),
            iris: toHtml(true),
            opening: { cx: IRIS_CENTER_X + 0.5, cy: centerY + 0.5, rx: OPENING_RADIUS_X, ry: radiusY + MAX_SHIFT_Y + 0.3 },
        };
    };

    const frames = rawFrames.map(toLayers);

    let charWidth = 6;
    let charHeight = 10;
    let current = 0;

    const measureChar = () => {
        const sample = document.createElement("span");
        sample.textContent = "0".repeat(20);
        sample.style.cssText = "position: absolute; visibility: hidden; white-space: pre;";
        scleraLayer.append(sample);
        charWidth = sample.getBoundingClientRect().width / 20;
        sample.remove();
        charHeight = parseFloat(getComputedStyle(scleraLayer).lineHeight) || charWidth * 1.87;
    };

    const updateOpening = () => {
        const { cx, cy, rx, ry } = frames[current].opening;
        opening.style.clipPath = `ellipse(${rx * charWidth}px ${ry * charHeight}px at ${cx * charWidth}px ${cy * charHeight}px)`;
    };

    const show = (idx) => {
        current = idx;
        scleraLayer.innerHTML = frames[idx].sclera;
        irisLayer.innerHTML = frames[idx].iris;
        updateOpening();
    };

    const remeasure = () => {
        measureChar();
        updateOpening();
    };

    frame.style.setProperty("--rows", height);
    show(0);
    remeasure();
    new ResizeObserver(remeasure).observe(frame);
    document.fonts.ready.then(remeasure);

    if (reducedMotion) return;

    let targetX = 0;
    let targetY = 0;
    let lookX = 0;
    let lookY = 0;
    let isLooping = false;
    let isActive = false;
    let isVisible = false;
    let lastPointerMove = 0;
    let idleTimer = null;
    let blinkRun = 0;

    const follow = () => {
        lookX += (targetX - lookX) * 0.14;
        lookY += (targetY - lookY) * 0.14;
        irisLayer.style.transform = `translate(${lookX * MAX_SHIFT_X * charWidth}px, ${lookY * MAX_SHIFT_Y * charHeight}px)`;

        if (Math.abs(targetX - lookX) > 0.001 || Math.abs(targetY - lookY) > 0.001) {
            requestAnimationFrame(follow);
        } else {
            isLooping = false;
        }
    };

    const lookAt = (x, y) => {
        targetX = Math.max(-1, Math.min(1, x));
        targetY = Math.max(-1, Math.min(1, y));
        if (isLooping) return;
        isLooping = true;
        requestAnimationFrame(follow);
    };

    const lookAround = () => {
        if (performance.now() - lastPointerMove < IDLE_AFTER_MS) return;
        if (Math.random() < 0.35) {
            lookAt(0, 0);
        } else {
            lookAt(random(-0.9, 0.9), random(-0.6, 0.6));
        }
    };

    const blink = async (run) => {
        const isCurrent = () => run === blinkRun;

        while (isCurrent()) {
            show(0);
            await wait(frames[0].duration + Math.random() * 3500);

            const repeats = Math.random() < 0.2 ? 2 : 1;
            for (let repeat = 0; repeat < repeats && isCurrent(); repeat++) {
                for (let idx = 1; idx < frames.length && isCurrent(); idx++) {
                    show(idx);
                    await wait(frames[idx].duration);
                }
                if (repeat < repeats - 1) {
                    show(0);
                    await wait(120);
                }
            }
        }
    };

    const start = () => {
        if (isActive) return;
        isActive = true;
        idleTimer = setInterval(lookAround, 2600);
        blinkRun++;
        if (frames.length > 1) blink(blinkRun);
    };

    const stop = () => {
        if (!isActive) return;
        isActive = false;
        clearInterval(idleTimer);
        blinkRun++;
        show(0);
    };

    const update = () => {
        if (isVisible && !document.hidden) {
            start();
        } else {
            stop();
        }
    };

    window.addEventListener("pointermove", (event) => {
        if (!isActive || event.pointerType === "touch") return;
        lastPointerMove = performance.now();

        const rect = frame.getBoundingClientRect();
        const dx = event.clientX - (rect.left + (IRIS_CENTER_X + 0.5) * charWidth);
        const dy = event.clientY - (rect.top + (height / 2) * charHeight);
        const distance = Math.hypot(dx, dy) || 1;
        const strength = Math.min(1, distance / FULL_LOOK_DISTANCE);
        lookAt(dx / distance * strength, dy / distance * strength);
    });

    document.addEventListener("mouseout", (event) => {
        if (!event.relatedTarget) lookAt(0, 0);
    });

    new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        update();
    }).observe(frame);

    document.addEventListener("visibilitychange", update);
})();
