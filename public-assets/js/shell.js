const headerMenu = document.querySelector('.header-menu');
if (headerMenu) {
    document.addEventListener('pointerdown', (event) => {
        if (headerMenu.open && !headerMenu.contains(event.target)) headerMenu.open = false;
    });
    headerMenu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && headerMenu.open) {
            headerMenu.open = false;
            headerMenu.querySelector('summary').focus();
        }
    });
}

if (window.isSecureContext && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            // The site remains usable when the browser disallows service workers.
        });
    }, {once: true});
}

document.addEventListener('click', (event) => {
    document.querySelectorAll('.post-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !(event.target instanceof Element)) return;
    const menu = event.target.closest('.post-menu[open]');
    if (menu) {
        menu.open = false;
        menu.querySelector('summary').focus();
    }
});

function revealClarification() {
    if (!/^#comment-\d+$/.test(location.hash)) return;
    const target = document.getElementById(location.hash.slice(1));
    if (!target) return;
    let opened = false;
    for (let node = target.parentElement; node; node = node.parentElement) {
        if (node.matches('.clarification-list:not([open]), .comment-branch:not([open])')) {
            node.open = true;
            opened = true;
        }
    }
    if (opened) target.scrollIntoView();
}
revealClarification();
window.addEventListener('hashchange', revealClarification);
