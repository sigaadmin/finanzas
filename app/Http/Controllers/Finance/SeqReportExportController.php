<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildSeqReportRows;
use App\Http\Controllers\Controller;
use App\Models\SeqReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SeqReportExportController extends Controller
{
    public function __invoke(Request $request, BuildSeqReportRows $buildRows): Response
    {
        Gate::authorize('operate-finance');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $rows = $buildRows->handle($filters);

        SeqReportExport::query()->create([
            'generated_by' => $request->user()->id,
            'period_month' => $this->periodMonth($filters),
            'filters' => $filters,
            'total_pesos' => $rows->sum('total_pesos'),
            'receipt_count' => $rows->count(),
            'exported_at' => now(),
        ]);

        $filename = 'seq-reporte-'.($filters['from'] ?? 'inicio').'-'.($filters['to'] ?? 'hoy').'.xls';

        return response($this->renderTable($rows), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    private function periodMonth(array $filters): string
    {
        $date = $filters['from'] ?? $filters['to'] ?? now()->toDateString();

        return CarbonImmutable::parse($date)->format('Y-m');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function renderTable($rows): string
    {
        $html = '<table><thead><tr>';

        foreach (['Folio', 'Fecha', 'Estudiante', 'Grado', 'Grupo', 'Concepto', 'Importe', 'Cantidad en letras', 'Fecha deposito', 'Folio deposito', 'Tipo deposito', 'Concepto deposito'] as $heading) {
            $html .= '<th>'.e($heading).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>'.e($row['folio']).'</td>';
            $html .= '<td>'.e($row['issued_at']).'</td>';
            $html .= '<td>'.e($row['student_name']).'</td>';
            $html .= '<td>'.e($row['grade']).'</td>';
            $html .= '<td>'.e($row['group']).'</td>';
            $html .= '<td>'.e($row['concept_name']).'</td>';
            $html .= '<td>'.number_format($row['total_pesos'], 0, '.', '').'</td>';
            $html .= '<td>'.e($row['amount_in_words']).'</td>';
            $html .= '<td>'.e($row['seq_deposit']['deposit_date'] ?? '').'</td>';
            $html .= '<td>'.e($row['seq_deposit']['bank_transaction_folio'] ?? '').'</td>';
            $html .= '<td>'.e($row['seq_deposit']['deposit_type'] ?? '').'</td>';
            $html .= '<td>'.e($row['seq_deposit']['deposit_concept'] ?? '').'</td>';
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }
}
