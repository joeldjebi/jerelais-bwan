/**
 * Motion design de la vitrine publique JE RELAIS.
 * GSAP (ScrollTrigger, SplitText) + Lenis pour un défilement fluide, qualité « habillage TV ».
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

    return lenis;
}

/**
 * Barre de navigation : se densifie au scroll et s'inverse (texte clair) au-dessus
 * des sections sombres, détectées sous la barre elle-même (transformations comprises).
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

function hero() {
    const title = $('[data-hero-title]');

    if (!title) {
        return;
    }

    const split = SplitText.create(title, { type: 'chars', mask: 'chars' });

    gsap.timeline({ defaults: { ease: 'expo.out' } })
        .set(title, { autoAlpha: 1 })
        .from(split.chars, { yPercent: 115, duration: 1.4, stagger: 0.045 }, 0.15)
        .from('[data-hero-fade]', { y: 28, autoAlpha: 0, duration: 1.2, stagger: 0.12 }, 0.7);
}

/** L'écran TV grandit jusqu'au plein cadre, puis l'habillage antenne entre en scène. */
function broadcastScreen() {
    const wrap = $('[data-screen-wrap]');

    if (!wrap) {
        return;
    }

    gsap.timeline({
        scrollTrigger: { trigger: wrap, start: 'top top', end: '+=120%', pin: true, scrub: 0.8 },
    })
        .fromTo('[data-screen]', { scale: 0.72, borderRadius: 40 }, { scale: 1, borderRadius: 0, ease: 'power2.inOut', duration: 1 })
        .to('[data-screen-hint]', { autoAlpha: 0, duration: 0.2 }, 0)
        .fromTo('[data-lower-third] > *', { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', ease: 'expo.out', duration: 0.4, stagger: 0.1 }, 0.75)
        .from('[data-screen-bug]', { autoAlpha: 0, y: -12, duration: 0.25 }, 0.7);
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
                .to(steps[index - 1], { autoAlpha: 0, y: -48, duration: 0.3 }, index)
                .fromTo(step, { autoAlpha: 0, y: 48 }, { autoAlpha: 1, y: 0, duration: 0.3 }, index + 0.05)
                .to(screens[index - 1], { autoAlpha: 0, scale: 0.97, duration: 0.3 }, index)
                .fromTo(screens[index], { autoAlpha: 0, scale: 1.03 }, { autoAlpha: 1, scale: 1, duration: 0.3 }, index + 0.05);
        }
    });

    timeline
        .from($$('[data-check]', section), { autoAlpha: 0, x: -14, stagger: 0.12, duration: 0.2 }, 1.25)
        .fromTo('[data-check-circle]', { strokeDashoffset: 160 }, { strokeDashoffset: 0, duration: 0.35, ease: 'power2.inOut' }, 1.6)
        .from('[data-story-notification]', { yPercent: -140, duration: 0.3, ease: 'back.out(1.6)' }, 2.35);
}

/** Bandeaux défilants façon téléscripteur, accélérés par la vitesse du scroll. */
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

/** Horloge de l'écran « en direct » (heure d'Abidjan = UTC). */
function liveClock() {
    const clocks = document.querySelectorAll('[data-live-clock]');
    const format = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Abidjan' });
    const tick = () => clocks.forEach((clock) => (clock.textContent = format.format(new Date())));

    tick();
    setInterval(tick, 15000);
}

document.addEventListener('DOMContentLoaded', () => {
    liveClock();
    adaptiveNav();

    const mm = gsap.matchMedia();

    mm.add('(prefers-reduced-motion: no-preference)', () => {
        startSmoothScroll();
        document.fonts.ready.then(() => {
            hero();
            broadcastScreen();
            scrubText();
            story();
            marquees();
            horizontalSteps(mm);
            reveals();
            counters();
            finale();
            ScrollTrigger.refresh();
        });
    });

    mm.add('(prefers-reduced-motion: reduce)', () => {
        document.querySelectorAll('[data-counter]').forEach((element) => (element.textContent = element.dataset.counter));
    });
});
