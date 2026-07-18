import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/annual-close/show.tsx',
    import.meta.url,
);
const budgetPagePath = new URL(
    '../../resources/js/pages/finance/own-revenue/budgets/show.tsx',
    import.meta.url,
);

test('annual close requires the exact phrase and a meaningful note', () => {
    const page = readFileSync(pagePath, 'utf8');

    assert.match(page, /useForm/);
    assert.match(page, /form\.post/);
    assert.match(page, /review\.confirmation_phrase/);
    assert.match(
        page,
        /form\.data\.confirmation === review\.confirmation_phrase/,
    );
    assert.match(page, /form\.data\.note\.trim\(\)\.length >= 10/);
    assert.match(page, /review\.eligible/);
    assert.match(page, /permissions\.close/);
    assert.match(page, /Dialog/);
    assert.match(page, /Cierre definitivo/);
    assert.match(page, /form\.errors as Record/);
    assert.match(page, /\.closure/);
});

test('annual close explains blockers and displays the immutable act', () => {
    const page = readFileSync(pagePath, 'utf8');
    const budgetPage = readFileSync(budgetPagePath, 'utf8');

    assert.match(page, /review\.blockers/);
    assert.match(page, /Acta de cierre anual/);
    assert.match(page, /closure\.fingerprint/);
    assert.match(page, /closure\.closed_by\.name/);
    assert.match(page, /Disponible/);
    assert.match(page, /Combustible disponible/);
    assert.match(budgetPage, /Revisar cierre anual/);
    assert.doesNotMatch(page, /target=["']_blank/);
    assert.doesNotMatch(budgetPage, /target=["']_blank/);
    assert.doesNotMatch(page, /reabrir|reopen/i);
});
