<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnRevenueExpenseDossierDocumentController extends Controller
{
    public function __invoke(OwnRevenueExpenseDossierDocument $expenseDossierDocument): StreamedResponse
    {
        Gate::authorize('view', $expenseDossierDocument->dossier->budget);
        $disk = Storage::disk($expenseDossierDocument->storage_disk);
        abort_unless($disk->exists($expenseDossierDocument->storage_path), 404);

        return $disk->download($expenseDossierDocument->storage_path, $expenseDossierDocument->original_name);
    }
}
