import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    canConfirmWorkSheet,
    canManageWorkSheetDecision,
    formatCents,
    previewPageQuery,
    previewStateMessage,
    workSheetPreviewBadge,
    workSheetConfirmationFeedback,
    workSheetDecisionFeedback,
} from '../../resources/js/components/finance/own-revenue/imports/work-sheet-preview-state.js';

test('work sheet preview badge reflects workflow state before permissions', () => {
    assert.equal(
        workSheetPreviewBadge({
            status: 'confirmed',
            viewState: 'confirmed',
            canManage: false,
            canConfirm: false,
        }),
        'Confirmado',
    );
    assert.equal(
        workSheetPreviewBadge({
            status: 'ready',
            viewState: 'ready',
            canManage: false,
            canConfirm: false,
        }),
        'Sólo consulta',
    );
    assert.equal(
        workSheetPreviewBadge({
            status: 'ready',
            viewState: 'ready',
            canManage: true,
            canConfirm: true,
        }),
        'Listo para confirmar',
    );
    assert.equal(
        workSheetPreviewBadge({
            status: 'ready',
            viewState: 'ready',
            canManage: true,
            canConfirm: false,
        }),
        'Pendiente de revisión',
    );
    assert.equal(
        workSheetPreviewBadge({
            status: 'analyzing',
            viewState: 'analyzing',
            canManage: true,
            canConfirm: false,
        }),
        'Analizando',
    );
});

test('confirmation controls require management access current eligibility and a revision', () => {
    assert.equal(
        canConfirmWorkSheet({
            canManage: true,
            canConfirm: true,
            analysisRevision: '7f65c28d-a6ef-4c49-ae75-673bbec87bec',
        }),
        true,
    );
    assert.equal(
        canConfirmWorkSheet({
            canManage: false,
            canConfirm: true,
            analysisRevision: '7f65c28d-a6ef-4c49-ae75-673bbec87bec',
        }),
        false,
    );
    assert.equal(
        canConfirmWorkSheet({
            canManage: true,
            canConfirm: false,
            analysisRevision: '7f65c28d-a6ef-4c49-ae75-673bbec87bec',
        }),
        false,
    );
    assert.equal(
        canConfirmWorkSheet({
            canManage: true,
            canConfirm: true,
            analysisRevision: null,
        }),
        false,
    );
});

test('work sheet amounts remain exact beyond JavaScript safe integers', () => {
    assert.equal(formatCents('9007199254740993'), '$90,071,992,547,409.93');
    assert.equal(formatCents('-2'), '-$0.02');
    assert.equal(formatCents('0'), '$0.00');
});

test('preview states use operational language', () => {
    assert.equal(
        previewStateMessage('not_analyzed'),
        'Analiza este archivo para consultar sus renglones.',
    );
    assert.equal(
        previewStateMessage('failed'),
        'No fue posible preparar la vista previa. Vuelve a analizar el archivo desde Importaciones.',
    );
    assert.equal(
        previewStateMessage('empty'),
        'El análisis no encontró renglones que puedan mostrarse.',
    );
    assert.equal(
        previewStateMessage('abpre_changed'),
        'El ABPRE cambió; vuelve a analizar la Hoja de trabajo antes de tomar decisiones.',
    );
    assert.doesNotMatch(previewStateMessage('failed'), /parser|payload|token/i);
});

test('stale decision errors explain the next action without technical fields', () => {
    assert.equal(
        workSheetDecisionFeedback({
            analysis_revision: 'El análisis cambió.',
        }),
        'La revisión cambió mientras trabajabas. Actualiza la página y revisa nuevamente las diferencias.',
    );
    assert.equal(
        workSheetDecisionFeedback({
            file: 'El presupuesto cambió.',
        }),
        'La información del presupuesto cambió. Vuelve a analizar el archivo antes de decidir.',
    );
});

test('confirmation errors explain stale analysis and ordinary validation failures', () => {
    assert.equal(
        workSheetConfirmationFeedback({
            analysis_revision: 'El análisis cambió.',
        }),
        'La revisión cambió mientras confirmabas. Actualiza la página y revisa nuevamente la Hoja de trabajo.',
    );
    assert.equal(
        workSheetConfirmationFeedback({ file: 'No está lista.' }),
        'No está lista.',
    );
});

test('decision controls require management permission and a current ABPRE review', () => {
    assert.equal(
        canManageWorkSheetDecision({
            canManage: true,
            decisionsEnabled: true,
            requiresDecision: true,
        }),
        true,
    );
    assert.equal(
        canManageWorkSheetDecision({
            canManage: true,
            decisionsEnabled: false,
            requiresDecision: true,
        }),
        false,
    );
    assert.equal(
        canManageWorkSheetDecision({
            canManage: false,
            decisionsEnabled: true,
            requiresDecision: true,
        }),
        false,
    );
});

test('all preview paginations preserve the rest of the query', () => {
    const current = '/preview?preview_page=2&blocking_page=3&review_page=4';

    assert.deepEqual(previewPageQuery(current, 'preview_page', 5), {
        preview_page: '5',
        blocking_page: '3',
        review_page: '4',
    });
    assert.deepEqual(previewPageQuery(current, 'blocking_page', 6), {
        preview_page: '2',
        blocking_page: '6',
        review_page: '4',
    });
    assert.deepEqual(previewPageQuery(current, 'review_page', 7), {
        preview_page: '2',
        blocking_page: '3',
        review_page: '7',
    });
});

test('work sheet table and decision buttons expose accessible labels', () => {
    const component = readFileSync(
        new URL(
            '../../resources/js/components/finance/own-revenue/imports/work-sheet-preview.tsx',
            import.meta.url,
        ),
        'utf8',
    );

    assert.match(component, /<caption[^>]*className="sr-only"/);
    assert.match(component, /aria-label=\{`Aceptar diferencia/);
    assert.match(component, /aria-label=\{`No aceptar diferencia/);
});
