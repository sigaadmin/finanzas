<?php

namespace App\Services\Finance\U300;

class U300ProjectParser
{
    /**
     * @return array{
     *     general: array{name: string, objective: string, justification: string, requested_total_cents: int},
     *     responsible: array{name: string, position: string, academic_degree: string, phone: string, email: string},
     *     projects: list<array{number: string, name: string, justification: string, goals: list<array{number: string, description: string, requested_total_cents: int, actions: list<array{number: string, name: string, justification: string, items: list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>}>}>
     * }
     */
    public function parse(string $text): array
    {
        $text = $this->normalize($text);

        return [
            'general' => [
                'name' => $this->matchAfter($text, 'Proyecto General:', 'Objetivo general del Proyecto General:'),
                'objective' => $this->matchAfter($text, 'Objetivo general del Proyecto General:', 'Justificación del Proyecto General:'),
                'justification' => $this->matchAfter($text, 'Justificación del Proyecto General:', 'Datos del responsable:'),
                'requested_total_cents' => $this->moneyToCents($this->matchLine($text, '/TOTAL GENERAL DEL PROYECTO:\s*(\$[\d,]+(?:\.\d{2})?)/u')),
            ],
            'responsible' => [
                'name' => $this->matchLine($text, '/Nombre:\s*(.+)/u'),
                'position' => $this->matchLine($text, '/Cargo:\s*(.+)/u'),
                'academic_degree' => $this->matchLine($text, '/Grado Académico:\s*(.+)/u'),
                'phone' => $this->matchLine($text, '/Teléfono:\s*(.+)/u'),
                'email' => $this->matchLine($text, '/Correo electrónico:\s*(.+)/u'),
            ],
            'projects' => $this->parseProjects($text),
        ];
    }

