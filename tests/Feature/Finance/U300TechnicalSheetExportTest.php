<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

function u300TechnicalSheetExportUser(): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    return $user;
}

function u300ProgramWithTechnicalSheet(User $user): U300Program
{
    $classification = ExpenseClassification::create([
        'fiscal_year' => 2026,
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios Generales',
        'concept_code' => '3700',
        'concept_name' => 'Servicios de traslado y viáticos',
        'generic_item_code' => '3750',
        'generic_item_name' => 'Viáticos en el país',
        'specific_item_code' => '37501',
        'specific_item_name' => 'Viáticos en el país',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);

    $program = U300Program::create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => '0. Proyecto General U300',
        'objective' => 'Objetivo general.',
        'justification' => 'Justificación general.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
        'responsible_name' => 'William González',
        'responsible_position' => 'Director',
        'responsible_academic_degree' => 'Maestría',
        'responsible_phone' => '9838671071',
        'responsible_email' => 'direccion@crenfcp.edu.mx',
    ]);
    $version = $program->budgetVersions()->create([
        'created_by' => $user->id,
        'kind' => 'adjusted',
        'name' => 'Adecuación presupuestal',
        'status' => 'draft',
        'total_cents' => 16000000,
    ]);
    $project = $program->projects()->create([
        'number' => '5',
        'name' => 'Proyecto de evaluación institucional.',
        'justification' => 'Justificación.',
    ]);
    $goal = $project->goals()->create([
        'number' => '5.1',
        'description' => 'Meta con redistribución permitida.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
    ]);
    $action = $goal->actions()->create([
        'number' => '5.1.2',
        'name' => 'Acción concentradora',
        'justification' => 'Justificación dos.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
    ]);
    $line = $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 16000000,
        'exercise_month' => 'OCT',
        'description' => 'Acción concentradora de la meta 5.1.',
        'justification' => 'Alimentos para movilidad académica.',
    ]);
    $line->technicalSheet()->create([
        'item_name' => 'Servicio de alimentos para movilidad académica',
        'objective' => 'Propiciar movilidad académica.',
        'work_description' => 'Comprar alimentos para estudiantes.',
        'technical_specs' => 'Alimentos para 3 estudiantes por 8 semanas.',
        'beneficiaries' => '3 estudiantes',
        'scheduled_date' => 'AGO',
        'deliverables' => 'Informe con evidencia fotográfica.',
        'delivery_location' => 'Servicios Educativos de Quintana Roo.',
        'supervisor' => 'Dra. Geraldine Díaz Argáez',
        'payment_terms' => 'En una sola emisión, a través de transferencia electrónica.',
    ]);

    return $program;
}

