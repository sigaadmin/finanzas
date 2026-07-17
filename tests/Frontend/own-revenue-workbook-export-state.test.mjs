import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL(
        '../../resources/js/pages/finance/own-revenue/planning/show.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('planning offers all five authorized workbook formats', () => {
    for (const format of [
        'abpre',
        'work_sheet',
        'technical_sheet',
        'fuel',
        'travel_expenses',
    ]) {
        assert.match(page, new RegExp(`${format}:`));
    }
});

test('workbook downloads stay in the current window', () => {
    assert.match(
        page,
        /workbookExports\.download\([\s\n]*item\.id,[\s\n]*\)\.url/,
    );
    assert.doesNotMatch(page, /target=["']_blank["']/);
});
