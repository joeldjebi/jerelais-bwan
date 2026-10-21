/**
 * Test de charge JE RELAIS — pic × 10 (étape 6).
 *
 * Hypothèse de pic nominal (à confirmer avec REFLET TV, paramétrable) : un événement majeur pendant le
 * journal du soir, PEAK_RPS requêtes/s réparties entre l'application des Relais (70 %), le site public (20 %)
 * et la console (10 %, non couverte ici : sessions 2FA). Le test applique MULTIPLIER × ce pic.
 *
 * Préparation :  php artisan jerelais:load-test-accounts 50
 * Lancement :    docker run --rm -i --add-host=host.docker.internal:host-gateway \
 *                  -v "$PWD/tests/Load:/scripts" -v "$PWD/storage/app/private/k6:/data" \
 *                  -e BASE_URL=http://host.docker.internal:8000 -e PEAK_RPS=2 -e MULTIPLIER=10 \
 *                  grafana/k6:0.54.0 run /scripts/pic-10x.js
 */
import http from 'k6/http';
import { check, group } from 'k6';
import { SharedArray } from 'k6/data';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const PEAK_RPS = Number(__ENV.PEAK_RPS || 2);
const MULTIPLIER = Number(__ENV.MULTIPLIER || 10);
const DURATION = __ENV.DURATION || '2m';
const TARGET = PEAK_RPS * MULTIPLIER;

const accounts = new SharedArray('jetons', () => [JSON.parse(open(__ENV.TOKENS_FILE || '/data/tokens.json'))]);

export const options = {
    discardResponseBodies: false,
    scenarios: {
        application: {
            executor: 'ramping-arrival-rate',
            exec: 'application',
            startRate: 1,
            timeUnit: '1s',
            preAllocatedVUs: 20,
            maxVUs: 200,
            stages: [
                { target: Math.max(1, Math.round(TARGET * 0.7)), duration: '30s' },
                { target: Math.max(1, Math.round(TARGET * 0.7)), duration: DURATION },
                { target: 0, duration: '15s' },
            ],
        },
        site: {
            executor: 'constant-arrival-rate',
            exec: 'site',
            rate: Math.max(1, Math.round(TARGET * 0.2)),
            timeUnit: '1s',
            duration: DURATION,
            startTime: '30s',
            preAllocatedVUs: 10,
            maxVUs: 100,
        },
    },
    thresholds: {
        // Objectifs de service retenus pour la recette (§10) : 95 % des requêtes sous 800 ms, < 1 % d'erreurs.
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<800'],
        'http_req_duration{scenario:application}': ['p(95)<800'],
        'http_req_duration{scenario:site}': ['p(95)<600'],
    },
};

/** Vérifie le statut attendu et journalise les écarts (code HTTP, erreur réseau) pour l'analyse. */
function expectStatus(response, status, name) {
    const ok = check(response, { [name]: (r) => r.status === status });

    if (!ok) {
        console.warn(`${name} : HTTP ${response.status} ${response.error || ''}`);
    }
}

const json = (token) => ({ headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' } });

export function application() {
    const { tokens, commune } = accounts[0];
    const params = json(tokens[Math.floor(Math.random() * tokens.length)]);
    const roll = Math.random();

    group('application', () => {
        if (roll < 0.35) {
            expectStatus(http.get(`${BASE_URL}/api/v1/relais`, params), 200, 'mes relais');
        } else if (roll < 0.55) {
            expectStatus(http.get(`${BASE_URL}/api/v1/appels`, params), 200, 'appels');
        } else if (roll < 0.70) {
            expectStatus(http.get(`${BASE_URL}/api/v1/me/stats`, params), 200, 'statistiques');
        } else if (roll < 0.80) {
            expectStatus(http.get(`${BASE_URL}/api/v1/conversations`, params), 200, 'messagerie');
        } else if (roll < 0.90) {
            expectStatus(http.get(`${BASE_URL}/api/v1/settings`, params), 200, 'paramètres');
        } else {
            // Brouillon de relais (écriture en base) ; l'envoi des médias se mesure à part, sur le stockage.
            const body = JSON.stringify({
                client_uuid: uuidv4(),
                urgency: 'normal',
                title: 'Test de charge',
                declared_commune: commune,
                location: { lat: 5.34, lng: -4.07, accuracy_m: 15 },
                device_captured_at: new Date().toISOString(),
                protections: { has_minors: false, people_consent: 'not_applicable', credit_mode: 'anonymous' },
            });
            expectStatus(http.post(`${BASE_URL}/api/v1/relais`, body, params), 201, 'brouillon');
        }
    });
}

export function site() {
    const pages = ['/', '/vu-sur-reflet-tv', '/reseau', '/devenir-relais'];
    const page = pages[Math.floor(Math.random() * pages.length)];

    expectStatus(http.get(`${BASE_URL}${page}`), 200, 'page publique');
}
