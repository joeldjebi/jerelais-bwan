/**
 * Thème clair / sombre : suit le système par défaut, mémorise le choix de
 * l'utilisateur (simple préférence d'affichage, aucune donnée personnelle).
 */

const STORAGE_KEY = 'jerelais.theme';

function storedTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export function applyTheme(theme = storedTheme()) {
    const dark = theme ? theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
}

window.toggleTheme = () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // Stockage indisponible (navigation privée) : le choix vaut pour la page.
    }

    applyTheme(next);
};

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme());
