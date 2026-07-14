import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatCents,
    previewStateMessage,
    workSheetDecisionFeedback,
} from '../../resources/js/components/finance/own-revenue/imports/work-sheet-preview-state.js';

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
