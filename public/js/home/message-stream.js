(() => {
    const stream = document.querySelector(".hero-message-stream");
    if (!stream) return;

    const hero = stream.parentElement;
    const messages = JSON.parse(stream.dataset.messages || "[]");
    const platforms = JSON.parse(stream.dataset.platforms || "[]");
    if (messages.length === 0 || platforms.length === 0) return;

    const NICKNAMES = [
        "mika_gg", "kubson", "nightowl", "tomek_92", "frostbyte", "ola.pl", "zeroLag",
        "vex", "pixelmarta", "sunny", "orbit", "hexa", "milkyway", "ghostie",
    ];
    // the messages run in lanes from the top of the hero to above its bottom edge
    const LANE_HEIGHT = 56;
    const LANES_TOP = 16;
    const LANES_BOTTOM_MARGIN = 40;
    // each lane moves at its own speed, in pixels per second
    const MIN_SPEED = 42;
    const MAX_SPEED = 78;
    // the space before the next message in a lane, in pixels
    const MIN_GAP = 60;
    const MAX_GAP = 260;
    // at the start, this share of the lanes already shows a message somewhere in this part of the width,
    // the rest wait up to this long
    const START_VISIBLE_CHANCE = 0.5;
    const START_SPREAD = 0.7;
    const MAX_START_DELAY_MS = 4000;
    // where across the hero a message gets its verdict, as a part of the width
    const MIN_SCAN_LINE = 0.4;
    const MAX_SCAN_LINE = 0.6;
    // without animation the messages stand still somewhere in this part of the width
    const MIN_STATIC_X = 0.05;
    const MAX_STATIC_X = 0.7;
    // a resize restarts the lanes once it has stopped for this long
    const RESIZE_SETTLE_MS = 300;
    // the speeds are per second, the timers count milliseconds
    const MS_PER_SECOND = 1000;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    let lanes = [];
    let queue = [];
    let isVisible = false;
    let lastWidth = hero.clientWidth;
    let resizeTimer = null;

    const random = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];

    const nextMessage = () => {
        if (queue.length === 0) {
            queue = [...messages].sort(() => Math.random() - 0.5);
        }
        return queue.pop();
    };

    const createElement = (tag, className, text = null) => {
        const element = document.createElement(tag);
        element.className = className;
        if (text) element.append(text);
        return element;
    };

    const createText = (text) => {
        const element = createElement("span", "hero-message--text");
        text.split(/\{(.)\}/).forEach((part, idx) => {
            element.append(idx % 2 === 0 ? part : createElement("span", "hero-message--censored", part));
        });
        return element;
    };

    const createMessage = (message) => {
        const element = createElement("div", "hero-message");

        const icon = createElement("img", "hero-message--icon");
        icon.src = `/assets/icons/${pick(platforms)}.svg`;
        icon.alt = "";

        const verdictIcon = createElement("span", "hero-message--verdict-icon");
        verdictIcon.append(createElement("i", `fa-solid ${message.category ? "fa-xmark" : "fa-check"}`));

        const verdict = createElement("span", "hero-message--verdict", message.category);
        verdict.prepend(verdictIcon);

        element.append(
            icon,
            createElement("span", "hero-message--nickname", pick(NICKNAMES)),
            createText(message.text),
            verdict,
        );
        return element;
    };

    const judge = (element, message) => {
        element.classList.add(message.category ? "is-blocked" : "is-allowed");
    };

    const measureWithVerdict = (element, message) => {
        judge(element, message);
        const width = element.offsetWidth;
        element.classList.remove("is-allowed", "is-blocked");
        return width;
    };

    const launch = (lane, startX = null) => {
        const width = hero.clientWidth;
        const message = nextMessage();
        const element = createMessage(message);
        element.style.top = `${lane.y}px`;
        stream.append(element);

        const messageWidth = measureWithVerdict(element, message);
        const fromX = startX ?? -messageWidth;
        const scanLine = width * random(MIN_SCAN_LINE, MAX_SCAN_LINE);

        element.animate(
            [{ transform: `translateX(${fromX}px)` }, { transform: `translateX(${width}px)` }],
            { duration: (width - fromX) / lane.speed * MS_PER_SECOND, easing: "linear" }
        ).onfinish = () => element.remove();

        setTimeout(() => judge(element, message), (scanLine - fromX - messageWidth) / lane.speed * MS_PER_SECOND);
        lane.timer = setTimeout(() => launch(lane), (messageWidth + random(MIN_GAP, MAX_GAP)) / lane.speed * MS_PER_SECOND);
    };

    const stop = () => {
        lanes.forEach((lane) => clearTimeout(lane.timer));
        lanes = [];
        stream.replaceChildren();
    };

    // the top of every lane that fits in the hero now
    const laneTops = () => {
        const tops = [];
        for (let y = LANES_TOP; y < hero.clientHeight - LANES_BOTTOM_MARGIN; y += LANE_HEIGHT) {
            tops.push(y);
        }
        return tops;
    };

    const start = () => {
        stop();
        for (const y of laneTops()) {
            const lane = { y, speed: random(MIN_SPEED, MAX_SPEED), timer: null };
            lanes.push(lane);

            if (Math.random() < START_VISIBLE_CHANCE) {
                launch(lane, random(0, hero.clientWidth * START_SPREAD));
            } else {
                lane.timer = setTimeout(() => launch(lane), random(0, MAX_START_DELAY_MS));
            }
        }
    };

    const showStatic = () => {
        for (const y of laneTops()) {
            const message = nextMessage();
            const element = createMessage(message);
            element.style.top = `${y}px`;
            element.style.transform = `translateX(${random(MIN_STATIC_X, MAX_STATIC_X) * hero.clientWidth}px)`;
            stream.append(element);
            judge(element, message);
        }
    };

    if (reducedMotion) {
        showStatic();
        return;
    }

    const update = () => {
        if (isVisible && !document.hidden) {
            start();
        } else {
            stop();
        }
    };

    new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        update();
    }).observe(hero);

    document.addEventListener("visibilitychange", update);

    window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (hero.clientWidth === lastWidth) return;
            lastWidth = hero.clientWidth;
            update();
        }, RESIZE_SETTLE_MS);
    });
})();
