# Presupuesto de Ingresos Propios — Plan de implementación de la fase 1

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar el fundamento anual del módulo: crear, consultar, editar y copiar ejercicios de Ingresos Propios con configuración institucional, UMA, combustible, región fija, firmantes, actividades y COG anual.

**Architecture:** Un agregado `OwnRevenueBudget` representa el ejercicio fiscal. Conserva instantáneas institucionales y parámetros propios del año; sus actividades y firmantes son registros hijos. El catálogo `expense_classifications` continúa siendo compartido con U300 y se copia por año mediante una acción transaccional idempotente. Las políticas separan consulta, operación y administración.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, Inertia Laravel 3, Inertia React 3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

## Alcance de esta fase

Incluye:

- listado y tablero de ejercicios;
- creación vacía o copia de configuración desde otro año;
- datos institucionales y parámetros anuales;
- región forzada `02-001 — Felipe Carrillo Puerto`;
- UMA provisional/definitiva y precio de combustible;
- mes presupuestal de combustible fijado en abril;
- actividades informativas A01–A04;
- firmantes ordenados;
- copia del último COG disponible y confirmación manual de su vigencia;
- autorización por roles y auditoría básica de creación/actualización.

No incluye todavía necesidades, comisiones, recorridos, propuestas, recortes, Excel, presupuesto autorizado, transferencias ni ejercicio.

## Convenciones decididas

- Tablas: prefijo `own_revenue_`.
- Namespace PHP de dominio: `App\Models\Finance\OwnRevenue`, `App\Actions\Finance\OwnRevenue` y `App\Enums\Finance\OwnRevenue`.
- Páginas: `resources/js/pages/finance/own-revenue/budgets`.
- Rutas: `finance.own-revenue.budgets.*`.
- Importes monetarios futuros: centavos enteros. UMA y precio por litro: decimales con cuatro posiciones para conservar precisión de cálculo.
- `FinanceAssistant` puede consultar y posteriormente operar la planeación, pero no crear ejercicios, cambiar configuración anual ni confirmar el COG.
- `FinanceAuditor` sólo consulta.
- `Owner`, `Admin` y `FinanceManager` administran el ejercicio.

## Task 1: Confirmar patrones y documentación aplicable

**Files:**
- Read: `app/Models/Finance/U300/U300Program.php`
- Read: `app/Policies/ChargeConceptPolicy.php`
- Read: `app/Http/Controllers/Finance/U300ProgramController.php`
- Read: `resources/js/pages/finance/u300/programs/index.tsx`
- Read: `tests/Feature/Finance/U300ProgramIndexTest.php`
- Read: `routes/web.php`

- [ ] Activar `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `tailwindcss-development` y `pest-testing` antes de tocar código de sus dominios.
- [ ] Usar Laravel Boost `search-docs` con `['model fillable attribute casts relationships', 'database migrations foreign keys transactions lock for update', 'policies authorize resource controllers']` para `laravel/framework`.
- [ ] Usar Laravel Boost `search-docs` con `['forms validation errors', 'partial reloads preserve state', 'typed page props']` para `inertiajs/inertia-laravel` y `@inertiajs/react`.
- [ ] Verificar los patrones reales de modelos, fábricas, controladores, Form Requests, políticas, rutas y pruebas indicados arriba.
- [ ] Registrar cualquier discrepancia entre este plan y las convenciones actuales antes de continuar; ajustar nombres, no reglas funcionales aprobadas.

## Task 2: Crear enums, esquema anual y modelos

**Files:**
- Create: `app/Enums/Finance/OwnRevenue/OwnRevenueBudgetStatus.php`
- Create: `app/Enums/Finance/OwnRevenue/AnnualValueStatus.php`
- Create: `app/Enums/Finance/OwnRevenue/CogCatalogStatus.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_own_revenue_budgets_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_own_revenue_activities_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_own_revenue_signatories_table.php`
- Create: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Create: `app/Models/Finance/OwnRevenue/OwnRevenueActivity.php`
- Create: `app/Models/Finance/OwnRevenue/OwnRevenueSignatory.php`
- Create: `database/factories/Finance/OwnRevenue/OwnRevenueBudgetFactory.php`
- Create: `database/factories/Finance/OwnRevenue/OwnRevenueActivityFactory.php`
- Create: `database/factories/Finance/OwnRevenue/OwnRevenueSignatoryFactory.php`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetSchemaTest.php`

