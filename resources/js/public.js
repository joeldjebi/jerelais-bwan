/**
 * Motion design de la vitrine publique JE RELAIS.
 * GSAP (ScrollTrigger, SplitText) + Lenis pour un défilement fluide.
 * Rien n'est animé si l'utilisateur demande à réduire les animations : le contenu reste lisible tel quel.
 */
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import Lenis from 'lenis';

gsap.registerPlugin(ScrollTrigger, SplitText);

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => gsap.utils.toArray(selector, root);

function startSmoothScroll() {
    const lenis = new Lenis({ duration: 1.15, easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)) });

    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.querySelector(link.getAttribute('href'));

            if (target) {
                event.preventDefault();
                lenis.scrollTo(target, { offset: -48, duration: 1.4 });
            }
        });
    });
}

/**
 * Barre de navigation : se densifie au scroll et s'inverse (texte clair) au-dessus
 * des sections sombres, détectées sous la barre elle-même.
 */
function adaptiveNav() {
    const root = document.documentElement;
    const header = $('[data-site-nav]');
    let queued = false;

    const update = () => {
        queued = false;
        const below = document.elementsFromPoint(window.innerWidth / 2, 30).find((element) => !header?.contains(element));
        const themed = below?.closest('[data-nav-theme]');

        root.toggleAttribute('data-scrolled', window.scrollY > 12);
        root.toggleAttribute('data-nav-dark', themed?.dataset.navTheme === 'dark');
    };

    window.addEventListener('scroll', () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    // Les animations scrubbées continuent après le dernier événement scroll : on revérifie à faible cadence.
    let frame = 0;
    gsap.ticker.add(() => {
        frame = (frame + 1) % 8;

        if (frame === 0) {
            update();
        }
    });

    update();
}

/** Timecodes façon régie (HH:MM:SS:FF à 25 images/s). */
function timecodes() {
    const elements = $$('[data-timecode]');

    if (!elements.length) {
        return;
    }

    const pad = (value) => String(value).padStart(2, '0');
    const start = performance.now();

    gsap.ticker.add(() => {
        const elapsed = (performance.now() - start) / 1000;

        elements.forEach((element) => {
            const total = elapsed + Number(element.dataset.timecode);
            const frames = Math.floor((total % 1) * 25);
            const seconds = Math.floor(total);

            element.textContent = `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor(seconds / 60) % 60)}:${pad(seconds % 60)}:${pad(frames)}`;
        });
    });
}

/** Ouverture : titre lettre à lettre, mosaïque qui s'assemble, puis parallaxe au défilement. */
function hero() {
    const section = $('[data-hero]');
    const title = $('[data-hero-title]');
    const wall = $('[data-wall]');

    if (!section || !title || !wall) {
        return;
    }

    const tiles = Array.from(wall.children);
    const split = SplitText.create(title, { type: 'chars', mask: 'chars' });

    gsap.timeline({ defaults: { ease: 'expo.out' } })
        .set(title, { autoAlpha: 1 })
        .from(split.chars, { yPercent: 115, duration: 1.4, stagger: 0.045 }, 0.15)
        .from('[data-hero-fade]', { y: 28, autoAlpha: 0, duration: 1.2, stagger: 0.12 }, 0.6)
        .from(tiles, { y: 120, autoAlpha: 0, scale: 0.92, duration: 1.6, stagger: { each: 0.08, from: 'center' } }, 0.75);

    // Au défilement, le texte s'efface et la mosaïque remonte au centre, colonnes décalées.
    const lift = () => wall.offsetTop - (window.innerHeight - wall.offsetHeight) / 2;
    const timeline = gsap.timeline({
        scrollTrigger: { trigger: section, start: 'top top', end: '+=90%', pin: true, scrub: 0.8, invalidateOnRefresh: true },
    });

    timeline
        .to('[data-hero-copy]', { y: -120, autoAlpha: 0, ease: 'power2.in', duration: 0.5 }, 0)
        .to(wall, { y: () => -lift(), scale: 1.04, ease: 'power2.inOut', duration: 1 }, 0);

    tiles.forEach((tile, index) => {
        const offset = index % 3 === 1 ? -3.6 : 3.6;

        timeline.fromTo(tile, { yPercent: 0 }, { yPercent: offset * (index < 3 ? 1 : 0.6), ease: 'none', duration: 1 }, 0);
    });
}

/** Phrase manifeste : les mots s'allument au fil du défilement. */
function scrubText() {
    $$('[data-scrub-text]').forEach((element) => {
        const split = SplitText.create(element, { type: 'words' });

        gsap.fromTo(split.words, { opacity: 0.14 }, {
            opacity: 1,
            stagger: 0.1,
            ease: 'none',
            scrollTrigger: { trigger: element, start: 'top 78%', end: 'bottom 42%', scrub: true },
        });
    });
}

/** Récit épinglé : capture → vérification → antenne. */
function story() {
    const section = $('[data-story]');

    if (!section) {
        return;
    }

    const steps = $$('[data-story-step]', section);
    const screens = $$('[data-story-screen]', section);
    const bars = $$('[data-story-bar]', section);

    gsap.set([...steps.slice(1), ...screens.slice(1)], { autoAlpha: 0 });

    const timeline = gsap.timeline({
        defaults: { ease: 'power3.out' },
        scrollTrigger: { trigger: section, start: 'top top', end: `+=${steps.length * 110}%`, pin: true, scrub: 0.7 },
    });

    steps.forEach((step, index) => {
        timeline.fromTo(bars[index], { scaleX: 0 }, { scaleX: 1, ease: 'none', duration: 1 }, index);

        if (index > 0) {
            timeline
                .to(steps[index - 1], { autoAlpha: 0, y: -32, duration: 0.22 }, index)
                .fromTo(step, { autoAlpha: 0, y: 40 }, { autoAlpha: 1, y: 0, duration: 0.3 }, index + 0.12)
                .to(screens[index - 1], { autoAlpha: 0, scale: 0.97, duration: 0.25 }, index)
                .fromTo(screens[index], { autoAlpha: 0, scale: 1.03 }, { autoAlpha: 1, scale: 1, duration: 0.3 }, index + 0.08);
        }
    });

    timeline
        .from($$('[data-check]', section), { autoAlpha: 0, x: -14, stagger: 0.12, duration: 0.2 }, 1.3)
        .fromTo('[data-check-circle]', { strokeDashoffset: 160 }, { strokeDashoffset: 0, duration: 0.35, ease: 'power2.inOut' }, 1.65)
        .from('[data-story-notification]', { yPercent: -140, duration: 0.3, ease: 'back.out(1.6)' }, 2.35);
}

/** Catégories : cartes qui se lèvent en cascade. */
function categories() {
    gsap.set('[data-category]', { autoAlpha: 0, y: 60, rotateX: -8, transformPerspective: 900 });

    ScrollTrigger.batch('[data-category]', {
        start: 'top 90%',
        once: true,
        onEnter: (batch) => gsap.to(batch, { autoAlpha: 1, y: 0, rotateX: 0, duration: 1.2, ease: 'expo.out', stagger: 0.08 }),
    });
}

/** Carte : les points s'allument depuis Abidjan, les liaisons se tracent. */
function networkMap(animated) {
    const map = $('[data-map]');

    if (!map) {
        return;
    }

    if (!animated) {
        map.setAttribute('data-lit', '');

        return;
    }

    ScrollTrigger.create({ trigger: map, start: 'top 72%', once: true, onEnter: () => map.setAttribute('data-lit', '') });
}

/** Bandeaux défilants, accélérés par la vitesse du scroll. */
function marquees() {
    const tweens = $$('[data-marquee]').map((row) => {
        const toRight = row.dataset.marquee === 'right';

        return gsap.fromTo(row.firstElementChild,
            { xPercent: toRight ? -50 : 0 },
            { xPercent: toRight ? 0 : -50, duration: Number(row.dataset.speed || 40), ease: 'none', repeat: -1 },
        );
    });

    if (!tweens.length) {
        return;
    }

    let boost = 1;

    ScrollTrigger.create({
        onUpdate: (self) => {
            boost = 1 + Math.min(Math.abs(self.getVelocity()) / 300, 6);
        },
    });

    gsap.ticker.add(() => {
        boost += (1 - boost) * 0.06;
        tweens.forEach((tween) => tween.timeScale(boost));
    });
}

/** Console : la fenêtre se redresse au scroll, puis les relais arrivent au fil de l'eau. */
function consolePreview() {
    const rise = $('[data-console-rise]');
    const feed = $('[data-live-feed]');

    if (rise) {
        gsap.fromTo(rise, { y: 140, rotateX: 18, scale: 0.92, transformPerspective: 1400, transformOrigin: 'center top' }, {
            y: 0, rotateX: 0, scale: 1, ease: 'none',
            scrollTrigger: { trigger: rise, start: 'top 95%', end: 'top 35%', scrub: 0.8 },
        });
    }

    if (!feed) {
        return;
    }

    const template = $('[data-feed-template]');
    const icons = $('[data-feed-icons]');
    const statuses = $('[data-status-templates]');
    const pool = JSON.parse($('[data-feed-pool]')?.textContent || '[]');
    let cursor = 0;
    let timer = null;

    const statusMarkup = (name) => statuses.content.querySelector(`[data-status="${name}"]`).cloneNode(true);

    const advance = () => {
        // Les relais déjà arrivés progressent : en attente → en vérification → validé.
        feed.querySelectorAll('[data-stage]').forEach((item) => {
            const next = { pending: 'review', review: 'validated' }[item.dataset.stage];
            const slot = item.querySelector('[data-feed-status]');

            if (next && slot) {
                item.dataset.stage = next;
                slot.replaceChildren(statusMarkup(next));
                gsap.from(slot, { scale: 0.8, autoAlpha: 0, duration: 0.5, ease: 'back.out(2)' });
            }
        });

        const entry = pool[cursor++ % pool.length];
        const item = template.content.firstElementChild.cloneNode(true);

        item.dataset.stage = 'pending';
        item.querySelector('[data-feed-title]').textContent = entry.title;
        item.querySelector('[data-feed-place]').textContent = entry.place;
        item.querySelector('[data-feed-icon]').append(icons.content.querySelector(`[data-icon="${entry.icon}"]`).cloneNode(true));

        feed.prepend(item);
        gsap.from(item, { height: 0, autoAlpha: 0, y: -16, paddingTop: 0, paddingBottom: 0, duration: 0.8, ease: 'expo.out' });

        const last = feed.lastElementChild;

        if (feed.children.length > 4 && last) {
            gsap.to(last, { autoAlpha: 0, height: 0, paddingTop: 0, paddingBottom: 0, duration: 0.5, ease: 'power2.in', onComplete: () => last.remove() });
        }
    };

    ScrollTrigger.create({
        trigger: feed,
        start: 'top bottom',
        end: 'bottom top',
        onToggle: (self) => {
            clearInterval(timer);

            if (self.isActive) {
                timer = setInterval(advance, 2600);
            }
        },
    });
}

/** Appels à relais : les notifications tombent une à une sur l'écran verrouillé. */
function calls() {
    const section = $('[data-calls]');

    if (!section) {
        return;
    }

    gsap.from($$('[data-call]', section), {
        y: -40,
        autoAlpha: 0,
        scale: 0.94,
        stagger: 0.25,
        ease: 'power3.out',
        scrollTrigger: { trigger: section, start: 'top 65%', end: 'center 45%', scrub: 0.8 },
    });
}

/** Les étapes d'inscription défilent horizontalement (écrans larges). */
function horizontalSteps(mm) {
    mm.add('(min-width: 768px)', () => {
        const section = $('[data-hscroll]');
        const track = $('[data-hscroll-track]');

        if (!section || !track) {
            return;
        }

        const distance = () => track.scrollWidth - document.documentElement.clientWidth + 48;

        gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: { trigger: section, start: 'top top', end: () => `+=${distance()}`, pin: true, scrub: 0.7, invalidateOnRefresh: true },
        });
    });
}

