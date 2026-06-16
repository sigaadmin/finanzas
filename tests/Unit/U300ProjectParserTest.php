<?php

use App\Services\Finance\U300\U300ProjectParser;

test('it parses the federal project text into a reviewable project structure', function () {
    $text = <<<'TEXT'
    Proyecto Especiﬁco Estatal
    TOTAL GENERAL DEL PROYECTO: $31,296,825.00
    Proyecto General: 0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto
    Objevo general del Proyecto General: Fortalecer de manera integral la capacidad académica.
    Jusﬁcación del Proyecto General: El Centro Regional enfrenta retos en infraestructura.
    Datos del responsable:
    Nombre: William Miguel González Rodríguez
    Cargo: Director
    Grado Académico: Maestría
    Teléfono: 9838671071
    Correo electrónico: direccion@crenfcp.edu.mx
    Proyecto: 1. Fortalecer las condiciones físicas y funcionales del CREN-FCP.
    Justificación del Proyecto: Atender prioridades institucionales.
    Meta: 1.1 Ampliar la capacidad instalada del CREN-FCP.
    Subtotal de la Meta: $7,183,000.00
    Acción: 1.1.1 Conclusión y habilitación de dos aulas académicas
    Jusﬁcación 2026: Realizar las acciones de obra civil necesarias.
    RECURSOS 2026
    Concepto de gasto Rubro de gasto P Candad Precio unitario Total
    Construcción Aulas 4 2 $2,300,000 $4,600,000
    Equipamiento Cañon 4 2 $15,000 $30,000
    Total $4,630,000.00
    TEXT;

    $project = (new U300ProjectParser)->parse($text);

    expect($project['general']['name'])->toBe('0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto')
        ->and($project['general']['requested_total_cents'])->toBe(3129682500)
        ->and($project['general']['objective'])->toBe('Fortalecer de manera integral la capacidad académica.')
        ->and($project['general']['justification'])->toBe('El Centro Regional enfrenta retos en infraestructura.')
        ->and($project['responsible']['name'])->toBe('William Miguel González Rodríguez')
        ->and($project['responsible']['email'])->toBe('direccion@crenfcp.edu.mx')
        ->and($project['projects'])->toHaveCount(1)
        ->and($project['projects'][0]['number'])->toBe('1')
        ->and($project['projects'][0]['goals'][0]['number'])->toBe('1.1')
        ->and($project['projects'][0]['goals'][0]['requested_total_cents'])->toBe(718300000)
        ->and($project['projects'][0]['goals'][0]['actions'][0]['number'])->toBe('1.1.1')
        ->and($project['projects'][0]['goals'][0]['actions'][0]['items'])->toHaveCount(2)
        ->and($project['projects'][0]['goals'][0]['actions'][0]['items'][0])->toMatchArray([
            'expense_concept' => 'Construcción',
            'expense_item' => 'Aulas',
            'period' => 4,
            'quantity' => 2,
            'unit_price_cents' => 230000000,
            'total_cents' => 460000000,
        ]);
});