test('finance operator can export technical sheets as a Word document', function () {
    $user = u300TechnicalSheetExportUser();
    $program = u300ProgramWithTechnicalSheet($user);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.export', $program));

    $response->assertOk();
    $response->assertDownload('fichas-tecnicas-u300-2026.docx');

    $path = tempnam(sys_get_temp_dir(), 'u300-docx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    $documentRelationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $headerXml = $zip->getFromName('word/header1.xml');
    $stylesXml = $zip->getFromName('word/styles.xml');
    $zip->close();
    unlink($path);

    expect($documentRelationshipsXml)
        ->toBeString()
        ->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header"')
        ->toContain('Target="header1.xml"')
        ->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"')
        ->toContain('Target="styles.xml"');
    expect($stylesXml)
        ->toBeString()
        ->toContain('<w:rFonts w:ascii="Aptos Narrow" w:hAnsi="Aptos Narrow" w:cs="Aptos Narrow"/>')
        ->toContain('<w:sz w:val="22"/>')
        ->toContain('<w:szCs w:val="22"/>');
    expect($documentXml)
        ->toContain('<w:headerReference w:type="default"')
        ->toContain('<w:pgMar w:top="1134" w:right="567" w:bottom="1134" w:left="1701"')
        ->toContain('<w:tblW w:w="9972" w:type="dxa"/>')
        ->toContain('<w:tblLayout w:type="autofit"/>')
        ->toContain('<w:tcW w:w="2400" w:type="dxa"/>')
        ->toContain('<w:tcW w:w="7572" w:type="dxa"/>')
        ->toContain('<w:sz w:val="26"/>')
        ->not->toContain('CENTRO REGIONAL DE EDUCACIÓN NORMAL');
    expect($headerXml)
        ->toBeString()
        ->toContain('CENTRO REGIONAL DE EDUCACIÓN NORMAL')
        ->toContain('FICHA TÉCNICA');
    expect($documentXml)
        ->toContain('ACCIÓN')
        ->toContain('5.1.2 Acción concentradora')
        ->toContain('37501')
        ->toContain('Viáticos en el país')
        ->toContain('Servicio de alimentos para movilidad académica')
        ->toContain('$160,000.00')
        ->toContain('Son: ciento sesenta mil pesos 00/100 M.N.')
        ->toContain('Agosto de 2026')
        ->not->toContain('AGO')
        ->toContain('Propiciar movilidad académica.')
        ->toContain('Dra. Geraldine Díaz Argáez');
    expect(preg_match('/<w:t xml:space="preserve">37501<\/w:t>.*?<w:br\/>.*?<w:t xml:space="preserve">Viáticos en el país<\/w:t>/s', $documentXml))->toBe(1);
    expect(preg_match('/<w:t xml:space="preserve">37501<\/w:t>.*?<w:br\/>.*?<w:b\/>.*?<w:t xml:space="preserve">Viáticos en el país<\/w:t>/s', $documentXml))->toBe(0);
});

test('technical sheet export renders requested goods tables rich text and reference photos', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetExportUser();
    $program = u300ProgramWithTechnicalSheet($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $referencePhoto = UploadedFile::fake()->image('microscopio.jpg', 800, 400);
    Storage::disk('public')->put(
        'u300/technical-sheets/reference-photos/microscopio.jpg',
        file_get_contents($referencePhoto->getRealPath()),
    );
    $line->technicalSheet()->update([
        'technical_specs' => "1. Microscopio escolar\nUnidad de medida: Pieza\nCantidad mínima: 2\nPrecio unitario: $1,500.00\nTotal: $3,000.00\nEspecificaciones: *Características del bien*\n- Lente _óptico_\n- Iluminación *LED*\nFoto de referencia: storage/u300/technical-sheets/reference-photos/microscopio.jpg",
        'beneficiaries' => "- *120 estudiantes*\n- Docentes de _laboratorio_",
        'deliverables' => "- Acta de entrega\n- Evidencia *fotográfica*",
    ]);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.export', $program));

    $path = tempnam(sys_get_temp_dir(), 'u300-docx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    $documentRelationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $mediaFiles = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        if (str_starts_with($name, 'word/media/')) {
            $mediaFiles[] = $name;
        }
    }

    $zip->close();
    unlink($path);

    expect($documentXml)
        ->toContain('UNIDAD')
        ->toContain('DE MEDIDA')
        ->toContain('DESCRIPCIÓN DEL BIEN')
        ->toContain('CANTIDAD')
        ->toContain('MÍNIMA')
        ->toContain('<w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="autofit"/>')
        ->not->toContain('<w:gridCol w:w="1800"/><w:gridCol w:w="3972"/><w:gridCol w:w="1800"/>')
        ->not->toContain('<w:tcW w:w="1800" w:type="dxa"/>')
        ->not->toContain('<w:tcW w:w="3972" w:type="dxa"/>')
        ->toContain('<w:sz w:val="14"/>')
        ->toContain('<w:sz w:val="17"/>')
        ->toContain('Microscopio escolar')
        ->toContain('Características del bien')
        ->toContain('Especificaciones')
        ->toContain('Foto de referencia')
        ->toContain('• Lente ')
        ->toContain('<w:i/>')
        ->toContain('<w:b/>')
        ->toContain('<wp:extent cx="1620000" cy="810000"/>')
        ->toContain('<a:ext cx="1620000" cy="810000"/>')
        ->toContain('120 estudiantes')
        ->toContain('Docentes de ')
        ->toContain('Acta de entrega')
        ->toContain('Evidencia ');
    expect(preg_match('/UNIDAD.*?<w:br\/>.*?DE MEDIDA/s', $documentXml))->toBe(1);
    expect(preg_match('/CANTIDAD.*?<w:br\/>.*?MÍNIMA/s', $documentXml))->toBe(1);
    expect($documentXml)
        ->not->toContain('<w:t xml:space="preserve">Pieza</w:t></w:r></w:p><w:p/></w:tc>')
        ->not->toContain('<w:t xml:space="preserve">Microscopio escolar</w:t></w:r></w:p><w:p/></w:tc>')
        ->not->toContain('<w:t xml:space="preserve">2</w:t></w:r></w:p><w:p/></w:tc>')
        ->not->toContain('<w:p/></w:tc>')
        ->toContain('<w:vanish/>');
    expect($documentRelationshipsXml)
        ->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"');
    expect($mediaFiles)->toHaveCount(1);
});

test('technical sheet export ignores arbitrary local reference photo paths', function () {
    $user = u300TechnicalSheetExportUser();
    $program = u300ProgramWithTechnicalSheet($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $line->expenseClassification()->update(['chapter_code' => '2000']);
    $privateImage = UploadedFile::fake()->image('private-reference.jpg', 800, 400);
    $privateImagePath = sys_get_temp_dir().'/u300-private-reference-'.uniqid().'.jpg';
    file_put_contents($privateImagePath, file_get_contents($privateImage->getRealPath()));

    $line->technicalSheet()->update([
        'technical_specs' => "1. Microscopio escolar\nEspecificaciones: Lente óptico\nFoto de referencia: {$privateImagePath}",
    ]);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.export', $program));

    $path = tempnam(sys_get_temp_dir(), 'u300-docx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $documentRelationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $mediaFiles = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        if (str_starts_with($name, 'word/media/')) {
            $mediaFiles[] = $name;
        }
    }

    $zip->close();
    unlink($path);
    unlink($privateImagePath);

    expect($documentRelationshipsXml)
        ->not->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"');
    expect($mediaFiles)->toBeEmpty();
});

test('technical sheet export uses the program fiscal year for abbreviated months', function () {
    $user = u300TechnicalSheetExportUser();
    $program = u300ProgramWithTechnicalSheet($user);
    $program->update(['fiscal_year' => 2027]);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.export', $program));

    $path = tempnam(sys_get_temp_dir(), 'u300-docx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');

    $zip->close();
    unlink($path);

    expect($documentXml)
        ->toContain('Agosto de 2027')
        ->not->toContain('Agosto de 2026');
});