- [ ] Generar los modelos con `php artisan make:model Finance/OwnRevenue/OwnRevenueBudget -mf --no-interaction`, `php artisan make:model Finance/OwnRevenue/OwnRevenueActivity -mf --no-interaction` y `php artisan make:model Finance/OwnRevenue/OwnRevenueSignatory -mf --no-interaction`.
- [ ] Generar los enums con `php artisan make:enum Finance/OwnRevenue/OwnRevenueBudgetStatus --no-interaction`, `php artisan make:enum Finance/OwnRevenue/AnnualValueStatus --no-interaction` y `php artisan make:enum Finance/OwnRevenue/CogCatalogStatus --no-interaction`; consultar `php artisan list make` si el generador cambia en Laravel 13.
- [ ] Escribir primero pruebas que exijan año fiscal único, casts de enums/decimales, relaciones, región fija y fábricas válidas. Afirmar explícitamente que los casts Eloquent `decimal:4` de UMA y precio de combustible devuelven cadenas exactas —incluido el relleno de escala, por ejemplo entrada `'123.45'` → lectura `'123.4500'` y `'24.9876'` → `'24.9876'`—, nunca `float`, para conservar precisión y no depender de la coerción de SQLite.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetSchemaTest.php` y comprobar que falla por las tablas o clases ausentes.
- [ ] Crear `own_revenue_budgets` con: creador, ejercicio único, estado, datos de UR/programa/componente/actividad oficial, región, ingresos estimados opcionales, porcentaje de recorte opcional, UMA, estado de UMA, precio de combustible, estado del precio, mes presupuestal de combustible, año fuente del COG, estado del COG, usuario/fecha de confirmación del COG y timestamps.
- [ ] Usar `decimal(12, 4)` para UMA/precio y `unsignedBigInteger` en centavos para importes; no usar `float`.
- [ ] Añadir restricciones que permitan únicamente región `02-001` y mes `4` desde la aplicación y valores predeterminados equivalentes en la migración.
- [ ] Crear hijos `own_revenue_activities` con código único por ejercicio, nombre y orden; y `own_revenue_signatories` con clave de función, nombre, cargo, grado académico y orden.
- [ ] Aplicar `#[Fillable]`, relaciones tipadas, casts, PHPDoc genérico de `HasFactory` y nombres descriptivos siguiendo los modelos U300; declarar UMA y precio de combustible con casts `decimal:4`, cuyo contrato de lectura es `string|null`, y hacer que las factories proporcionen estos valores como cadenas decimales.
- [ ] Volver a ejecutar la prueba hasta verla pasar.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Add own revenue annual budget foundation"`.

## Task 3: Encapsular valores predeterminados y reglas del ejercicio

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/InitializeOwnRevenueBudget.php`
- Create: `app/Actions/Finance/OwnRevenue/UpdateOwnRevenueBudgetSettings.php`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetSettingsTest.php`

- [ ] Escribir pruebas para crear las actividades `A01` Fomento de la investigación, `A02` Profesorado y docencia, `A03` Difusión y `A04` Gestión exactamente una vez y en ese orden.
- [ ] Probar que toda inicialización fuerza región `02-001`, nombre `Felipe Carrillo Puerto` y mes de combustible `4`, aunque una carga maliciosa intente otros valores.
- [ ] Probar que UMA y combustible requieren valores positivos, guardan estado provisional/definitivo y sólo se actualizan mientras el ejercicio esté en borrador.
- [ ] Ejecutar la prueba y verificar el fallo esperado por acciones ausentes.
- [ ] Implementar la creación y actualización dentro de transacciones, dejando las reglas fijas fuera de los datos suministrados por el navegador.
- [ ] No incluir todavía transiciones de estado distintas de `Draft`; el enum puede declarar el ciclo aprobado para evitar migraciones posteriores.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetSettingsTest.php`.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Enforce own revenue annual settings"`.

## Task 4: Copiar y confirmar el COG anual

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/CopyExpenseClassificationsForYear.php`
- Create: `app/Actions/Finance/OwnRevenue/ConfirmOwnRevenueCogCatalog.php`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueCogCatalogTest.php`

- [ ] Escribir pruebas para copiar al nuevo ejercicio todas las partidas del año fuente conservando la jerarquía completa.
- [ ] Probar que la acción elige el año más reciente anterior cuando no se especifica fuente, registra `cog_source_year` y deja el catálogo `PendingConfirmation`.
- [ ] Probar idempotencia: una segunda ejecución no duplica partidas ni cambia registros ya existentes.
- [ ] Probar que no encontrar ningún COG anterior produce un error de dominio legible, no deja partidas parciales y conserva el ejercicio como `PendingConfirmation`.
- [ ] Probar que sólo se puede confirmar el COG cuando el ejercicio destino contiene partidas y que la confirmación guarda usuario y fecha.
- [ ] Ejecutar la prueba y verificar el fallo por acciones ausentes.
- [ ] Implementar la copia con transacción, inserción por lotes y la restricción única existente `(fiscal_year, specific_item_code)`; no crear un segundo catálogo.
- [ ] Evitar `updateOrCreate` que pueda sobrescribir silenciosamente un catálogo destino ya revisado; si existen diferencias, devolver un conflicto explícito.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueCogCatalogTest.php`.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Add annual COG copy and confirmation"`.

