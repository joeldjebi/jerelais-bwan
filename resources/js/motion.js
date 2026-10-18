/**
 * Motion design JE RELAIS : apparitions au défilement, compteurs animés,
 * barre de navigation qui se densifie au scroll. Tout est désactivé si
 * l'utilisateur a demandé à réduire les animations.
 */

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function revealOnScroll(root = document) {
    const elements = root.querySelectorAll('[data-reveal]:not([data-revealed])');

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.setAttribute('data-revealed', ''));

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-revealed', '');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
    );

    elements.forEach((element) => {
        const stagger = element.closest('[data-stagger]');

        if (stagger) {
            const index = Array.from(stagger.querySelectorAll('[data-reveal]')).indexOf(element);
            element.style.setProperty('--reveal-delay', `${index * Number(stagger.dataset.stagger || 80)}ms`);
        }

        observer.observe(element);
    });
}

function animateCounters(root = document) {
    const counters = root.querySelectorAll('[data-count]');
    const format = new Intl.NumberFormat('fr-FR');

    const run = (element) => {
        const target = Number(element.dataset.count);
        const duration = 1600;
        const start = performance.now();
        const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            element.textContent = format.format(Math.round(target * easeOutExpo(progress)));

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    };

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        counters.forEach((element) => (element.textContent = format.format(Number(element.dataset.count))));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                run(entry.target);
                observer.unobserve(entry.target);
            }
        });
    });

    counters.forEach((element) => observer.observe(element));
}

function condenseNavOnScroll() {
    const update = () => document.documentElement.toggleAttribute('data-scrolled', window.scrollY > 12);

    update();
    window.addEventListener('scroll', update, { passive: true });
}

export function initMotion(root = document) {
    revealOnScroll(root);
    animateCounters(root);
}

document.addEventListener('DOMContentLoaded', () => {
    condenseNavOnScroll();
    initMotion();
});

// Pages Livewire naviguées sans rechargement (wire:navigate)
document.addEventListener('livewire:navigated', () => initMotion());
