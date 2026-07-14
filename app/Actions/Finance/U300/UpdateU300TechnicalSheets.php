<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use App\Models\Finance\U300\U300TechnicalSheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UpdateU300TechnicalSheets
{
    /**
     * @param  list<array{
     *     u300_budget_line_id: int,
     *     item_name: string|null,
     *     objective: string|null,
     *     work_description: string|null,
     *     technical_specs: string|null,
     *     beneficiaries: string|null,
     *     scheduled_date: string|null,
     *     deliverables: string|null,
     *     delivery_location: string|null,
     *     supervisor: string|null,
     *     payment_terms: string|null,
     *     goods?: list<array<string, mixed>>
     * }>  $sheets
     */
    public function handle(U300Program $program, array $sheets): U300Program
    {
        $storedPhotoPaths = [];
        $obsoletePhotoPaths = [];

        try {
            $updatedProgram = DB::transaction(function () use (
                $program,
                $sheets,
                &$storedPhotoPaths,
                &$obsoletePhotoPaths,
            ): U300Program {
                $lineIds = collect($sheets)->pluck('u300_budget_line_id');

                $budgetLines = U300BudgetLine::query()
                    ->whereIn('id', $lineIds)
                    ->whereHas('budgetVersion', fn ($query) => $query
                        ->where('u300_program_id', $program->id)
                        ->where('kind', 'adjusted'))
                    ->with(['action', 'expenseClassification', 'technicalSheet'])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($budgetLines->count() !== $lineIds->unique()->count()) {
                    throw ValidationException::withMessages([
                        'sheets' => 'Una o más partidas no pertenecen a la adecuación seleccionada.',
                    ]);
                }

                foreach ($sheets as $sheetIndex => $sheetData) {
                    /** @var U300BudgetLine $budgetLine */
                    $budgetLine = $budgetLines->get($sheetData['u300_budget_line_id']);
                    $existingSheet = $budgetLine->technicalSheet;
                    $oldPhotoPaths = $this->photoPaths(
                        $existingSheet?->goods_profile,
                        $existingSheet?->technical_specs,
                    );
                    $goodsProfile = null;
                    $usesGoodsList = in_array(
                        $budgetLine->expenseClassification?->chapter_code,
                        ['2000', '5000'],
                        true,
                    );

                    if ($usesGoodsList && array_key_exists('goods', $sheetData)) {
                        $this->validateOwnedPhotoPaths(
                            $sheetData['goods'],
                            $oldPhotoPaths,
                            $sheetIndex,
                        );
                        $goodsProfile = $this->storeGoods($sheetData['goods'], $storedPhotoPaths);
                        $sheetData['goods_profile'] = $goodsProfile;
                        $sheetData['technical_specs'] = null;
                    }

                    unset($sheetData['goods'], $sheetData['u300_budget_line_id']);

                    if ($existingSheet === null && ! $this->hasMeaningfulCapture($budgetLine, $sheetData, $goodsProfile)) {
                        continue;
                    }

                    $technicalSheet = $budgetLine->technicalSheet()->updateOrCreate(
                        ['u300_budget_line_id' => $budgetLine->id],
                        $sheetData,
                    );

                    if ($goodsProfile !== null) {
                        $newPhotoPaths = $this->photoPaths($technicalSheet->goods_profile, null);
                        $obsoletePhotoPaths = [
                            ...$obsoletePhotoPaths,
                            ...array_diff($oldPhotoPaths, $newPhotoPaths),
                        ];
                    }
                }

                return $program->refresh()->load('budgetVersions.budgetLines.technicalSheet');
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($storedPhotoPaths);

            throw $exception;
        }

        $this->deleteUnreferencedPhotos($obsoletePhotoPaths);

        return $updatedProgram;
    }

    /**
     * @param  list<array<string, mixed>>  $goods
     * @param  list<string>  $storedPhotoPaths
     * @return list<array{unit: string, description: string, minimum_quantity: string, unit_price: string, specifications: string, reference_photo_path: string}>
     */
    private function storeGoods(array $goods, array &$storedPhotoPaths): array
    {
        return collect($goods)
            ->filter(fn (array $good): bool => collect($good)
                ->except(['reference_photo'])
                ->filter(fn (mixed $value): bool => filled($value))
                ->isNotEmpty() || ($good['reference_photo'] ?? null) instanceof UploadedFile)
            ->values()
            ->map(function (array $good) use (&$storedPhotoPaths): array {
                $referencePhotoPath = (string) ($good['reference_photo_path'] ?? '');

                if (($good['reference_photo'] ?? null) instanceof UploadedFile) {
                    $path = $good['reference_photo']->store(
                        'u300/technical-sheets/reference-photos',
                        'public',
                    );

                    if (! is_string($path)) {
                        throw new RuntimeException('No fue posible guardar la foto de referencia.');
                    }

                    $storedPhotoPaths[] = $path;
                    $referencePhotoPath = 'storage/'.$path;
                }

                return [
                    'unit' => (string) ($good['unit'] ?? ''),
                    'description' => (string) ($good['description'] ?? ''),
                    'minimum_quantity' => (string) ($good['minimum_quantity'] ?? ''),
                    'unit_price' => (string) ($good['unit_price'] ?? ''),
                    'specifications' => (string) ($good['specifications'] ?? ''),
                    'reference_photo_path' => $referencePhotoPath,
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $goods
     * @param  list<string>  $ownedPhotoPaths
     */
    private function validateOwnedPhotoPaths(array $goods, array $ownedPhotoPaths, int $sheetIndex): void
    {
        foreach ($goods as $goodIndex => $good) {
            $photoPath = $good['reference_photo_path'] ?? null;

            if (is_string($photoPath) && $photoPath !== '' && ! in_array($photoPath, $ownedPhotoPaths, true)) {
                throw ValidationException::withMessages([
                    "sheets.{$sheetIndex}.goods.{$goodIndex}.reference_photo_path" => 'La foto de referencia ya no pertenece a esta partida.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sheetData
     * @param  list<array<string, string>>|null  $goodsProfile
     */
    private function hasMeaningfulCapture(
        U300BudgetLine $budgetLine,
        array $sheetData,
        ?array $goodsProfile,
    ): bool {
        if ($goodsProfile !== null && $goodsProfile !== []) {
            return true;
        }

        foreach (['work_description', 'technical_specs', 'beneficiaries', 'deliverables'] as $field) {
            if (filled($sheetData[$field] ?? null)) {
                return true;
            }
        }

        if (filled($sheetData['item_name'] ?? null)
            && trim((string) $sheetData['item_name']) !== trim((string) $budgetLine->description)) {
            return true;
        }

        return filled($sheetData['objective'] ?? null)
            && trim((string) $sheetData['objective']) !== trim((string) $budgetLine->action->justification);
    }

    /**
     * @param  list<array<string, mixed>>|null  $goodsProfile
     * @return list<string>
     */
    private function photoPaths(?array $goodsProfile, ?string $technicalSpecs): array
    {
        $paths = collect($goodsProfile ?? [])
            ->pluck('reference_photo_path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        if ($paths->isEmpty() && filled($technicalSpecs)) {
            preg_match_all(
                '/^Foto de referencia:\s*(storage\/u300\/technical-sheets\/reference-photos\/[A-Za-z0-9._-]+)$/mu',
                $technicalSpecs,
                $matches,
            );
            $paths = collect($matches[1] ?? []);
        }

        return $paths->map(fn (string $path): string => $path)->unique()->values()->all();
    }

    /**
     * @param  list<string>  $photoPaths
     */
    private function deletePhotos(array $photoPaths): void
    {
        $relativePaths = collect($photoPaths)
            ->map(fn (string $path): string => str_starts_with($path, 'storage/')
                ? substr($path, strlen('storage/'))
                : $path)
            ->filter(fn (string $path): bool => preg_match(
                '/\Au300\/technical-sheets\/reference-photos\/[A-Za-z0-9._-]+\z/',
                $path,
            ) === 1)
            ->unique()
            ->values()
            ->all();

        if ($relativePaths !== []) {
            Storage::disk('public')->delete($relativePaths);
        }
    }

    /**
     * @param  list<string>  $photoPaths
     */
    private function deleteUnreferencedPhotos(array $photoPaths): void
    {
        if ($photoPaths === []) {
            return;
        }

        $referencedPhotoPaths = U300TechnicalSheet::query()
            ->get(['goods_profile', 'technical_specs'])
            ->flatMap(fn (U300TechnicalSheet $technicalSheet): array => $this->photoPaths(
                $technicalSheet->goods_profile,
                $technicalSheet->technical_specs,
            ))
            ->unique()
            ->all();

        $this->deletePhotos(array_values(array_diff($photoPaths, $referencedPhotoPaths)));
    }
}
