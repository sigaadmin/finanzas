import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/reports/show.tsx',
    import.meta.url,
);
const budgetPagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/budgets/show.tsx',
    import.meta.url,
);

test('internal reports stay read only and preserve filters in the same window', () => {
    const page = readFileSync(pagePath, 'utf8');
    const budgetPage = readFileSync(budgetPagePath, 'utf8');

    for (const label of [
        'Inicial',
        'Modificado',
        'Reservado',
        'Comprometido',
        'Pagado',
        'Disponible',
    ]) {
        assert.match(page, new RegExp(label));
    }

    assert.match(page, /router\.get/);
    assert.doesNotMatch(page, /target=["']_blank/);
    assert.doesNotMatch(page, /method=["']post/);
    assert.match(budgetPage, /Abrir reportes/);
    assert.doesNotMatch(budgetPage, /target=["']_blank/);
});

test('internal report amounts use BigInt and operational empty states', () => {
    const page = readFileSync(pagePath, 'utf8');

    assert.match(page, /BigInt/);
    assert.match(page, /Aún no hay presupuesto inicial autorizado/);
    assert.match(page, /No hay movimientos para los filtros seleccionados/);
    assert.match(page, /no cambia\s+con los filtros\s+presupuestales/i);
});
