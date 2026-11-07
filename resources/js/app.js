import './motion';
import './theme';

/**
 * Temps réel (Laravel Echo + Reverb) : chargé uniquement sur les pages qui le déclarent
 * avec <body data-realtime>, pour ne pas ouvrir de connexion inutile.
 */
if (document.body?.hasAttribute('data-realtime')) {
    import('./echo');
}
