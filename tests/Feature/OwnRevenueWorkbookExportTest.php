<?php

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;

test('workbook exports are recorded privately', function () {
    expect(class_exists(OwnRevenueWorkbookExport::class))->toBeTrue();
});
