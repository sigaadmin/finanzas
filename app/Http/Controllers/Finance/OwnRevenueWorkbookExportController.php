<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OwnRevenueWorkbookExportController extends Controller
{
    public function download(OwnRevenueWorkbookExport $workbookExport): Response
    {
        Gate::authorize('view', $workbookExport->initialBudget->budget);

        return Storage::disk($workbookExport->storage_disk)->download(
            $workbookExport->storage_path,
            $workbookExport->file_name,
        );
    }
}
