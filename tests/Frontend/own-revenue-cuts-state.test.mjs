import assert from 'node:assert/strict';
import test from 'node:test';
import {
    cutFiltersQuery,
    visibleCutCandidates,
} from '../../resources/js/components/finance/own-revenue/planning/cuts-state.js';

const candidates = [
    { stable_key: 'technical:a', format: 'Ficha técnica', activity_code: 'A01', specific_item_code: '21101', month: 5 },
    { stable_key: 'fuel:b', format: 'Combustible', activity_code: 'A02', specific_item_code: '26101', month: 4 },
];

test('cut filters combine format activity item and month without technical field names', () => {
    assert.deepEqual(visibleCutCandidates(candidates, {
        format: 'Combustible', activity: 'A02', item: '26101', month: '4',
    }), [candidates[1]]);
});

test('cut filter navigation stays in the same window and clears empty values', () => {
    assert.deepEqual(cutFiltersQuery('/finance/own-revenue/budgets/1/proposals/1/cuts?format=Ficha%20t%C3%A9cnica&month=5', {
        format: '', activity: 'A01', item: '', month: '',
    }), { activity: 'A01' });
});
