/**
 * Espace candidat et Relais du site.
 * Livewire + Alpine depuis le bundle ; transitions d'étape et validation animées (GSAP).
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
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

/**
 * Envoi des médias depuis le site (WEB-04) : chaque fichier est annoncé, puis déposé en morceaux
 * directement sur Wasabi par URL signée ; une coupure reprend là où elle s'est arrêtée.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const json = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers ?? {}) },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
        throw new Error(firstError ?? body.message ?? 'Envoi impossible');
    }

    return body.data ?? body;
};

const kindOf = (file) => (file.type.startsWith('video/') ? 'video' : file.type.startsWith('audio/') ? 'audio' : 'photo');
const uuid = () => crypto.randomUUID();
const hex = (buffer) => [...new Uint8Array(buffer)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
const HASH_LIMIT = 100 * 1024 * 1024;

function putPart(url, blob, onProgress) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('PUT', url);
        request.upload.onprogress = (event) => onProgress(event.loaded);
        request.onload = () => (request.status >= 200 && request.status < 300 ? resolve((request.getResponseHeader('ETag') ?? '').replaceAll('"', '')) : reject(new Error(`HTTP ${request.status}`)));
        request.onerror = () => reject(new Error('Réseau indisponible'));
        request.send(blob);
    });
}

Alpine.data('webUploader', (config) => ({
    files: [],
    busy: false,

    get done() {
        return this.files.length > 0 && this.files.every((file) => ['uploaded', 'processing', 'ready'].includes(file.status));
    },

    async add(event) {
        for (const file of [...event.target.files]) {
            const entry = { id: uuid(), name: file.name, size: file.size, kind: kindOf(file), progress: 0, status: 'waiting', error: null, file };
            this.files.push(entry);
        }

        event.target.value = '';
        this.run();
    },

    async run() {
        if (this.busy) {
            return;
        }

        this.busy = true;

        for (const entry of this.files.filter((item) => ['waiting', 'failed'].includes(item.status))) {
            await this.upload(entry);
        }

        this.busy = false;
        this.$dispatch('medias-changed');
    },

    async upload(entry) {
        entry.status = 'uploading';
        entry.error = null;

        try {
            const sha256 = entry.size <= HASH_LIMIT ? hex(await crypto.subtle.digest('SHA-256', await entry.file.arrayBuffer())) : null;
            let media = entry.media ?? await json(config.announceUrl, {
                method: 'POST',
                body: JSON.stringify({ client_uuid: entry.id, kind: entry.kind, mime: entry.file.type || 'application/octet-stream', size: entry.size, sha256, filename: entry.name }),
            });
            entry.media = media;

            if (media.upload) {
                const partSize = media.upload.part_size;
                const parts = [...media.upload.uploaded_parts.map((part) => ({ number: part.number, etag: part.etag }))];
                let sent = parts.length * partSize;

                for (const part of media.upload.missing_parts) {
                    const blob = entry.file.slice((part.number - 1) * partSize, part.number * partSize);
                    let etag = null;

                    for (let attempt = 1; attempt <= 3 && etag === null; attempt++) {
                        try {
                            etag = await putPart(part.url, blob, (loaded) => (entry.progress = Math.min(99, Math.round(((sent + loaded) / entry.size) * 100))));
                        } catch (error) {
                            if (attempt === 3) {
                                throw error;
                            }
                            await new Promise((resume) => setTimeout(resume, 1500 * attempt));
                        }
                    }

                    sent += blob.size;
                    parts.push({ number: part.number, etag });
                }

                media = await json(config.completeUrl.replace('__MEDIA__', media.uuid), { method: 'POST', body: JSON.stringify({ parts }) });
            }

            entry.progress = 100;
            entry.status = media.status;
            entry.error = media.rejection;
        } catch (error) {
            entry.status = 'failed';
            entry.error = error.message;

            // Les morceaux déjà déposés sont conservés : « Réessayer » ne renvoie que les manquants.
            if (entry.media) {
                entry.media = await json(config.statusUrl.replace('__MEDIA__', entry.media.uuid)).catch(() => entry.media);
            }
        }
    },

    sizeLabel(bytes) {
        return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
    },
}));

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
