<?php

namespace App\Services\Siga;

use App\Data\Finance\SigaStudentData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SigaStudentClient
{
    /**
     * @return list<SigaStudentData>
     */
    public function search(string $query): array
    {
        $baseUrl = rtrim((string) config('finance.siga.base_url'), '/');
        $token = (string) config('finance.siga.token');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('finance.siga.timeout', 10))
                ->get('/api/internal/finance/v1/students/search', [
                    'query' => $query,
                    'include_graduates' => 1,
                    'limit' => 10,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException('No se pudo consultar SIGA2.', previous: $exception);
        }

        return collect($response->json('data', []))
            ->map(fn (array $student): SigaStudentData => SigaStudentData::fromArray($student))
            ->values()
            ->all();
    }
}
