import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { canSubmitLocalDataReset } from '../../resources/js/components/settings/local-data-reset-state.js';

const page = readFileSync(
    new URL(
        '../../resources/js/pages/settings/local-data.tsx',
        import.meta.url,
    ),
    'utf8',
);
const card = readFileSync(
    new URL(
        '../../resources/js/components/settings/local-data-reset-card.tsx',
        import.meta.url,
    ),
    'utf8',
);
const settingsLayout = readFileSync(
    new URL('../../resources/js/layouts/settings/layout.tsx', import.meta.url),
    'utf8',
);

test('enables reset only for the exact confirmation phrase while idle', () => {
    assert.equal(
        canSubmitLocalDataReset('BORRAR U300', 'BORRAR U300', false),
        true,
    );
    assert.equal(
        canSubmitLocalDataReset('borrar u300', 'BORRAR U300', false),
        false,
    );
    assert.equal(
        canSubmitLocalDataReset('BORRAR U300 ', 'BORRAR U300', false),
        false,
    );
    assert.equal(
        canSubmitLocalDataReset('BORRAR U300', 'BORRAR U300', true),
        false,
    );
});

test('local data page uses operational language and a confirmation dialog', () => {
    assert.match(page, /Datos locales/);
    assert.match(page, /Reinicia únicamente los datos de prueba/);
    assert.match(card, /Escriba la frase exactamente como aparece/);
    assert.match(card, /Dialog/);
    assert.match(card, /Reiniciando…/);

    for (const technicalTerm of [
        'DROP TABLE',
        'truncate',
        'parser',
        'own_revenue_',
        'u300_programs',
    ]) {
        assert.doesNotMatch(`${page}\n${card}`, new RegExp(technicalTerm, 'i'));
    }
});

test('settings navigation exposes local data only through the shared availability flag', () => {
    assert.match(settingsLayout, /localDataResetAvailable/);
    assert.match(settingsLayout, /Datos locales/);
    assert.match(settingsLayout, /@\/routes\/local-data/);
});
