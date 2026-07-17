<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierDocumentStage;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequestExpensePayment
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/xml',
        'text/xml',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    private const ALLOWED_CLIENT_EXTENSIONS = ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'xlsx'];

    /** @param list<UploadedFile> $documents */
    public function handle(
        OwnRevenueExpenseDossier $dossier,
        User $user,
        string $reference,
        array $documents,
    ): OwnRevenueExpenseDossier {
        Gate::forUser($user)->authorize('manageExpensePurchase', $dossier->budget);
        $reference = trim($reference);
        $this->validateInput($reference, $documents);
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($dossier, $user, $reference, $documents, &$storedPaths): OwnRevenueExpenseDossier {
                $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
                Gate::forUser($user)->authorize('manageExpensePurchase', $lockedBudget);
                $lockedDossier = OwnRevenueExpenseDossier::query()
                    ->whereBelongsTo($lockedBudget, 'budget')
                    ->whereKey($dossier->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::PurchaseInProgress) {
                    throw ValidationException::withMessages([
                        'status' => 'La compra debe estar en proceso antes de solicitar el pago.',
                    ]);
                }

                foreach ($documents as $document) {
                    $mimeType = (string) $document->getMimeType();
                    $extension = self::MIME_EXTENSIONS[$mimeType];
                    $path = $document->storeAs(
                        "finance/own-revenue/{$lockedBudget->id}/expense-dossiers/{$lockedDossier->id}",
                        Str::uuid().".{$extension}",
                        'local',
                    );
                    if (! is_string($path)) {
                        throw ValidationException::withMessages(['documents' => 'No fue posible guardar uno de los documentos.']);
                    }
                    $storedPaths[] = $path;
                    $lockedDossier->documents()->create([
                        'stage' => OwnRevenueExpenseDossierDocumentStage::PaymentRequest,
                        'original_name' => $document->getClientOriginalName(),
                        'storage_disk' => 'local',
                        'storage_path' => $path,
                        'mime_type' => $mimeType,
                        'size_bytes' => (int) $document->getSize(),
                        'uploaded_by' => $user->id,
                        'uploaded_at' => now(),
                    ]);
                }

                $lockedDossier->update([
                    'status' => OwnRevenueExpenseDossierStatus::PaymentRequested,
                    'payment_request_reference' => $reference,
                    'payment_requested_by' => $user->id,
                    'payment_requested_at' => now(),
                ]);
                $lockedDossier->transitions()->create([
                    'from_status' => OwnRevenueExpenseDossierStatus::PurchaseInProgress,
                    'to_status' => OwnRevenueExpenseDossierStatus::PaymentRequested,
                    'actor_id' => $user->id,
                    'occurred_at' => now(),
                ]);

                return $lockedDossier;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    /** @param list<UploadedFile> $documents */
    private function validateInput(string $reference, array $documents): void
    {
        if ($reference === '') {
            throw ValidationException::withMessages(['payment_request_reference' => 'Captura la referencia de la solicitud de pago.']);
        }
        if ($documents === []) {
            throw ValidationException::withMessages(['documents' => 'Adjunta al menos un documento para solicitar el pago.']);
        }
        foreach ($documents as $document) {
            $extension = strtolower($document->getClientOriginalExtension());
            if (! $document->isValid()
                || $document->getSize() > 10 * 1024 * 1024
                || ! in_array($document->getMimeType(), self::ALLOWED_MIME_TYPES, true)
                || ! in_array($extension, self::ALLOWED_CLIENT_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    'documents' => 'Los documentos deben ser PDF, XML, imagen o XLSX y no exceder 10 MB cada uno.',
                ]);
            }
        }
    }
}
