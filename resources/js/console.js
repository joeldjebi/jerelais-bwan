/**
 * Console REFLET TV et back-office coordonnateur.
 * Livewire + Alpine chargés depuis le bundle pour enregistrer nos composants avant le démarrage.
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './theme';

const normalize = (value) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

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

Livewire.start();
