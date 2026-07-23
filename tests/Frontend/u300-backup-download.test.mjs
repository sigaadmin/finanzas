import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL(
        '../../resources/js/pages/finance/u300/programs/index.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('U300 backup uses a native download link instead of an Inertia visit', () => {
    const backupSection = page.slice(
        page.indexOf('Respaldar') - 350,
        page.indexOf('Respaldar') + 100,
    );

    assert.match(backupSection, /<a[\s\S]*href=/);
    assert.doesNotMatch(backupSection, /<Link/);
});

test('U300 restore submits the current preview token and displays form errors', () => {
    const restoreSection = page.slice(
        page.indexOf('{restore_preview ? ('),
        page.indexOf(') : (', page.indexOf('{restore_preview ? (')),
    );

    assert.match(restoreSection, /preview_token:\s*restore_preview\.token/);
    assert.match(
        restoreSection,
        /<InputError\s+message=\{\s*restore\.errors\.preview_token\s*\}\s*\/>/,
    );
});