function reveals() {
    gsap.set('[data-anim]', { autoAlpha: 0, y: 40 });

    ScrollTrigger.batch('[data-anim]', {
        start: 'top 88%',
        once: true,
        onEnter: (batch) => gsap.to(batch, { autoAlpha: 1, y: 0, duration: 1.1, ease: 'expo.out', stagger: 0.09 }),
    });
}

function counters() {
    $$('[data-counter]').forEach((element) => {
        const target = Number(element.dataset.counter);
        const state = { value: 0 };

        gsap.to(state, {
            value: target,
            duration: 1.8,
            ease: 'expo.out',
            scrollTrigger: { trigger: element, start: 'top 85%', once: true },
            onUpdate: () => (element.textContent = Math.round(state.value)),
        });
    });
}

function finale() {
    const title = $('[data-finale-title]');

    if (!title) {
        return;
    }

    const split = SplitText.create(title, { type: 'words,chars', mask: 'chars' });

    gsap.from(split.chars, {
        yPercent: 110,
        duration: 1.2,
        ease: 'expo.out',
        stagger: 0.025,
        scrollTrigger: { trigger: title, start: 'top 80%', once: true },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    adaptiveNav();

    const mm = gsap.matchMedia();

    mm.add('(prefers-reduced-motion: no-preference)', () => {
        startSmoothScroll();
        timecodes();
        document.fonts.ready.then(() => {
            hero();
            scrubText();
            story();
            categories();
            networkMap(true);
            marquees();
            consolePreview();
            calls();
            horizontalSteps(mm);
            reveals();
            counters();
            finale();
            ScrollTrigger.refresh();
        });
    });

    mm.add('(prefers-reduced-motion: reduce)', () => {
        networkMap(false);
        document.querySelectorAll('[data-counter]').forEach((element) => (element.textContent = element.dataset.counter));
    });
});