## Task 5: Copiar configuración desde un ejercicio anterior

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/CopyOwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/CopyOwnRevenueBudgetTest.php`

- [ ] Escribir pruebas para copiar datos institucionales, firmantes y actividades hacia un año nuevo.
- [ ] Probar que el ejercicio destino queda `Draft`; UMA, precio de combustible y COG quedan `PendingReview`/`PendingConfirmation`, incluso si eran definitivos en el origen.
- [ ] Probar que fechas, ejercicio, creador y año fuente se actualizan y que no se copia ninguna entidad de ejecución futura.
- [ ] Probar que no puede copiarse sobre un ejercicio ya existente ni desde el mismo año.
- [ ] Ejecutar la prueba y observar el fallo por la acción ausente.
- [ ] Implementar una transacción que reutilice `InitializeOwnRevenueBudget` y `CopyExpenseClassificationsForYear`; actualizar las cuatro actividades desde la instantánea de origen por código, sin duplicarlas ni duplicar reglas de región o COG.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/CopyOwnRevenueBudgetTest.php`.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Support copying annual own revenue settings"`.

## Task 6: Definir autorización por rol

**Files:**
- Create: `app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetAuthorizationTest.php`

- [ ] Generar la política con `php artisan make:policy Finance/OwnRevenue/OwnRevenueBudgetPolicy --model=Finance/OwnRevenue/OwnRevenueBudget --no-interaction` o el argumento compatible mostrado por `--help`; conservar el namespace `App\Policies\Finance\OwnRevenue` para que Laravel 13 descubra la política del modelo anidado por convención.
- [ ] Escribir una matriz Pest para `Owner`, `Admin`, `FinanceManager`, `FinanceAssistant`, `FinanceAuditor`, `Public` y usuario sin acceso.
- [ ] Probar explícitamente el descubrimiento automático con `Gate::getPolicyFor(OwnRevenueBudget::class)` y comprobar que devuelve una instancia de `App\Policies\Finance\OwnRevenue\OwnRevenueBudgetPolicy`; ejercer además al menos una habilidad mediante usuarios `Admin` y `FinanceManager`, no mediante `Owner`, para que el `Gate::before` global de Owner no pueda ocultar una policy ausente o ubicada en un namespace incorrecto.
- [ ] Exigir que manager/admin/owner puedan crear, copiar, configurar y confirmar COG; assistant y auditor puedan ver; public y usuario sin acceso no puedan entrar.
- [ ] Probar que `FinanceAssistant` no administra parámetros aunque `operate-finance` le permita entrar al área general.
- [ ] Ejecutar la prueba y comprobar el fallo esperado.
- [ ] Implementar métodos explícitos `viewAny`, `view`, `create`, `updateSettings`, `copy` y `confirmCog`; no depender sólo de ocultar botones.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetAuthorizationTest.php`.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Authorize own revenue annual budgets"`.

## Task 7: Exponer endpoints Inertia y validación

**Files:**
- Create: `app/Http/Controllers/Finance/OwnRevenueBudgetController.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueCogConfirmationController.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/StoreOwnRevenueBudgetRequest.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/UpdateOwnRevenueBudgetRequest.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/ConfirmOwnRevenueCogRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetManagementTest.php`

