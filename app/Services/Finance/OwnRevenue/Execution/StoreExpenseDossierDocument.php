<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierDocumentStage;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreExpenseDossierDocument
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    private const CLIENT_EXTENSIONS = ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'xlsx'];

    public function handle(
        OwnRevenueExpenseDossier $dossier,
        User $user,
        UploadedFile $file,
        OwnRevenueExpenseDossierDocumentStage $stage,
    ): OwnRevenueExpenseDossierDocument {
        $mimeType = (string) $file->getMimeType();
        $clientExtension = strtolower($file->getClientOriginalExtension());
        if (! $file->isValid()
            || $file->getSize() > 10 * 1024 * 1024
            || ! isset(self::MIME_EXTENSIONS[$mimeType])
            || ! in_array($clientExtension, self::CLIENT_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'evidence' => 'La evidencia debe ser PDF, XML, imagen o XLSX y no exceder 10 MB.',
            ]);
        }

        $extension = self::MIME_EXTENSIONS[$mimeType];
        $path = $file->storeAs(
            "finance/own-revenue/{$dossier->own_revenue_budget_id}/expense-dossiers/{$dossier->id}",
            Str::uuid().".{$extension}",
            'local',
        );
        if (! is_string($path)) {
            throw ValidationException::withMessages(['evidence' => 'No fue posible guardar la evidencia.']);
        }

        return $dossier->documents()->create([
            'stage' => $stage,
            'original_name' => $file->getClientOriginalName(),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
    }
}
