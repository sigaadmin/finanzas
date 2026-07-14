# Corrección de confirmación de archivos complementarios

Fecha: 2026-07-14  
Estado: aprobado para implementación

## Objetivo

Corregir los cuatro hallazgos altos de la auditoría del commit `da0a6dc` para que Ficha técnica, Combustible y Viáticos sólo puedan confirmarse con un análisis vigente, advertencias resueltas y referencias históricas suficientes.

Esta corrección no asignará actividades ni conciliará importes. La Hoja de trabajo continuará siendo la autoridad para actividad y calendarización.

## Alcance

1. Exigir una huella de análisis vigente en toda confirmación complementaria.
2. Registrar decisiones auditables para las advertencias `year.mismatch` y `region.normalized`.
3. Conservar en las necesidades de Ficha técnica la relación con el COG y una referencia histórica de la partida.
4. Comparar el ejercicio detectado en el archivo con el ejercicio presupuestal durante el análisis.

Queda fuera de esta corrección el hallazgo medio sobre la nulabilidad de cantidad y unidad.

## Análisis y ejercicio fiscal

El análisis de los tres formatos recibirá el ejercicio fiscal y el año detectado para el archivo. Cuando ambos existan y sean distintos, creará una advertencia `year.mismatch` con:

- ejercicio detectado;
- ejercicio presupuestal;
- indicador de que requiere una decisión;
- revisión del análisis a la que pertenece la futura aceptación.

La ausencia de un año detectable no se considerará por sí sola un error. La confirmación volverá a comprobar que el año detectado no cambió respecto del análisis persistido.

## Decisiones auditables

Se ampliará el flujo existente de decisiones de importación para admitir, además de `work_sheet.abpre_mismatch`, las advertencias complementarias:

- `year.mismatch`;
- `region.normalized`.

La decisión conservará incidencia, revisión del análisis, aceptación o rechazo, justificación, usuario y fecha. Una aceptación sólo será válida para la revisión vigente. Reanalizar el archivo invalidará las decisiones anteriores mediante el cambio de `analysis_revision`.

La vista previa complementaria mostrará las advertencias que requieren decisión. Permitirá aceptar o rechazar cada una y explicará que la confirmación permanecerá deshabilitada mientras falte una aceptación vigente. La confirmación genérica del navegador seguirá siendo la última confirmación de la operación, pero no sustituirá las decisiones auditables.

## Vigencia e integridad del análisis

La confirmación exigirá simultáneamente:

- estado `ready`;
- `analysis_revision` coincidente;
- `analysis_fingerprint` presente y coincidente con una nueva captura;
- presupuesto sin cambios desde el análisis;
- archivo privado disponible y con el mismo SHA-256;
- ausencia de errores;
- aceptación vigente de cada advertencia que requiera decisión.

Una huella nula será tratada como análisis vencido y obligará a analizar nuevamente el archivo.

## Referencia histórica del COG

Una migración nueva agregará a `own_revenue_technical_sheet_needs`:

- `expense_classification_id`, con llave foránea restrictiva;
- `specific_item_name`;
- `chapter_code`;
- `chapter_name`.

Dentro de la transacción, cada partida se resolverá nuevamente contra el COG del ejercicio. Si ya no existe o no coincide con el análisis vigente, la confirmación se rechazará. El registro confirmado guardará tanto la relación al catálogo como los campos históricos para que una actualización futura no cambie su interpretación.

## Compatibilidad y migración

No se modificará la migración ya aplicada. Se añadirá una migración reversible. Como todavía no existen registros complementarios confirmados en el entorno actual, las nuevas columnas podrán introducirse sin una conversión de datos históricos.

Los archivos listos que tengan huella válida conservarán su revisión. Los archivos sin huella deberán analizarse nuevamente. Un nuevo análisis regenerará las advertencias y requerirá decisiones nuevas.

## Pruebas

Las pruebas deberán demostrar primero las fallas y después verificar:

- rechazo de una huella ausente o vencida;
- generación de `year.mismatch` en los tres formatos;
- rechazo de confirmación mientras falte una decisión requerida;
- persistencia de usuario, fecha, justificación y revisión en cada decisión;
- invalidez de decisiones pertenecientes a otra revisión;
- persistencia de FK y fotografía histórica del COG;
- rechazo si la partida cambia o desaparece después del análisis;
- reemplazo correcto de una versión confirmada;
- autorización del endpoint y aislamiento entre presupuesto, archivo e incidencia;
- presentación y controles de decisiones en la vista previa complementaria.

La verificación final incluirá pruebas PHP focalizadas, suite completa, pruebas frontend, tipos, lint, formato y compilación.
