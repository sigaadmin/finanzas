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
