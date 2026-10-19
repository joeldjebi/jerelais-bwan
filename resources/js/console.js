/**
 * Console REFLET TV et back-office coordonnateur.
 * Livewire + Alpine chargés depuis le bundle pour enregistrer nos composants avant le démarrage.
 * Motion design (GSAP) : chorégraphie d'entrée à chaque page, compteurs, indicateur de navigation.
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import gsap from 'gsap';
import { SplitText } from 'gsap/SplitText';
import { applyTheme } from './theme';

gsap.registerPlugin(SplitText);

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const normalize = (value) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const numberFormat = new Intl.NumberFormat('fr-FR');

/**
 * Palette de commandes (⌘K / Ctrl+K) : navigation clavier vers toutes les pages accessibles.
 */
Alpine.data('commandPalette', (commands) => ({
    open: false,
    query: '',
    cursor: 0,
    commands,

    get results() {
        const terms = normalize(this.query).split(/\s+/).filter(Boolean);

        return this.commands.filter((command) => {
            const haystack = normalize(`${command.label} ${command.keywords} ${command.group}`);

            return terms.every((term) => haystack.includes(term));
        });
    },

    show() {
        this.open = true;
        this.query = '';
        this.cursor = 0;
        this.$nextTick(() => this.$refs.input?.focus());
    },

    hide() {
        this.open = false;
    },

    move(step) {
        const total = this.results.length;

        if (total === 0) {
            return;
        }

        this.cursor = (this.cursor + step + total) % total;
        this.$nextTick(() => this.$refs.list?.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' }));
    },

    run(command = this.results[this.cursor]) {
        if (!command) {
            return;
        }

        this.hide();
        Livewire.navigate(command.url);
    },
}));

/**
 * Notifications discrètes (succès, erreur), déclenchées depuis Livewire : $this->dispatch('toast', ...).
 */
Alpine.data('toasts', (initial = []) => ({
    items: [],

    init() {
        initial.forEach((toast) => this.add(toast));
    },

    add({ message, tone = 'success' }) {
        const id = Date.now() + Math.random();

        this.items.push({ id, message, tone, visible: true });
        setTimeout(() => this.dismiss(id), 4200);
    },

    dismiss(id) {
        const toast = this.items.find((item) => item.id === id);

        if (toast) {
            toast.visible = false;
            setTimeout(() => (this.items = this.items.filter((item) => item.id !== id)), 400);
        }
    },
}));

/** Compteur qui défile jusqu'à sa valeur (chiffres des indicateurs). */
function countUp(element) {
    const target = Number(element.dataset.count);

    if (Number.isNaN(target)) {
        return;
    }

    const state = { value: 0 };

    gsap.to(state, {
        value: target,
        duration: 1.4,
        ease: 'expo.out',
        delay: 0.25,
        onUpdate: () => (element.textContent = numberFormat.format(Math.round(state.value))),
    });
}

/** Anneau de progression qui se dessine (mise en service). */
function drawRing(circle) {
    gsap.fromTo(circle, { strokeDashoffset: circle.dataset.circumference }, {
        strokeDashoffset: circle.dataset.offset,
        duration: 1.6,
        ease: 'expo.out',
        delay: 0.3,
    });
}

/** Chorégraphie d'entrée de page. */
function choreograph() {
    const main = document.querySelector('[data-console-main]');

    if (!main || main.dataset.choreographed) {
        document.documentElement.classList.add('console-ready');

        return;
    }

    main.dataset.choreographed = 'true';
    document.documentElement.classList.add('console-ready');

    if (reduceMotion()) {
        main.querySelectorAll('[data-map]').forEach((map) => map.setAttribute('data-lit', ''));

        return;
    }

    const timeline = gsap.timeline({ defaults: { ease: 'expo.out' } });
    const title = main.querySelector('[data-split]');

    if (title) {
        const split = SplitText.create(title, { type: 'words', mask: 'words' });

        // Les masques laissent de la place aux accents (É) et aux jambages (g, j).
        gsap.set(split.masks, { paddingTop: '0.18em', marginTop: '-0.18em', paddingBottom: '0.12em', marginBottom: '-0.12em' });
        timeline.from(split.words, { yPercent: 115, duration: 1, stagger: 0.06 }, 0);
    }

    timeline
        .from(main.querySelectorAll('[data-fade]'), { y: 10, autoAlpha: 0, duration: 0.8, stagger: 0.06 }, 0.1)
        // Widgets : éclosion à ressort, en vague à travers la grille bento.
        .from(main.querySelectorAll('[data-widget], [data-bento] > [data-enter]'), {
            y: 26,
            scale: 0.94,
            autoAlpha: 0,
            duration: 1,
            ease: 'back.out(1.25)',
            stagger: { each: 0.07, grid: 'auto', from: 'start' },
            clearProps: 'transform',
        }, 0.15)
        .from(main.querySelectorAll('[data-enter]:not([data-bento] > [data-enter]):not([data-widget])'), { y: 24, autoAlpha: 0, duration: 0.9, stagger: 0.07, clearProps: 'transform' }, 0.2)
        .from(main.querySelectorAll('[data-stagger] > *'), { y: 8, autoAlpha: 0, duration: 0.35, stagger: 0.03, ease: 'power1.out', clearProps: 'all' }, 0.45);

    // Carte du réseau : les points s'allument depuis Abidjan.
    main.querySelectorAll('[data-map]').forEach((map) => setTimeout(() => map.setAttribute('data-lit', ''), 500));

    main.querySelectorAll('[data-count]').forEach(countUp);
    main.querySelectorAll('[data-ring]').forEach(drawRing);
}

/** Indicateur de navigation : glisse vers la page active. */
function moveNavIndicator(animate = true) {
    const nav = document.querySelector('[data-nav]');
    const indicator = nav?.querySelector('[data-nav-indicator]');
    const active = nav?.querySelector('.nav-link.is-active');

    if (!nav || !indicator) {
        return;
    }

    if (!active) {
        gsap.to(indicator, { autoAlpha: 0, duration: 0.2 });

        return;
    }

    const y = active.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop;
    const props = { y, height: active.offsetHeight, autoAlpha: 1 };

    animate && !reduceMotion()
        ? gsap.to(indicator, { ...props, duration: 0.6, ease: 'expo.out' })
        : gsap.set(indicator, props);
}

/** Horloges (heure d'Abidjan = UTC) : aiguilles analogiques et affichage numérique. */
function startClock() {
    const digital = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Abidjan' });
    let previousSecond = -1;

    const tick = (isInitial = false) => {
        const now = new Date();
        const seconds = now.getUTCSeconds();
        const minutes = now.getUTCMinutes() + seconds / 60;
        const hours = (now.getUTCHours() % 12) + minutes / 60;

        document.querySelectorAll('[data-clock-digital]').forEach((element) => (element.textContent = digital.format(now)));

        document.querySelectorAll('[data-analog-clock]').forEach((clock) => {
            const hands = {
                hour: [clock.querySelector('[data-hand="hour"]'), hours * 30],
                minute: [clock.querySelector('[data-hand="minute"]'), minutes * 6],
                second: [clock.querySelector('[data-hand="second"]'), seconds * 6],
            };

            Object.entries(hands).forEach(([name, [hand, angle]]) => {
                let initial = isInitial;
                if (!hand) {
                    return;
                }

                if (!hand.dataset.ready) {
                    // Pivot au centre du cadran (coordonnées du viewBox SVG).
                    gsap.set(hand, { svgOrigin: '50 50', rotation: 0 });
                    hand.dataset.ready = 'true';
                    initial = true;
                }

                // Rotation continue (360 → 366 et non 0) pour éviter que l'aiguille ne revienne en arrière.
                const current = Number(gsap.getProperty(hand, 'rotation')) || 0;
                const target = current + ((((angle - current) % 360) + 360) % 360);

                initial || reduceMotion()
                    ? gsap.set(hand, { rotation: angle })
                    : gsap.to(hand, { rotation: target, duration: name === 'second' ? 0.45 : 1, ease: name === 'second' ? 'elastic.out(1.1, 0.45)' : 'power2.out' });
            });
        });

        previousSecond = seconds;
    };

    tick(true);
    setInterval(() => {
        if (new Date().getUTCSeconds() !== previousSecond) {
            tick();
        }
    }, 250);
}

/**
 * Livewire recopie les attributs de <html> de la page suivante : on réapplique le thème et l'état
 * « JS actif » au moment même de l'échange, pour éviter tout flash clair/sombre.
 */
document.addEventListener('livewire:navigating', (event) => {
    event.detail?.onSwap?.(() => {
        document.documentElement.classList.add('js');
        applyTheme();
    });
});

document.addEventListener('livewire:navigated', () => {
    applyTheme();
    choreograph();
    requestAnimationFrame(() => moveNavIndicator(true));
});

document.addEventListener('DOMContentLoaded', () => {
    startClock();
    choreograph();
    requestAnimationFrame(() => moveNavIndicator(false));
});

window.addEventListener('resize', () => moveNavIndicator(false));

Livewire.start();