test('it keeps multiline project goal action names and wrapped expense items from the federal PDF', function () {
    $text = <<<'TEXT'
    Proyecto Especiﬁco Estatal
    TOTAL GENERAL DEL PROYECTO: $31,296,825.00
    Proyecto General: 0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto
    Obje vo general del Proyecto General: Fortalecer la ges ón ins tucional.
    Jus ﬁcación del Proyecto General: Justificación general.
    Datos del responsable:
    Nombre: William Miguel González Rodríguez
    Cargo: Director
    Grado Académico: Maestría
    Teléfono: 9838671071
    Correo electrónico: direccion@crenfcp.edu.mx

     Proyecto: 1. Fortalecer de manera gradual y sostenida las condiciones sicas, funcionales y de equipamiento de los espacios
     académicos y de servicios del CREN-FCP.
     Subtotal del Proyecto: $17,993,000.00
     Jus ﬁcación: Infraestructura educa va prioritaria.
     Meta: 1.1 Ampliar la capacidad instalada del CREN-FCP mediante la conclusión, habilitación y equipamiento integral de
     infraestructura académica prioritaria
     Subtotal de la Meta: $7,183,000.00
     Acción: 1.1.1 Conclusión y habilitación de dos aulas académicas
     Jus ﬁcación 2026: Realizar acciones de obra civil.
     RECURSOS 2026
     Concepto de gasto Rubro de gasto P Can dad Precio unitario Total
     Construcción Aulas 4 2 $2,300,000 $4,600,000
     Total $4,600,000.00

    Proyecto: 2. Diseñar, implementar y consolidar un programa de cooperación y movilidad académica nacional e internacional en
    el CREN que, durante un periodo de cinco años, garan ce la par cipación progresiva y equita va de las y los estudiantes
    normalistas en experiencias forma vas de intercambio, fortaleciendo sus saberes profesionales e interculturales, generando un
    impacto sostenido en la mejora de la excelencia académica, así como en la innovación educa va y la proyección ins tucional.
    Subtotal del Proyecto: $1,748,350.00
    Jus ﬁcación: La movilidad fortalece la formación.
    Meta: 2.1 Colaboración y vinculación con escuelas normales e Ins tuciones de Educación Superior con enfoque intercultural,
    plurilingüe y comunitario.
    Subtotal de la Meta: $264,050.00
    Acción: 2.1.1 Visitas académicas con tres docentes por año durante cinco años a par r de ac vidades de vinculación en cinco Estados del contexto nacional para
    establecer redes de colaboración académica con ins tuciones.
    Jus ﬁcación 2026: Las visitas de vinculación permiten a los docentes crear una red de colaboración.
    RECURSOS 2026
    Concepto de gasto Rubro de gasto P Can dad Precio unitario Total
    Servicios personales Alimentos (desayunos, comidas, 3 3 $5,025 $15,075
    cenas)
    Servicios personales Noches de hotel (hospedaje) 3 3 $5,400 $16,200
    Total $31,275.00

    Proyecto: 3. Integrar de manera ar culada procesos con necesidades iden ficadas, conec vidad, espacios sicos y múl ples recursos.
    Subtotal del Proyecto: $1,000.00
    Jus ﬁcación: Fortalecer la sistema zación para sistema zar un compara vo con operación administra va, material didác co y fosa sép ca.
    Meta: 3.1 Meta de prueba
    Subtotal de la Meta: $1,000.00
    Acción: 3.1.1 Acción de prueba
    Jus ﬁcación 2026: Acción.
    RECURSOS 2026
    Concepto de gasto Rubro de gasto P Can dad Precio unitario Total
    Insumos consumibles Publicaciones 1 1 $100 $100
    Insumos consumibles Artículos de papelería 1 2 $100 $200
    Insumos consumibles Accesorios para oficina (genérico) 2 3 $100 $300
    Insumos consumibles Artículos de oficina 2 4 $100 $400
    Total $1,000.00
    TEXT;

    $project = (new U300ProjectParser)->parse($text);

    expect($project['projects'])->toHaveCount(3)
        ->and($project['projects'][0]['number'])->toBe('1')
        ->and($project['projects'][1]['name'])->toBe('Diseñar, implementar y consolidar un programa de cooperación y movilidad académica nacional e internacional en el CREN que, durante un periodo de cinco años, garantice la participación progresiva y equitativa de las y los estudiantes normalistas en experiencias formativas de intercambio, fortaleciendo sus saberes profesionales e interculturales, generando un impacto sostenido en la mejora de la excelencia académica, así como en la innovación educativa y la proyección institucional.')
        ->and($project['projects'][1]['goals'][0]['description'])->toBe('Colaboración y vinculación con escuelas normales e Instituciones de Educación Superior con enfoque intercultural, plurilingüe y comunitario.')
        ->and($project['projects'][1]['goals'][0]['actions'][0]['name'])->toBe('Visitas académicas con tres docentes por año durante cinco años a partir de actividades de vinculación en cinco Estados del contexto nacional para establecer redes de colaboración académica con instituciones.')
        ->and($project['projects'][2]['name'])->toBe('Integrar de manera articulada procesos con necesidades identificadas, conectividad, espacios físicos y múltiples recursos.')
        ->and($project['projects'][2]['justification'])->toBe('Fortalecer la sistematización para sistematizar un comparativo con operación administrativa, material didáctico y fosa séptica.')
        ->and($project['projects'][2]['goals'][0]['actions'][0]['items'])->toHaveCount(4)
        ->and($project['projects'][2]['goals'][0]['actions'][0]['items'][0])->toMatchArray([
            'expense_concept' => 'Insumos consumibles',
            'expense_item' => 'Publicaciones',
        ])
        ->and($project['projects'][2]['goals'][0]['actions'][0]['items'][1])->toMatchArray([
            'expense_concept' => 'Insumos consumibles',
            'expense_item' => 'Artículos de papelería',
        ])
        ->and($project['projects'][2]['goals'][0]['actions'][0]['items'][2])->toMatchArray([
            'expense_concept' => 'Insumos consumibles',
            'expense_item' => 'Accesorios para oficina (genérico)',
        ])
        ->and($project['projects'][2]['goals'][0]['actions'][0]['items'][3])->toMatchArray([
            'expense_concept' => 'Insumos consumibles',
            'expense_item' => 'Artículos de oficina',
        ])
        ->and($project['projects'][1]['goals'][0]['actions'][0]['items'])->toHaveCount(2)
        ->and($project['projects'][1]['goals'][0]['actions'][0]['items'][0])->toMatchArray([
            'expense_concept' => 'Servicios personales',
            'expense_item' => 'Alimentos (desayunos, comidas, cenas)',
            'period' => 3,
            'quantity' => 3,
            'unit_price_cents' => 502500,
            'total_cents' => 1507500,
        ]);
});
