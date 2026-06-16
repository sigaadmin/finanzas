<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\StoreU300ImportedProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PreviewU300ImportRequest;
use App\Services\Finance\U300\PdfTextExtractor;
use App\Services\Finance\U300\U300ProjectParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class U300ImportController extends Controller
{
    private const PreviewSessionKey = 'finance.u300.import.preview';

    public function create(): Response
    {
        return Inertia::render('finance/u300/imports/create');
    }

    public function preview(
        PreviewU300ImportRequest $request,
        PdfTextExtractor $extractor,
        U300ProjectParser $parser,
    ): RedirectResponse {
        $file = $request->file('project_pdf');
        $path = $file->store('u300/imports');
        $absolutePath = Storage::disk('local')->path($path);
        $text = $extractor->extract($absolutePath);

        $request->session()->put(self::PreviewSessionKey, [
            'fiscal_year' => (int) $request->validated('fiscal_year'),
            'source_filename' => $file->getClientOriginalName(),
            'source_path' => $path,
            'parsed' => $parser->parse($text),
        ]);

        return to_route('finance.u300.imports.preview.show');
    }

    public function showPreview(Request $request): Response|RedirectResponse
    {
        $preview = $request->session()->get(self::PreviewSessionKey);

        if (! is_array($preview)) {
            return to_route('finance.u300.imports.create');
        }

        return Inertia::render('finance/u300/imports/preview', [
            'preview' => $preview,
        ]);
    }

    public function store(Request $request, StoreU300ImportedProject $store): RedirectResponse
    {
        $preview = $request->session()->get(self::PreviewSessionKey);

        if (! is_array($preview)) {
            return to_route('finance.u300.imports.create');
        }

        $program = $store->handle(
            importedBy: $request->user(),
            fiscalYear: (int) $preview['fiscal_year'],
            sourceFilename: $preview['source_filename'],
            sourcePath: $preview['source_path'],
            parsed: $preview['parsed'],
        );

        $request->session()->forget(self::PreviewSessionKey);

        return to_route('finance.u300.programs.show', $program);
    }
}
