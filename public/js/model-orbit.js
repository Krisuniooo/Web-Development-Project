// x = cos(a) * r
// y = sin(a) * r
(() => {
    const modelScene = document.querySelectorAll(".models-orbit-item");
    const RADIUS = 250; // TODO: adjust to mobile devices
    const FULL_TURN_MS = 30000;

    console.log(modelScene);

    const setRotation = (rotation) => {
        for(let idx = 0; idx < modelScene.length; idx++) {
            // modelScene should have points equally between 
            // 0 and 2 pi, since that is one function period
            const angle = rotation + idx / modelScene.length * Math.PI * 2;
            const x = Math.cos(angle) * RADIUS;
            const y = Math.sin(angle) * RADIUS;

            modelScene[idx].style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px)`;
        }
    }

    const animate = (time) => {
        setRotation(time / FULL_TURN_MS * Math.PI * 2);
        requestAnimationFrame(animate);
    };

    setRotation(0);
    requestAnimationFrame(animate);
})()