    private function normalize(string $text): string
    {
        $text = strtr($text, [
            'ﬁ' => 'fi',
            'ﬂ' => 'fl',
        ]);

        $replacements = [
            'Obje vo' => 'Objetivo',
            'Objevo' => 'Objetivo',
            'Jus ficación' => 'Justificación',
            'Jusﬁcación' => 'Justificación',
            'Jusficación' => 'Justificación',
            'Can dad' => 'Cantidad',
            'Candad' => 'Cantidad',
            'garan ce' => 'garantice',
            'par cipación' => 'participación',
            'Par cipación' => 'Participación',
            'equita va' => 'equitativa',
            'forma va' => 'formativa',
            'forma vas' => 'formativas',
            'forma vo' => 'formativo',
            'forma vos' => 'formativos',
            'sép ca' => 'séptica',
            'conec vidad' => 'conectividad',
            'educa va' => 'educativa',
            'educa vas' => 'educativas',
            'educa vo' => 'educativo',
            'educa vos' => 'educativos',
            'administra va' => 'administrativa',
            'didác co' => 'didáctico',
            'ins tución' => 'institución',
            'ins tuciones' => 'instituciones',
            'Ins tuciones' => 'Instituciones',
            'ins tucional' => 'institucional',
            'Ins tucional' => 'Institucional',
            'ins tucionales' => 'institucionales',
            'Ins tucionales' => 'Institucionales',
            'So ware' => 'Software',
            'so ware' => 'software',
            'Ar culos' => 'Artículos',
            'ar culos' => 'artículos',
            'ar culada' => 'articulada',
            'ar culadas' => 'articuladas',
            'ar culado' => 'articulado',
            'ar culados' => 'articulados',
            'ar cula' => 'articula',
            'ar cular' => 'articular',
            'ar culación' => 'articulación',
            'inves gación' => 'investigación',
            'Inves gación' => 'Investigación',
            'autoforma vo' => 'autoformativo',
            'autoforma vos' => 'autoformativos',
            'a par r' => 'a partir',
            'par r' => 'partir',
            'ac vidad' => 'actividad',
            'ac vidades' => 'actividades',
            'ac va' => 'activa',
            'ac vas' => 'activas',
            'ac vo' => 'activo',
            'ac vos' => 'activos',
            'per nencia' => 'pertinencia',
            'per nente' => 'pertinente',
            'iden dad' => 'identidad',
            'compara vo' => 'comparativo',
            'sistema zación' => 'sistematización',
            'sistema zar' => 'sistematizar',
            'prác ca' => 'práctica',
            'prác cas' => 'prácticas',
            'Iden ficación' => 'Identificación',
            'iden ficación' => 'identificación',
            'iden ficadas' => 'identificadas',
            'iden ficados' => 'identificados',
            'Cer ficación' => 'Certificación',
            'cer ficación' => 'certificación',
            'ges ón' => 'gestión',
            'Ges ón' => 'Gestión',
            'diagnós co' => 'diagnóstico',
            'Diagnós co' => 'Diagnóstico',
            'con nua' => 'continua',
            'Con nua' => 'Continua',
            'mul anual' => 'multianual',
            'Mul anual' => 'Multianual',
            'múl ples' => 'múltiples',
            ' sicos' => ' físicos',
            'sicos' => 'físicos',
            'sicas' => 'físicas',
        ];

        $text = strtr($text, $replacements);
        $text = preg_replace('/^\s*Página\s+\d+\s+\|\s+Total General:.*$/mu', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R/u', "\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<array{number: string, name: string, justification: string, goals: list<array{number: string, description: string, requested_total_cents: int, actions: list<array{number: string, name: string, justification: string, items: list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>}>}>
     */
    private function parseProjects(string $text): array
    {
        preg_match_all('/^\s*Proyecto:\s*(\d+)\.\s*(.*?)(?=^\s*Proyecto:\s*\d+\.|\z)/msu', $text, $projectMatches, PREG_SET_ORDER);

        return collect($projectMatches)
            ->map(function (array $match): array {
                $block = trim($match[0]);

                return [
                    'number' => $match[1],
                    'name' => $this->cleanInlineText($this->matchBetweenRegex(
                        $block,
                        '/^\s*Proyecto:\s*\d+\.\s*/u',
                        '/^\s*Subtotal del Proyecto:/mu',
                    )),
                    'justification' => $this->matchAfterAny($block, ['Justificación del Proyecto:', 'Justificación:'], 'Meta:'),
                    'goals' => $this->parseGoals($block),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, description: string, requested_total_cents: int, actions: list<array{number: string, name: string, justification: string, items: list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>}>
     */
    private function parseGoals(string $text): array
    {
        preg_match_all('/^\s*Meta:\s*(\d+\.\d+)\s*(.*?)(?=^\s*Meta:\s*\d+\.\d+|^\s*Proyecto:\s*\d+\.|\z)/msu', $text, $goalMatches, PREG_SET_ORDER);

        return collect($goalMatches)
            ->map(function (array $match): array {
                $block = trim($match[0]);

                return [
                    'number' => $match[1],
                    'description' => $this->cleanInlineText($this->matchBetweenRegex(
                        $block,
                        '/^\s*Meta:\s*\d+\.\d+\s*/u',
                        '/^\s*Subtotal de la Meta:/mu',
                    )),
                    'requested_total_cents' => $this->moneyToCents($this->matchLine($block, '/Subtotal de la Meta:\s*(\$[\d,]+(?:\.\d{2})?)/u')),
                    'actions' => $this->parseActions($block),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, name: string, justification: string, items: list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>}>
     */
    private function parseActions(string $text): array
    {
        preg_match_all('/^\s*Acción:\s*(\d+\.\d+\.\d+)\s*(.*?)(?=^\s*Acción:\s*\d+\.\d+\.\d+|^\s*Meta:\s*\d+\.\d+|^\s*Proyecto:\s*\d+\.|\z)/msu', $text, $actionMatches, PREG_SET_ORDER);

        return collect($actionMatches)
            ->map(function (array $match): array {
                $block = trim($match[0]);

                return [
                    'number' => $match[1],
                    'name' => $this->cleanInlineText($this->matchBetweenRegex(
                        $block,
                        '/^\s*Acción:\s*\d+\.\d+\.\d+\s*/u',
                        '/^\s*Justificación 2026:/mu',
                    )),
                    'justification' => $this->matchAfter($block, 'Justificación 2026:', 'RECURSOS 2026'),
                    'items' => $this->parseItems($block),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>
     */
    private function parseItems(string $text): array
    {
        $lines = collect(explode("\n", $text))
            ->map(fn (string $line): string => trim($line))
            ->filter();

        $items = [];
        $inTable = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'Concepto de gasto') && str_contains($line, 'Rubro de gasto')) {
                $inTable = true;

                continue;
            }

            if (! $inTable) {
                continue;
            }

            if (preg_match('/^(?:Total\s+\$|.*\s+Total\s+\$)/u', $line) === 1) {
                break;
            }

            if (preg_match('/^(?<left>.+?)\s+(?<period>[1-4])\s+(?<quantity>\d+)\s+(?<unit>\$[\d,]+(?:\.\d{2})?)\s+(?<total>\$[\d,]+(?:\.\d{2})?)$/u', $line, $match) === 1) {
                [$concept, $item] = $this->splitExpenseConceptAndItem(trim($match['left']));
                $items[] = [
                    'expense_concept' => $concept,
                    'expense_item' => $item,
                    'period' => (int) $match['period'],
                    'quantity' => (int) $match['quantity'],
                    'unit_price_cents' => $this->moneyToCents($match['unit']),
                    'total_cents' => $this->moneyToCents($match['total']),
                ];

                continue;
            }

            if ($items !== [] && preg_match('/^\$|^\d+$|^RECURSOS 2026$/u', $line) !== 1) {
                $lastIndex = array_key_last($items);
                $items[$lastIndex]['expense_item'] = $this->cleanInlineText($items[$lastIndex]['expense_item'].' '.$line);
            }
        }

        return $items;
    }

    private function matchAfter(string $text, string $start, string $end): string
    {
        $pattern = '/'.preg_quote($start, '/').'\s*(.*?)\s*'.preg_quote($end, '/').'/su';

        if (preg_match($pattern, $text, $match) !== 1) {
            return '';
        }

        return $this->cleanInlineText($match[1]);
    }

    /**
     * @param  list<string>  $starts
     */
    private function matchAfterAny(string $text, array $starts, string $end): string
    {
        foreach ($starts as $start) {
            $value = $this->matchAfter($text, $start, $end);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function matchBetweenRegex(string $text, string $startPattern, string $endPattern): string
    {
        $pattern = '/(?:'.$this->regexBody($startPattern).')(.*?)(?:'.$this->regexBody($endPattern).')/msu';

        if (preg_match($pattern, $text, $match) !== 1) {
            return '';
        }

        return $match[1];
    }

    private function regexBody(string $pattern): string
    {
        if (! str_starts_with($pattern, '/')) {
            return $pattern;
        }

        $lastDelimiter = strrpos($pattern, '/');

        if ($lastDelimiter === 0 || $lastDelimiter === false) {
            return $pattern;
        }

        return substr($pattern, 1, $lastDelimiter - 1);
    }

    private function cleanInlineText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function matchLine(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $match) !== 1) {
            return '';
        }

        return trim($match[1]);
    }

    private function moneyToCents(string $money): int
    {
        $normalized = str_replace(['$', ','], '', $money);

        return (int) round(((float) $normalized) * 100);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitExpenseConceptAndItem(string $text): array
    {
        $concepts = [
            'Servicios personales',
            'Insumos consumibles',
            'Mantenimiento',
            'Construcción',
            'Equipamiento',
            'Mobiliario',
            'Materiales',
            'Artículos',
            'Software',
        ];

        foreach ($concepts as $concept) {
            if (str_starts_with($text, $concept.' ')) {
                return [$concept, trim(substr($text, strlen($concept)))];
            }
        }

        $parts = explode(' ', $text, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
