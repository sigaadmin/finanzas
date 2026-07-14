# Presupuesto de Ingresos Propios — Hoja de ruta de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar gradualmente el módulo anual de Presupuesto de Ingresos Propios aprobado en la especificación, manteniendo cada entrega pequeña, verificable y utilizable.

**Architecture:** La base de datos será la fuente de verdad. Los archivos XLSX serán adaptadores de entrada y salida. La propuesta, el presupuesto inicial autorizado, el presupuesto modificado, el ejercicio y el control operativo de combustible permanecerán como capas separadas y auditables.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Laravel 3, Inertia React 3, React 19, Wayfinder, Tailwind CSS 4, Pest 4 y el catálogo existente `expense_classifications`.

---

## Referencia funcional

La especificación aprobada es `docs/superpowers/specs/2026-07-13-presupuesto-ingresos-propios-design.md`. Si una decisión de implementación contradice ese documento, debe detenerse la tarea y resolverse la diferencia antes de continuar.

## Secuencia de entregas

### Fase 1 — Fundamento anual y configuración

Crear el ejercicio fiscal, sus datos institucionales, parámetros anuales, firmantes, actividades A01–A04 y vínculo con el COG. Permitir crear desde cero o copiar la configuración de un ejercicio anterior, marcando UMA, combustible y COG para revisión. Esta fase termina con un tablero anual navegable y políticas de acceso.

Plan detallado: `docs/superpowers/plans/2026-07-13-presupuesto-ingresos-propios-fase-1.md`.

### Fase 2 — Planeación y versiones de propuesta

Incorporar:

- conceptos de ficha técnica con cantidad/unidad opcionales;
- comisiones, participantes, tarifas, zonas y UMA;
- recorridos y necesidades planeadas de combustible;
- redondeo al peso superior y al siguiente múltiplo de $50;
- aplicación presupuestal del combustible en abril;
- versiones de propuesta, recálculo por UMA y distribución de recortes;
- conciliación por actividad, partida y mes.

El resultado será una propuesta íntegra capturable dentro de la plataforma, todavía sin importación de Excel.

### Fase 3 — Importación, conciliación, autorización y exportación XLSX

Incorporar adaptadores por formato para combustible, viáticos, ficha técnica, hoja de trabajo y ABPRE. La importación reconocerá encabezados normalizados, presentará vista previa y diferencias, y guardará los originales privadamente. ABPRE confirmará el presupuesto inicial inmutable.

La exportación regenerará versiones oficiales limpias de los cinco archivos. Antes de ejecutar esta fase se debe solicitar autorización explícita para agregar un escritor XLSX real, previsiblemente `phpoffice/phpspreadsheet`; el proyecto actualmente no incluye uno y sus reglas prohíben modificar dependencias sin aprobación.

Diseño detallado de importación: `docs/superpowers/specs/2026-07-13-presupuesto-ingresos-propios-importacion-xlsx-design.md`.

Primer incremento ejecutable —infraestructura común, cinco espacios de carga e importador ABPRE—: `docs/superpowers/plans/2026-07-13-presupuesto-ingresos-propios-xlsx-fase-3a.md`. Los adaptadores de hoja de trabajo, ficha técnica, combustible y viáticos se desarrollarán sobre esa infraestructura en planes independientes.

### Fase 4 — Presupuesto modificado y expedientes de gasto

Incorporar:

- transferencias entre partidas del mismo capítulo y mes;
- recalendarizaciones de la misma partida hacia un mes futuro;
- saldos inicial, modificado, reservado, comprometido, pagado y disponible;
- flujo de suficiencia, compra, solicitudes y autorizaciones;
- rechazo/cancelación con liberación de saldos;
- listas de verificación condicionales, excepciones administrativas y evidencias privadas.

Esta fase debe usar bloqueos transaccionales para impedir sobreejercicio concurrente.

### Fase 5 — Control operativo de combustible, reportes y cierre

Abrir el fondo operativo a partir del valor realmente adquirido en abril. Registrar comisiones planeadas o extraordinarias, vehículo, recorrido, litros, importe y saldo posterior, sin folios de vales. Agregar reportes comparativos de planeación, autorización, ejercicio, pendientes y disponibilidad; auditoría y cierre anual.

## Dependencias entre fases

```mermaid
flowchart LR
    F1["Fase 1: ejercicio anual"] --> F2["Fase 2: planeación"]
    F2 --> F3["Fase 3: XLSX y autorización"]
    F3 --> F4["Fase 4: transferencias y gasto"]
    F3 --> F5["Fase 5: combustible operativo"]
    F4 --> F5
```

## Reglas transversales de ejecución

- [ ] Antes de cada cambio de código, usar Laravel Boost `search-docs` con consultas amplias y los paquetes correspondientes.
- [ ] Activar las habilidades del dominio que corresponda: Laravel, Inertia React, Wayfinder, Tailwind y Pest.
- [ ] Trabajar mediante TDD: prueba roja, implementación mínima, prueba verde y refactorización.
- [ ] Crear archivos Laravel con `php artisan make:* --no-interaction` cuando exista un generador apropiado.
- [ ] Reutilizar convenciones y componentes de U300 sin acoplar ambos dominios.
- [ ] Mantener las rutas bajo `finance.own-revenue.*` y las clases de dominio bajo `Finance/OwnRevenue`.
- [ ] Ejecutar las pruebas específicas después de cada tarea.
- [ ] Ejecutar `vendor/bin/pint --dirty --format agent` después de modificar PHP.
- [ ] Ejecutar `npm run types:check`, `npm run lint:check` y `npm run build` en los puntos de control frontend.
- [ ] No modificar ni incluir en commits cambios ajenos ya existentes en el árbol de trabajo.
- [ ] Crear un commit pequeño por tarea terminada.

## Criterio de terminación global

El módulo se considerará terminado cuando sea posible crear o copiar un ejercicio, planear y ajustar todas sus necesidades, importar y regenerar los cinco formatos, confirmar el presupuesto inicial, registrar transferencias y expedientes sin sobreejercicio, controlar el fondo de combustible y reconstruir toda decisión mediante su historial de auditoría.
