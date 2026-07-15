import assert from 'node:assert/strict';
import test from 'node:test';
import {
    openActivityGroup,
    reconciliationStatusLabel,
} from '../../resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js';

test('opens a group while preserving the selected format', () => {
    assert.deepEqual(
        openActivityGroup('/imports/reconciliation?format=fuel', 'abc'),
        { format: 'fuel', group: 'abc' },
    );
});

test('describes reconciliation progress in user-facing language', () => {
    assert.equal(
        reconciliationStatusLabel({ total: 4, pending: 0 }),
        'Actividades conciliadas',
    );
    assert.equal(
        reconciliationStatusLabel({ total: 4, pending: 1 }),
        '1 registro pendiente',
    );
});
