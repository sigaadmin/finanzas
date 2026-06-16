<?php

namespace App\Services\Finance\U300;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo extraer texto del PDF. Verifica que pdftotext esté disponible en el servidor.');
        }

        return $process->getOutput();
    }
}
