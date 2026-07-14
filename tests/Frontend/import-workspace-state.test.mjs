import assert from 'node:assert/strict';
import test from 'node:test';
import {
    failImportMutation,
    finishImportMutation,
    importIssueDialogState,
    importIssueContextDetails,
    importIssueDialogOpenAction,
    importIssuePageQuery,
    importFilePresentation,
    importDecisionRememberKey,
    initialImportMutation,
    resolveFailedUpload,
    selectImportFileQuery,
    startImportMutation,
    takeNextUpload,
} from '../../resources/js/components/finance/own-revenue/imports/import-workspace-state.js';

test('issue details expose only explicitly labeled business context', () => {
    assert.deepEqual(
        importIssueContextDetails({
            sheet_name: 'HOJA FINAL',
            row_number: 12,
            activity_code: 'A03-A01',
            specific_item_code: '21101',
            source_region: '01-002',
            normalized_region: '02-001',
            difference_cents: '-250',
            requires_reanalysis: true,
            token: 'secret',
            source_payload: { raw: true },
            unknown_variable: 'never render',
        }),
        [
            { label: 'Hoja', value: 'HOJA FINAL' },
            { label: 'Renglón', value: '12' },
            { label: 'Actividad', value: 'A03-A01' },
            { label: 'Partida', value: '21101' },
            { label: 'Región original', value: '01-002' },
            { label: 'Región asignada', value: '02-001' },
            { label: 'Diferencia', value: '-$2.50' },
            { label: 'Acción necesaria', value: 'Volver a analizar' },
        ],
    );
});

test('file statuses use operational language and expose only available ABPRE actions', () => {
    const expectedLabels = {
        uploaded: 'Listo para analizar',
        analyzing: 'Analizando',
        needs_correction: 'Requiere atención',
        ready: 'Listo para revisar',
        confirmed: 'Confirmado',
        failed: 'No se pudo analizar',
        parser_pending: 'Revisión no disponible',
        replaced: 'Reemplazado por otra versión',
        discarded: 'Descartado',
    };

    for (const [status, label] of Object.entries(expectedLabels)) {
        const presentation = importFilePresentation({
            status,
            format: status === 'parser_pending' ? 'fuel' : 'abpre',
            analyzed: ['ready', 'confirmed', 'needs_correction'].includes(
                status,
            ),
            issueCount: status === 'needs_correction' ? 2 : 0,
        });

        assert.equal(presentation.label, label);
        assert.doesNotMatch(presentation.label, /parser/i);
    }

    assert.deepEqual(
        importFilePresentation({
            status: 'uploaded',
            format: 'abpre',
            analyzed: false,
            issueCount: 0,
        }),
        {
            label: 'Listo para analizar',
            canAnalyze: true,
            canViewIssues: false,
            canViewPreview: false,
        },
    );
    assert.equal(
        importFilePresentation({
            status: 'ready',
            format: 'abpre',
            analyzed: true,
            issueCount: 0,
        }).canViewIssues,
        true,
        'analyzed files keep their empty issue report available',
    );
    assert.deepEqual(
        importFilePresentation({
            status: 'needs_correction',
            format: 'abpre',
            analyzed: true,
            issueCount: 2,
        }),
        {
            label: 'Requiere atención',
            canAnalyze: true,
            canViewIssues: true,
            canViewPreview: true,
        },
    );
    assert.deepEqual(
        importFilePresentation({
            status: 'parser_pending',
            format: 'fuel',
            analyzed: false,
            issueCount: 0,
        }),
        {
            label: 'Revisión no disponible',
            canAnalyze: false,
            canViewIssues: false,
            canViewPreview: false,
        },
    );
});

test('work sheet files expose analysis and preview actions with operational language', () => {
    assert.deepEqual(
        importFilePresentation({
            status: 'parser_pending',
            format: 'work_sheet',
            analyzed: false,
            issueCount: 0,
            canReclassify: false,
        }),
        {
            label: 'Listo para analizar',
            canAnalyze: true,
            canViewIssues: false,
            canViewPreview: false,
        },
    );
    assert.deepEqual(
        importFilePresentation({
            status: 'ready',
            format: 'work_sheet',
            analyzed: true,
            issueCount: 1,
            canReclassify: false,
        }),
        {
            label: 'Listo para confirmar',
            canAnalyze: false,
            canViewIssues: true,
            canViewPreview: true,
        },
    );
    assert.equal(
        importFilePresentation({
            status: 'needs_correction',
            format: 'work_sheet',
            analyzed: true,
            issueCount: 1,
            canReclassify: false,
        }).label,
        'Requiere revisión',
    );
    assert.equal(
        importFilePresentation({
            status: 'parser_pending',
            format: 'abpre',
            analyzed: false,
            issueCount: 0,
            canReclassify: false,
        }).canAnalyze,
        false,
        'ABPRE keeps its previous analysis action matrix',
    );
});

test('unclassified uploads stay pending classification without changing ABPRE language', () => {
    assert.equal(
        importFilePresentation({
            status: 'uploaded',
            format: null,
            analyzed: false,
            issueCount: 0,
            canReclassify: true,
        }).label,
        'Pendiente de clasificar',
    );
    assert.equal(
        importFilePresentation({
            status: 'uploaded',
            format: 'abpre',
            analyzed: false,
            issueCount: 0,
            canReclassify: false,
        }).label,
        'Listo para analizar',
    );
    assert.equal(
        importFilePresentation({
            status: 'discarded',
            format: null,
            analyzed: false,
            issueCount: 0,
            canReclassify: false,
        }).label,
        'Descartado',
    );
});

test('issue dialog queries preserve context while file changes clear stale pages', () => {
    assert.deepEqual(
        importIssuePageQuery(
            '/imports?unassigned_page=2&abpre_versions_page=3&filter=active',
            41,
            4,
        ),
        {
            unassigned_page: '2',
            abpre_versions_page: '3',
            filter: 'active',
            import_file_id: '41',
            issues_page: '4',
        },
    );

    assert.deepEqual(
        selectImportFileQuery(
            '/imports?filter=active&import_file_id=41&issues_page=4&preview_page=2&decisions_page=3',
            99,
        ),
        {
            filter: 'active',
            import_file_id: '99',
        },
    );
});

test('issue dialog may close and reopen for an already selected file', () => {
    assert.deepEqual(importIssueDialogOpenAction(41, 41), {
        isOpen: true,
        shouldLoad: false,
    });
    assert.deepEqual(importIssueDialogOpenAction(null, 41), {
        isOpen: false,
        shouldLoad: true,
    });

    const opened = importIssueDialogState(undefined, true);
    const closed = importIssueDialogState(opened, false);
    const reopened = importIssueDialogState(closed, true);

    assert.deepEqual(opened, { isOpen: true });
    assert.deepEqual(closed, { isOpen: false });
    assert.deepEqual(reopened, { isOpen: true });
});

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
