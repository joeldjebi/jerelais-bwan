/**
 * Thème clair / sombre / auto : choix mémorisé par l'utilisateur (simple préférence d'affichage).
 * À défaut, le thème par défaut de l'espace s'applique (la console est sombre, façon régie).
 */

const STORAGE_KEY = 'jerelais.theme';

export function storedTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export function applyTheme(theme = storedTheme()) {
    const effective = theme || document.documentElement.dataset.defaultTheme || 'system';
    const dark = effective === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : effective === 'dark';

    document.documentElement.classList.toggle('dark', dark);
}

export function setTheme(theme) {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Stockage indisponible (navigation privée) : le choix vaut pour la page.
    }

    applyTheme(theme);
}

window.toggleTheme = () => setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
window.setTheme = setTheme;

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme());
