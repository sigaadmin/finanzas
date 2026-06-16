<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Siga\SigaStudentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class StudentLookupController extends Controller
{
    public function __invoke(Request $request, SigaStudentClient $students): JsonResponse
    {
        Gate::authorize('operate-finance');

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        try {
            $results = $students->search($validated['q']);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'No se pudo consultar SIGA2.',
            ], 503);
        }

        return response()->json([
            'data' => array_map(fn ($student): array => $student->toArray(), $results),
        ]);
    }
}
