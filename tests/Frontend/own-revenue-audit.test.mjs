import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/audit/index.tsx',
    import.meta.url,
);
const budgetPagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/budgets/show.tsx',
    import.meta.url,
);

test('consolidated audit is read only and filters in the same window', () => {
    const page = readFileSync(pagePath, 'utf8');
    const budgetPage = readFileSync(budgetPagePath, 'utf8');

    assert.match(page, /router\.get/);
    assert.match(page, /preserveState: true/);
    assert.match(page, /replace: true/);
    assert.match(page, /timeline\.options/);
    assert.match(page, /timeline\.events/);
    assert.match(page, /Historial consolidado/);
    assert.match(budgetPage, /Consultar historial/);
    assert.doesNotMatch(page, /target=["']_blank/);
    assert.doesNotMatch(budgetPage, /target=["']_blank/);
    assert.doesNotMatch(page, /useForm|method=["']post/);
});

test('audit presents operational event details and an empty state', () => {
    const page = readFileSync(pagePath, 'utf8');

    assert.match(page, /event\.title/);
    assert.match(page, /event\.description/);
    assert.match(page, /event\.actor_name/);
    assert.match(page, /event\.reference/);
    assert.match(page, /No hay movimientos para el filtro seleccionado/);
});
