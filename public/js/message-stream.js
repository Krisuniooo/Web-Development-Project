(() => {
    const stream = document.querySelector(".hero-message-stream");
    if (!stream) return;

    const hero = stream.parentElement;
    const messages = JSON.parse(stream.dataset.messages || "[]");
    if (messages.length === 0) return;

    const PLATFORMS = ["discord", "twitch", "kick", "telegram"];
    const NICKNAMES = [
        "mika_gg", "kubson", "nightowl", "tomek_92", "frostbyte", "ola.pl", "zeroLag",
        "vex", "pixelmarta", "sunny", "orbit", "hexa", "milkyway", "ghostie",
    ];
    const LANE_HEIGHT = 56;
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
        icon.src = `/assets/icons/${pick(PLATFORMS)}.svg`;
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
        const scanLine = width * random(0.4, 0.6);

        element.animate(
            [{ transform: `translateX(${fromX}px)` }, { transform: `translateX(${width}px)` }],
            { duration: (width - fromX) / lane.speed * 1000, easing: "linear" }
        ).onfinish = () => element.remove();

        setTimeout(() => judge(element, message), (scanLine - fromX - messageWidth) / lane.speed * 1000);
        lane.timer = setTimeout(() => launch(lane), (messageWidth + random(60, 260)) / lane.speed * 1000);
    };

    const stop = () => {
        lanes.forEach((lane) => clearTimeout(lane.timer));
        lanes = [];
        stream.replaceChildren();
    };

    const start = () => {
        stop();
        for (let y = 16; y < hero.clientHeight - 40; y += LANE_HEIGHT) {
            const lane = { y, speed: random(42, 78), timer: null };
            lanes.push(lane);

            if (Math.random() < 0.5) {
                launch(lane, random(0, hero.clientWidth * 0.7));
            } else {
                lane.timer = setTimeout(() => launch(lane), random(0, 4000));
            }
        }
    };

    const showStatic = () => {
        for (let y = 16; y < hero.clientHeight - 40; y += LANE_HEIGHT) {
            const message = nextMessage();
            const element = createMessage(message);
            element.style.top = `${y}px`;
            element.style.transform = `translateX(${random(0.05, 0.7) * hero.clientWidth}px)`;
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
        }, 300);
    });
})();
