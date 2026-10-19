/**
 * Espace candidat et Relais du site.
 * Livewire + Alpine depuis le bundle ; transitions d'étape et validation animées (GSAP).
 */
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import gsap from 'gsap';

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Entrée de la page : titre, puis blocs en cascade.
 */
function enter(root = document) {
    const items = root.querySelectorAll('[data-enter]');

    if (!items.length || reduceMotion()) {
        gsap.set(items, { clearProps: 'all' });
        return;
    }

    gsap.fromTo(items, { autoAlpha: 0, y: 18 }, {
        autoAlpha: 1, y: 0, duration: 0.8, ease: 'expo.out', stagger: 0.06, clearProps: 'transform,opacity,visibility',
    });
}

/**
 * Changement d'étape : le panneau glisse doucement, les champs suivent.
 */
function stepIn() {
    const panel = document.querySelector('[data-step-panel]');

    if (!panel || reduceMotion()) {
        return;
    }

    gsap.fromTo(panel, { autoAlpha: 0, x: 28 }, { autoAlpha: 1, x: 0, duration: 0.6, ease: 'expo.out', clearProps: 'transform' });
    gsap.fromTo(panel.querySelectorAll('[data-field]'), { autoAlpha: 0, y: 10 }, {
        autoAlpha: 1, y: 0, duration: 0.55, ease: 'power3.out', stagger: 0.04, delay: 0.08, clearProps: 'transform,opacity,visibility',
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Dossier déposé : anneau qui se dessine puis coche.
 */
function celebrate() {
    const ring = document.querySelector('[data-done-ring]');
    const check = document.querySelector('[data-done-check]');

    if (!ring || reduceMotion()) {
        return;
    }

    const length = ring.getTotalLength();
    const checkLength = check.getTotalLength();
    gsap.set(ring, { strokeDasharray: length, strokeDashoffset: length });
    gsap.set(check, { strokeDasharray: checkLength, strokeDashoffset: checkLength });

    gsap.timeline()
        .to(ring, { strokeDashoffset: 0, duration: 0.9, ease: 'power2.inOut' })
        .to(check, { strokeDashoffset: 0, duration: 0.45, ease: 'power3.out' }, '-=0.15')
        .fromTo('[data-done-badge]', { scale: 0.85 }, { scale: 1, duration: 0.9, ease: 'elastic.out(1, 0.5)' }, '<');
}

document.addEventListener('livewire:init', () => {
    Livewire.on('step-changed', () => requestAnimationFrame(stepIn));
    Livewire.on('candidature-submitted', () => requestAnimationFrame(() => {
        enter();
        celebrate();
    }));
});

document.addEventListener('livewire:navigated', () => {
    enter();
    celebrate();
});

Livewire.start();
