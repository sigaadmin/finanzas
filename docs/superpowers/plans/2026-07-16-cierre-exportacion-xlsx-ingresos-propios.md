# Cierre de exportación XLSX de Ingresos Propios — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar, auditar y descargar los cinco XLSX oficiales desde el presupuesto inicial autorizado, con región `02-001` y sin depender de edición manual posterior.

**Architecture:** Una acción transaccional validará el snapshot autorizado y delegará en un adaptador por formato. Los adaptadores cargarán una plantilla de referencia privada cuando exista; Ficha técnica, Combustible y Viáticos producirán una sola hoja con columna `Actividad`, conforme a la decisión aprobada. Cada resultado se almacenará en el disco privado y quedará registrado con hash, total, usuario y fecha.

**Tech Stack:** Laravel 13, PHP 8.5, PhpSpreadsheet 5, Inertia React 3, Wayfinder y Pest 4.

---

## Decisiones cerradas

- Los cinco formatos son `abpre`, `work_sheet`, `technical_sheet`, `fuel` y `travel_expenses`.
- La región es siempre `02-001 — Felipe Carrillo Puerto`.
- Ficha técnica, Combustible y Viáticos usan una sola hoja y muestran `Actividad`.
- El archivo `FORMATO CALENDARIO METAS POR REGIÓN` no sustituye a Viáticos en este incremento.
- Los archivos confirmados son evidencia inmutable; el exportador crea un libro nuevo y sólo toma de ellos estructura visual cuando corresponda.

### Tarea 1: Corregir el contrato de exportación y descarga

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Exports/OwnRevenueWorkbookExporter.php`
- Modify: `app/Http/Controllers/Finance/OwnRevenueWorkbookExportController.php`
- Modify: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueInitialBudget.php`
- Modify: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueWorkbookExport.php`
- Test: `tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueWorkbookExportTest.php`

- [ ] Escribir pruebas que exijan estado `initial_authorized`, permiso administrativo, formato permitido, transacción, archivo privado, hash verificable y respuesta de descarga compatible.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueWorkbookExportTest.php` y comprobar el fallo por ausencia de la acción de generación.
- [ ] Crear `GenerateOwnRevenueWorkbookExport` con `DB::transaction(..., attempts: 3)`, bloqueo del presupuesto inicial y selección exhaustiva del adaptador.
- [ ] Corregir la descarga para devolver `Symfony\Component\HttpFoundation\StreamedResponse` y validar que el archivo registrado existe.
- [ ] Ejecutar la prueba hasta obtener verde y guardar un commit pequeño.

### Tarea 2: ABPRE y Hoja de trabajo fieles al snapshot

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Exports/AbpreWorkbookExporter.php`
- Modify: `app/Services/Finance/OwnRevenue/Exports/WorkSheetWorkbookExporter.php`
- Create: `app/Services/Finance/OwnRevenue/Exports/WorkbookTemplate.php`
- Test: `tests/Unit/Finance/OwnRevenue/Exports/AbpreWorkbookExporterTest.php`
- Test: `tests/Unit/Finance/OwnRevenue/Exports/WorkSheetWorkbookExporterTest.php`

- [ ] Crear fixtures mínimos que reproduzcan hojas, encabezados, estilos y fórmulas observados en las muestras oficiales.
- [ ] Probar columnas institucionales, partidas como texto, meses enero–diciembre, anual, totales exactos y región fija.
- [ ] Implementar libros nuevos conservando sólo estructura/estilos de la plantilla y reemplazando datos con el snapshot autorizado.
- [ ] Abrir el resultado nuevamente con PhpSpreadsheet y comprobar valores y fórmulas.
- [ ] Ejecutar ambas pruebas y guardar un commit.

### Tarea 3: Ficha técnica unificada

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Exports/TechnicalSheetWorkbookExporter.php`
- Test: `tests/Unit/Finance/OwnRevenue/Exports/TechnicalSheetWorkbookExporterTest.php`

- [ ] Probar una sola hoja con Actividad, partida, descripción, cantidad, unidad, precio, importe, mes, impacto en metas y justificación.
- [ ] Implementar el mapeo desde `snapshot.technical_needs`, formatos monetarios, encabezado institucional y totales.
- [ ] Verificar apertura, tipos, suma total y región `02-001`.
- [ ] Guardar un commit con prueba verde.

### Tarea 4: Combustible unificado

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Exports/FuelWorkbookExporter.php`
- Test: `tests/Unit/Finance/OwnRevenue/Exports/FuelWorkbookExporterTest.php`

- [ ] Probar una sola hoja con Actividad, fecha/mes, motivo, vehículo, rendimiento, recorridos, kilómetros, litros, precio, cálculo, redondeo e importe.
- [ ] Implementar el mapeo desde `snapshot.fuel_needs`; el mes presupuestal será abril y la región `02-001`.
- [ ] Verificar fórmulas, múltiplos de $50, total y lectura del archivo.
- [ ] Guardar un commit con prueba verde.

### Tarea 5: Viáticos unificado

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Exports/TravelExpensesWorkbookExporter.php`
- Test: `tests/Unit/Finance/OwnRevenue/Exports/TravelExpensesWorkbookExporterTest.php`

- [ ] Probar una sola hoja con Actividad, comisión, fechas, destino, participante, cargo, días, zonas, UMA, alimentación, hospedaje, vuelo y total.
- [ ] Implementar una fila por participante desde `snapshot.travel_commissions`, repitiendo los datos comunes de la comisión.
- [ ] Verificar subtotales, total anual, región fija y compatibilidad de lectura.
- [ ] Guardar un commit con prueba verde.

### Tarea 6: Rutas, interfaz e historial

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Exports/GenerateOwnRevenueWorkbookExport.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/Exports/GenerateOwnRevenueWorkbookExportRequest.php`
- Modify: `app/Http/Controllers/Finance/OwnRevenueWorkbookExportController.php`
- Modify: `routes/web.php`
- Modify: `app/Services/Finance/OwnRevenue/Planning/OwnRevenuePlanningViewData.php`
- Modify: `resources/js/pages/finance/own-revenue/planning/show.tsx`
- Modify: `resources/js/types/finance-own-revenue.ts`
- Test: `tests/Feature/Finance/OwnRevenue/Exports/OwnRevenueWorkbookExportNavigationTest.php`
- Test: `tests/Frontend/own-revenue-workbook-export-state.test.mjs`

- [ ] Probar que sólo un presupuesto autorizado muestra los cinco botones y el historial descargable.
- [ ] Agregar POST de generación y GET de descarga mediante Wayfinder, sin abrir una ventana nueva.
- [ ] Mostrar formato, fecha, usuario, total y descarga; usar mensajes operativos y ocultar mutaciones a auditores.
- [ ] Regenerar Wayfinder y ejecutar pruebas PHP/frontend.
- [ ] Guardar un commit.

### Tarea 7: Verificación integral y cierre

**Files:**
- Verify all files above.

- [ ] Ejecutar `vendor/bin/pint --dirty --format agent`.
- [ ] Ejecutar `php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports tests/Unit/Finance/OwnRevenue/Exports tests/Feature/Finance/OwnRevenue/Planning`.
- [ ] Ejecutar `npm run test:frontend`, `npm run types:check`, ESLint focalizado y `npm run build`.
- [ ] Generar los cinco libros desde un fixture autorizado, reabrirlos y comparar sus totales con el snapshot.
- [ ] Auditar el diff, confirmar que no se modifican archivos importados ni plantillas y preparar integración local.

