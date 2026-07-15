function queryFromUrl(currentUrl) {
    return new URLSearchParams(currentUrl.split('?')[1] ?? '');
}

function queryObject(query) {
    return Object.fromEntries(query.entries());
}

export function planningSectionQuery(currentUrl, section) {
    const query = queryFromUrl(currentUrl);
    query.set('section', section);
    query.delete('page');
    query.delete('detail_id');

    return queryObject(query);
}

export function planningPageQuery(currentUrl, page) {
    const query = queryFromUrl(currentUrl);
    query.set('page', String(page));
    query.delete('detail_id');

    return queryObject(query);
}

export function planningDetailQuery(currentUrl, detailId) {
    const query = queryFromUrl(currentUrl);
    query.set('detail_id', String(detailId));

    return queryObject(query);
}

export function planningVersionQuery(currentUrl, version) {
    const query = queryFromUrl(currentUrl);
    query.set('proposal_version', String(version));
    query.delete('page');
    query.delete('detail_id');

    return queryObject(query);
}
