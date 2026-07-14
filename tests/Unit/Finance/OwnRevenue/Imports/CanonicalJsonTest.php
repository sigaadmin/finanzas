<?php

use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;

test('canonical JSON hashes associative payloads independently of key order while preserving list order', function () {
    $canonicalJson = new CanonicalJson;
    $first = [
        'activity' => ['name' => 'Investigación', 'code' => 'A03-A01'],
        'months' => [1 => '100', 2 => '200'],
    ];
    $sameMeaning = [
        'months' => [1 => '100', 2 => '200'],
        'activity' => ['code' => 'A03-A01', 'name' => 'Investigación'],
    ];
    $changedList = [
        'months' => [1 => '200', 2 => '100'],
        'activity' => ['code' => 'A03-A01', 'name' => 'Investigación'],
    ];

    expect($canonicalJson->hash($first))->toBe($canonicalJson->hash($sameMeaning))
        ->and($canonicalJson->hash($first))->not->toBe($canonicalJson->hash($changedList));
});
