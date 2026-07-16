import assert from 'node:assert/strict';
import test from 'node:test';
import {
    planningDetailQuery,
    planningPageQuery,
    planningSectionQuery,
    planningVersionQuery,
} from '../../resources/js/components/finance/own-revenue/planning/planning-state.js';

test('changing planning sections stays in the same page and clears row-specific state', () => {
    assert.deepEqual(
        planningSectionQuery(
            '/finance/own-revenue/budgets/7/planning?section=technical&page=3&detail_id=12&proposal_version=2',
            'fuel',
        ),
        { section: 'fuel', proposal_version: '2' },
    );
});

test('pagination keeps the active section and selected version', () => {
    assert.deepEqual(
        planningPageQuery(
            '/finance/own-revenue/budgets/7/planning?section=travel&proposal_version=4',
            3,
        ),
        { section: 'travel', proposal_version: '4', page: '3' },
    );
});

test('opening a detail keeps section page and version in the current window', () => {
    assert.deepEqual(
        planningDetailQuery(
            '/finance/own-revenue/budgets/7/planning?section=fuel&page=2&proposal_version=4',
            81,
        ),
        { section: 'fuel', page: '2', proposal_version: '4', detail_id: '81' },
    );
});

test('selecting another version resets pagination and detail but keeps the section', () => {
    assert.deepEqual(
        planningVersionQuery(
            '/finance/own-revenue/budgets/7/planning?section=technical&page=2&detail_id=8',
            6,
        ),
        { section: 'technical', proposal_version: '6' },
    );
});
