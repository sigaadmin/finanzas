import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL(
        '../../resources/js/pages/finance/u300/programs/technical-sheet-line.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('flattens transparent photo crops onto a white background before JPEG encoding', () => {
    assert.match(
        page,
        /canvas\.width = cropWidth;\s+canvas\.height = cropHeight;\s+context\.fillStyle = '#fff';\s+context\.fillRect\(0, 0, cropWidth, cropHeight\);\s+context\.drawImage\(/,
    );
});
