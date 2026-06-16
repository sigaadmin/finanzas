<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use App\Services\Finance\U300\PdfTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300ImportUser(): User
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

test('finance operator can preview and confirm a U300 PDF import', function () {
    Storage::fake('local');

    app()->bind(PdfTextExtractor::class, fn () => new class extends PdfTextExtractor
    {
        public function extract(string $path): string
        {
            return <<<'TEXT'
            TOTAL GENERAL DEL PROYECTO: $4,630,000.00
            Proyecto General: 0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto
            Objevo general del Proyecto General: Fortalecer la capacidad académica.
            Jusﬁcación del Proyecto General: Responder a prioridades institucionales.
            Datos del responsable:
            Nombre: William Miguel González Rodríguez
            Cargo: Director
            Grado Académico: Maestría
            Teléfono: 9838671071
            Correo electrónico: direccion@crenfcp.edu.mx
            Proyecto: 1. Fortalecer las condiciones físicas y funcionales del CREN-FCP.
            Justificación del Proyecto: Atender prioridades institucionales.
            Meta: 1.1 Ampliar la capacidad instalada.
            Subtotal de la Meta: $4,630,000.00
            Acción: 1.1.1 Conclusión y habilitación de dos aulas académicas
            Jusﬁcación 2026: Realizar obra civil necesaria.
            RECURSOS 2026
            Concepto de gasto Rubro de gasto P Candad Precio unitario Total
            Construcción Aulas 4 2 $2,300,000 $4,600,000
            Equipamiento Cañon 4 2 $15,000 $30,000
            Total $4,630,000.00
            TEXT;
        }
    });

    $this->actingAs(u300ImportUser())
        ->get(route('finance.u300.imports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/imports/create'));

    $this->actingAs(u300ImportUser())
        ->post(route('finance.u300.imports.preview'), [
            'fiscal_year' => 2026,
            'project_pdf' => UploadedFile::fake()->create('proyecto.pdf', 20, 'application/pdf'),
        ])
        ->assertRedirect(route('finance.u300.imports.preview.show'));

    $this->actingAs(u300ImportUser())
        ->get(route('finance.u300.imports.preview.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/imports/preview')
            ->where('preview.fiscal_year', 2026)
            ->where('preview.parsed.general.name', '0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto')
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.expense_concept', 'Equipamiento')
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.expense_item', 'Cañon')
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.period', 4)
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.quantity', 2)
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.unit_price_cents', 1500000)
            ->where('preview.parsed.projects.0.goals.0.actions.0.items.1.total_cents', 3000000));

    $this->actingAs(u300ImportUser())
        ->post(route('finance.u300.imports.store'))
        ->assertRedirect();

    expect(U300Program::query()->count())->toBe(1);

    $this->assertDatabaseHas('u300_programs', [
        'fiscal_year' => 2026,
        'name' => '0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto',
    ]);
});
