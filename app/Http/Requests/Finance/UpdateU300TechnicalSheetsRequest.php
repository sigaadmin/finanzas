<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\U300\U300Program;
use App\Models\Finance\U300\U300TechnicalSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdateU300TechnicalSheetsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('operate-finance') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stay_on_page' => ['sometimes', 'boolean'],
            'return_to_line_id' => ['sometimes', 'integer', 'exists:u300_budget_lines,id'],
            'sheets' => ['required', 'array', 'min:1'],
            'sheets.*.u300_budget_line_id' => ['required', 'integer', 'distinct', 'exists:u300_budget_lines,id'],
            'sheets.*.item_name' => ['nullable', 'string', 'max:3000'],
            'sheets.*.objective' => ['nullable', 'string', 'max:3000'],
            'sheets.*.work_description' => ['nullable', 'string', 'max:3000'],
            'sheets.*.technical_specs' => ['nullable', 'string', 'max:5000'],
            'sheets.*.beneficiaries' => ['nullable', 'string', 'max:255'],
            'sheets.*.scheduled_date' => ['nullable', 'string', 'max:255'],
            'sheets.*.deliverables' => ['nullable', 'string', 'max:3000'],
            'sheets.*.delivery_location' => ['nullable', 'string', 'max:3000'],
            'sheets.*.supervisor' => ['nullable', 'string', 'max:1000'],
            'sheets.*.payment_terms' => ['nullable', 'string', 'max:1000'],
            'sheets.*.goods' => ['sometimes', 'array', 'list', 'max:50'],
            'sheets.*.goods.*' => ['array:unit,description,minimum_quantity,unit_price,specifications,reference_photo,reference_photo_path'],
            'sheets.*.goods.*.unit' => ['nullable', 'string', 'max:255'],
            'sheets.*.goods.*.description' => ['nullable', 'string', 'max:1000'],
            'sheets.*.goods.*.minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'sheets.*.goods.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'sheets.*.goods.*.specifications' => ['nullable', 'string', 'max:3000'],
            'sheets.*.goods.*.reference_photo' => ['nullable', File::types(['jpg', 'jpeg', 'png'])->max('5mb')],
            'sheets.*.goods.*.reference_photo_path' => [
                'nullable',
                'string',
                'max:1000',
                'regex:/\Astorage\/u300\/technical-sheets\/reference-photos\/[A-Za-z0-9._-]+\z/',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $program = $this->route('program');

                if (! $program instanceof U300Program) {
                    return;
                }

                $validLines = $program->budgetVersions()
                    ->where('kind', 'adjusted')
                    ->first()
                    ?->budgetLines()
                    ->with('technicalSheet')
                    ->get()
                    ->keyBy('id') ?? collect();
                $validLineIds = $validLines->keys()
                    ->map(fn (int $id): int => $id)
                    ->all();
                $sheets = $this->all()['sheets'] ?? [];

                if (! is_array($sheets)) {
                    return;
                }

                foreach ($sheets as $sheetIndex => $sheet) {
                    if (! is_array($sheet)) {
                        continue;
                    }

                    $lineId = (int) ($sheet['u300_budget_line_id'] ?? 0);

                    if (! in_array($lineId, $validLineIds, true)) {
                        $validator->errors()->add(
                            "sheets.{$sheetIndex}.u300_budget_line_id",
                            'La partida no pertenece a la adecuación presupuestal seleccionada.',
                        );
                    }

                    $goods = $sheet['goods'] ?? null;

                    if (! is_array($goods) || ! collect($goods)->every(fn (mixed $good): bool => is_array($good))) {
                        continue;
                    }

                    if ($this->goodsProfileLength($goods) > 50000) {
                        $validator->errors()->add(
                            "sheets.{$sheetIndex}.goods",
                            'El perfil de bienes no debe exceder 50,000 caracteres.',
                        );
                    }

                    /** @var U300TechnicalSheet|null $technicalSheet */
                    $technicalSheet = $validLines->get($lineId)?->technicalSheet;
                    $ownedPhotoPaths = $this->photoPaths($technicalSheet);

                    foreach ($goods as $goodIndex => $good) {
                        $photoPath = $good['reference_photo_path'] ?? null;

                        if (is_string($photoPath) && $photoPath !== '' && ! in_array($photoPath, $ownedPhotoPaths, true)) {
                            $validator->errors()->add(
                                "sheets.{$sheetIndex}.goods.{$goodIndex}.reference_photo_path",
                                'La foto de referencia no pertenece a esta partida.',
                            );
                        }
                    }
                }

                $returnToLineId = $this->integer('return_to_line_id');

                if ($returnToLineId > 0 && ! in_array($returnToLineId, $validLineIds, true)) {
                    $validator->errors()->add(
                        'return_to_line_id',
                        'La partida de retorno no pertenece al programa seleccionado.',
                    );
                }
            },
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sheets(): array
    {
        return collect($this->validated('sheets'))->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $goods
     */
    private function goodsProfileLength(array $goods): int
    {
        $projectedGoods = collect($goods)
            ->filter(fn (array $good): bool => collect($good)
                ->except(['reference_photo'])
                ->filter(fn (mixed $value): bool => filled($value))
                ->isNotEmpty() || ($good['reference_photo'] ?? null) instanceof UploadedFile)
            ->map(function (array $good): array {
                $photo = $good['reference_photo'] ?? null;
                $referencePhotoPath = (string) ($good['reference_photo_path'] ?? '');

                if ($photo instanceof UploadedFile) {
                    $referencePhotoPath = 'storage/u300/technical-sheets/reference-photos/'.$photo->hashName();
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
            ->values()
            ->all();

        return mb_strlen((string) json_encode($projectedGoods, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<string>
     */
    private function photoPaths(?U300TechnicalSheet $technicalSheet): array
    {
        if ($technicalSheet === null) {
            return [];
        }

        $paths = collect($technicalSheet->goods_profile ?? [])
            ->pluck('reference_photo_path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        if ($paths->isEmpty() && filled($technicalSheet->technical_specs)) {
            preg_match_all(
                '/^Foto de referencia:\s*(storage\/u300\/technical-sheets\/reference-photos\/[A-Za-z0-9._-]+)$/mu',
                $technicalSheet->technical_specs,
                $matches,
            );
            $paths = collect($matches[1] ?? []);
        }

        return $paths->map(fn (string $path): string => $path)->unique()->values()->all();
    }
}
