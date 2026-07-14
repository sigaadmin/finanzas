import assert from 'node:assert/strict';
import test from 'node:test';
import {
    failImportMutation,
    finishImportMutation,
    importDecisionRememberKey,
    initialImportMutation,
    resolveFailedUpload,
    startImportMutation,
    takeNextUpload,
} from '../../resources/js/components/finance/own-revenue/imports/import-workspace-state.ts';

test('mutation feedback clears stale errors and exposes validation failures', () => {
    const started = startImportMutation(
        { activeFileId: null, error: 'Error anterior' },
        42,
    );

    assert.deepEqual(started, { activeFileId: 42, error: null });
    assert.deepEqual(
        failImportMutation(started, {
            format: 'El estado cambió mientras se procesaba.',
        }),
        {
            activeFileId: 42,
            error: 'El estado cambió mientras se procesaba.',
        },
    );
    assert.deepEqual(finishImportMutation({ ...started, error: 'Visible' }), {
        activeFileId: null,
        error: 'Visible',
    });
    assert.deepEqual(initialImportMutation, {
        activeFileId: null,
        error: null,
    });
});

test('duplicate retry resolves its stale failure without removing other files', () => {
    const duplicate = { name: 'duplicado.xlsx', size: 100, lastModified: 10 };
    const other = { name: 'otro.xlsx', size: 200, lastModified: 20 };

    assert.deepEqual(
        resolveFailedUpload(
            [
                { file: duplicate, message: 'Ya fue cargado.' },
                { file: other, message: 'Formato inválido.' },
            ],
            duplicate,
        ),
        [{ file: other, message: 'Formato inválido.' }],
    );
});

test('a failed queue item does not prevent the next item from being selected', () => {
    const first = { file: 'uno.xlsx', forceReanalysis: false };
    const second = { file: 'dos.xlsx', forceReanalysis: false };
    const firstStep = takeNextUpload([first, second]);
    const secondStep = takeNextUpload(firstStep.remaining);

    assert.equal(firstStep.current, first);
    assert.deepEqual(firstStep.remaining, [second]);
    assert.equal(secondStep.current, second);
    assert.deepEqual(secondStep.remaining, []);
});

test('decision persistence is isolated by file and analysis revision', () => {
    assert.notEqual(
        importDecisionRememberKey({
            id: 7,
            analyzed_at: '2026-07-14T10:00:00Z',
        }),
        importDecisionRememberKey({
            id: 7,
            analyzed_at: '2026-07-14T11:00:00Z',
        }),
    );
    assert.equal(
        importDecisionRememberKey({
            id: 7,
            analyzed_at: '2026-07-14T10:00:00Z',
        }),
        importDecisionRememberKey({
            id: 7,
            analyzed_at: '2026-07-14T10:00:00Z',
        }),
    );
});
