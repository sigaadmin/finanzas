<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\CreateU300BackupArchive;
use App\Actions\Finance\U300\InspectU300BackupArchive;
use App\Actions\Finance\U300\RestoreU300BackupArchive;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PreviewU300BackupRestoreRequest;
use App\Http\Requests\Finance\RestoreU300BackupRequest;
use App\Models\Finance\U300\U300Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class U300BackupController extends Controller
{
    public function download(U300Program $program, CreateU300BackupArchive $create): StreamedResponse
    {
        abort_unless(request()->user()?->can('manage-u300-backups'), 403);
        $archive = $create->handle($program, request()->user(), 'manual');

        return Storage::disk($archive->disk)->download($archive->path, $archive->original_filename);
    }

    public function preview(PreviewU300BackupRestoreRequest $request, InspectU300BackupArchive $inspector): RedirectResponse
    {
        $path = $request->file('archive')->store('u300/restore-previews', 'local');
        $preview = $inspector->handle(Storage::disk('local')->path($path));
        $token = (string) Str::uuid();

        $request->session()->put('finance.u300.restore_preview', [
            'token' => $token,
            'path' => $path,
            'fiscal_year' => $preview['fiscal_year'],
            'files_count' => $preview['files_count'],
        ]);

        return to_route('finance.u300.programs.index')->with('restore_preview', $request->session()->get('finance.u300.restore_preview'));
    }

    public function restore(RestoreU300BackupRequest $request, RestoreU300BackupArchive $restore): RedirectResponse
    {
        $preview = $request->session()->get('finance.u300.restore_preview');

        abort_unless(is_array($preview) && hash_equals((string) $preview['token'], $request->string('preview_token')->toString()), 422);
        abort_unless($request->string('confirmation')->toString() === 'RESTAURAR U300 '.$preview['fiscal_year'], 422);

        $restore->handle(Storage::disk('local')->path($preview['path']), $request->user());
        Storage::disk('local')->delete($preview['path']);
        $request->session()->forget('finance.u300.restore_preview');

        return to_route('finance.u300.programs.index')->with('success', 'Respaldo U300 restaurado correctamente.');
    }
}