- [ ] Generar controladores y Form Requests con Artisan y `--no-interaction`.
- [ ] Escribir pruebas HTTP para índice, alta vacía, alta desde ejercicio previo, detalle, actualización y confirmación del COG.
- [ ] Probar validación de ejercicio de cuatro dígitos, unicidad, UMA/precio positivos, firmantes anidados y año fuente existente/anterior. Enviar UMA y precio como cadenas, limitar su escala a cuatro decimales con reglas explícitas (`decimal:0,4` además de positividad y magnitud compatibles con `decimal(12, 4)`), rechazar cinco decimales y afirmar que las props Inertia normalizan valores como `'123.45'` a `'123.4500'` como `string`, no como números JSON, para evitar pérdida de precisión y diferencias entre SQLite y MySQL.
- [ ] Probar respuestas `403`, errores de validación, redirecciones y mensajes flash.
- [ ] Ejecutar la prueba y verificar que falla porque no existen rutas/controladores.
- [ ] Añadir rutas explícitas bajo `finance/own-revenue/budgets` con nombres `finance.own-revenue.budgets.*`, preservando cuidadosamente los cambios no relacionados que ya existan en `routes/web.php`.
- [ ] Mantener los controladores delgados: autorizar, validar, llamar acciones y responder con Inertia/redirección.
- [ ] Proveer al frontend estados, permisos, posibles años fuente y conteo/estado del COG; no exponer modelos completos sin transformar. Mapear UMA y precio de combustible como las cadenas producidas por sus casts `decimal:4`, sin convertirlos a `float` antes de construir las props.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetManagementTest.php`.
- [ ] Regenerar Wayfinder mediante el comando ya configurado por el proyecto.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Commit: `git commit -m "Expose own revenue annual budget endpoints"`.

## Task 8: Construir listado, formulario y tablero anual

**Files:**
- Create: `resources/js/pages/finance/own-revenue/budgets/index.tsx`
- Create: `resources/js/pages/finance/own-revenue/budgets/create.tsx`
- Create: `resources/js/pages/finance/own-revenue/budgets/show.tsx`
- Create: `resources/js/components/finance/own-revenue/annual-settings-form.tsx`
- Create: `resources/js/types/finance-own-revenue.ts`
- Modify: `resources/js/components/app-sidebar.tsx`
- Test: `tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetNavigationTest.php`

- [ ] Escribir pruebas de navegación que comprueben componente Inertia, props esenciales, permisos y enlace lateral para usuarios autorizados.
- [ ] Ejecutar la prueba y comprobar que falla por páginas/enlace ausentes.
- [ ] Crear un listado por año y estado con acción de “Nuevo ejercicio”.
- [ ] Crear un formulario que permita iniciar vacío o copiar otro año y explique que UMA, combustible y COG copiados requieren revisión. Tipar UMA y precio de combustible como `string|null` en las props y como `string` (`''` para ausencia) en los datos de `useForm`; usar `inputMode="decimal"` y `step="0.0001"`, mantener `event.target.value` y enviarlo sin `Number()`, `parseFloat()` ni otra coerción binaria.
- [ ] Crear el tablero del ejercicio con tarjetas de estado para configuración general, UMA, combustible, COG, firmantes y actividades; mostrar la región fija como dato no editable.
- [ ] Permitir edición de parámetros y firmantes sólo cuando `permissions.updateSettings` sea verdadero; ocultar además de proteger en servidor.
- [ ] Añadir “Presupuesto de Ingresos Propios” al menú lateral usando la ruta Wayfinder generada.
- [ ] Reutilizar componentes UI existentes, mantener controles accesibles y ofrecer estados vacíos claros.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/OwnRevenueBudgetNavigationTest.php`.
- [ ] Afirmar en la prueba Inertia que UMA y precio llegan como cadenas con escala fija —incluidos ceros finales como `'123.4500'`— y ejecutar `npm run types:check` para verificar el contrato `string|null` de props y `string` de formularios.
- [ ] Ejecutar `npm run lint:check`.
- [ ] Ejecutar `npm run format:check` y corregir sólo los archivos de esta tarea si es necesario.
- [ ] Commit: `git commit -m "Add own revenue annual budget screens"`.

## Task 9: Verificar el recorrido completo de la fase

**Files:**
- Modify only if verification finds a defect: files created in Tasks 2–8
- Test: `tests/Feature/Finance/OwnRevenue/*`

- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue`.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent` y repetir las pruebas si Pint cambia PHP.
- [ ] Ejecutar `npm run types:check`.
- [ ] Ejecutar `npm run lint:check`.
- [ ] Ejecutar `npm run build`.
- [ ] Resolver la URL mediante Laravel Boost `get-absolute-url`; no iniciar un servidor porque Herd ya sirve el proyecto.
- [ ] Verificar en navegador como manager: listar, crear 2027 desde cero, editar parámetros, confirmar COG y revisar el tablero.
- [ ] Verificar en navegador la copia 2026 → 2027 usando una base de prueba adecuada y confirmar indicadores de revisión.
- [ ] Verificar como assistant que el tablero sea visible pero la configuración anual no sea modificable.
- [ ] Consultar `browser-logs` y corregir únicamente errores recientes relacionados con esta fase.
- [ ] Revisar `git diff --check` y `git status --short`; confirmar que el commit final no incluye cambios U300 u otros cambios preexistentes del usuario.
- [ ] Commit: `git commit -m "Verify own revenue annual budget foundation"` sólo si la verificación produjo correcciones.

## Criterios de aceptación de la fase 1

- Un administrador financiero puede crear un ejercicio vacío o copiar otro año.
- No pueden existir dos ejercicios de Ingresos Propios para el mismo año.
- Región y mes presupuestal del combustible no pueden desviarse de `02-001` y abril.
- UMA y precio de combustible conservan precisión y estado de revisión.
- A01–A04 y los firmantes pertenecen al ejercicio y son auditables.
- El COG se reutiliza desde `expense_classifications`, puede copiarse sin duplicados y requiere confirmación.
- El manager administra; assistant y auditor consultan; roles externos no acceden.
- El recorrido cuenta con pruebas Pest, comprobación de tipos, lint, build y verificación visual sin errores recientes.
