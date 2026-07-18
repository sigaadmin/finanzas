# Centro de reinicio de datos locales

Fecha: 2026-07-18
Estado: pendiente de revisión escrita

## Propósito

Permitir que el propietario reinicie datos de prueba desde la aplicación local sin manipular directamente SQLite. El centro ofrecerá reinicios independientes para Ventanilla Finanzas, U300 e Ingresos Propios, además de un reinicio general que deje la aplicación sin datos funcionales y vuelva a crear únicamente el acceso institucional propietario.

La función es destructiva, exclusiva del entorno `local` y no se habilitará en producción, pruebas compartidas ni otros ambientes.

## Alcance funcional

Se agregará la opción **Datos locales** dentro de Configuración. La página mostrará cuatro bloques separados:

| Operación | Frase obligatoria | Resultado |
| --- | --- | --- |
| Reiniciar Ventanilla Finanzas | `BORRAR VENTANILLA` | Elimina cobros, trámites, recibos, cancelaciones, depósitos y reportes SEQ. |
| Reiniciar U300 | `BORRAR U300` | Elimina todos los programas U300, su planeación, ejecución, fichas y archivos. |
| Reiniciar Ingresos Propios | `BORRAR INGRESOS PROPIOS` | Elimina presupuestos, importaciones, planeación, ejecución, fondos, comisiones y archivos. |
| Reiniciar toda la aplicación | `REINICIAR TODO` | Elimina todos los datos locales y recrea únicamente al propietario institucional. |

Cada bloque explicará en lenguaje funcional qué se borra y qué se conserva. La acción permanecerá deshabilitada hasta que la frase coincida exactamente. La confirmación no dependerá sólo de la interfaz: el servidor validará nuevamente la frase, el ambiente y el rol.

## Autorización y barreras de seguridad

La protección tendrá capas independientes:

1. Las rutas web sólo se registrarán en el entorno `local`.
2. El servicio de reinicio rechazará cualquier ejecución cuando `App::environment('local')` sea falso, incluso si se invoca fuera del controlador.
3. La pantalla y los endpoints exigirán autenticación y rol `Owner`.
4. Cada petición se validará con un Form Request y exigirá la frase exacta de su operación.
5. El comando Artisan exigirá una selección válida y una confirmación interactiva; `--force` permitirá automatizarlo sólo en `local`.
6. Ninguna operación aceptará nombres libres de tablas o rutas de archivos.

Un administrador, operador o auditor no podrá ver ni ejecutar estas acciones. Una petición no autorizada no producirá borrados parciales.

## Arquitectura

La lógica residirá en un servicio único de aplicación, independiente de HTTP y consola. Recibirá un alcance cerrado (`ventanilla`, `u300`, `own-revenue` o `all`) y devolverá un resultado con el alcance ejecutado, número de registros eliminados y advertencias de archivos.

Lo consumirán dos adaptadores:

- un controlador invocable para Configuración;
- el comando `php artisan finance:reset-local-data {scope}`.

Las listas de tablas, filtros y directorios serán constantes privadas del servicio o de un objeto de alcance dedicado. No se construirá SQL ni rutas a partir de texto proporcionado por el usuario.

La página será Inertia/React y reutilizará el layout de Configuración, los botones, alertas y diálogos existentes. No se añadirá una dependencia de interfaz nueva.

## Flujo de una operación parcial

1. El propietario abre **Configuración > Datos locales**.
2. Escribe la frase del módulo y confirma en un diálogo final.
3. El servidor valida ambiente, autorización, alcance y frase.
4. Dentro de una transacción se eliminan las tablas del módulo en orden inverso a sus dependencias.
5. Al confirmar la transacción se eliminan los directorios exclusivos del módulo.
6. La pantalla se recarga y muestra el resultado.

Si falla la base de datos, la transacción se revierte y no se tocan archivos. Si la base se limpia pero falla un directorio, el reinicio se considera ejecutado con advertencia; una repetición de la misma operación volverá a intentar limpiar las raíces conocidas.

## Flujo del reinicio general

El reinicio general elimina los registros de todas las tablas actuales excepto `migrations`, conservando el esquema instalado. La eliminación se hará en orden de dependencias y dentro de una transacción. Antes de confirmar la transacción se volverán a crear:

- el usuario `administrador.siga@crenfcp.edu.mx` con nombre `Administrador CREN`;
- su registro activo en `authorized_accesses` con rol `Owner`.

No se conservarán otros usuarios, accesos, catálogos ni datos funcionales. Después de confirmar la base se limpiarán todas las raíces de archivos funcionales enumeradas en esta especificación. La sesión web que inició la operación se invalidará y la respuesta irá al acceso de la aplicación; si el autoacceso local está habilitado, éste podrá crear la sesión técnica del propietario en la siguiente petición.

El reinicio general es un reinicio de **datos**, no de migraciones: no modifica archivos `.env`, claves, logs, dependencias ni el esquema. Esto permite que el borrado y la reconstrucción del propietario sean atómicos.

## Inventario de Ventanilla Finanzas

Se eliminarán todos los registros de:

- `receipt_cancellations`;
- `seq_deposits`;
- `receipts`;
- `payment_transactions`;
- `payment_procedure_items`;
- `payment_procedures`;
- `student_snapshots`;
- `seq_report_exports`.

En `finance_folio_sequences` sólo se eliminarán las claves `procedure`, `receipt_internal` y `receipt_external`, para reiniciar los consecutivos de Ventanilla sin apropiarse de futuras secuencias de otros módulos.

Se conservarán:

- `charge_concepts`;
- `official_fee_schedules`;
- `official_fee_concepts`;
- `charge_concept_official_links`;
- usuarios y accesos.

Ventanilla no almacena actualmente documentos persistentes propios, por lo que este alcance no elimina directorios.

## Inventario de U300

Se eliminarán todos los registros de:

- `u300_technical_sheets`;
- `u300_budget_movements`;
- `u300_budget_lines`;
- `u300_requested_items`;
- `u300_actions`;
- `u300_goals`;
- `u300_projects`;
- `u300_budget_versions`;
- `u300_programs`.

Se eliminarán estas raíces:

- disco `local`: `u300/imports`;
- disco `public`: `u300/technical-sheets/reference-photos`.

Se conservarán las clasificaciones del gasto, Ventanilla, Ingresos Propios, usuarios y accesos.

## Inventario de Ingresos Propios

Se eliminarán todos los registros de:

- `own_revenue_expense_dossier_requirements`;
- `own_revenue_expense_dossier_documents`;
- `own_revenue_expense_dossier_transitions`;
- `own_revenue_fuel_commissions`;
- `own_revenue_fuel_funds`;
- `own_revenue_expense_dossiers`;
- `own_revenue_expense_requirement_rules`;
- `own_revenue_budget_modifications`;
- `own_revenue_modified_budget_lines`;
- `own_revenue_workbook_exports`;
- `own_revenue_initial_budgets`;
- `own_revenue_proposal_travel_participants`;
- `own_revenue_proposal_travel_commissions`;
- `own_revenue_proposal_fuel_needs`;
- `own_revenue_proposal_technical_needs`;
- `own_revenue_proposal_cuts`;
- `own_revenue_planning_corrections`;
- `own_revenue_proposals`;
- `own_revenue_activity_assignments`;
- `own_revenue_activity_rules`;
- `own_revenue_travel_rates`;
- `own_revenue_travel_destinations`;
- `own_revenue_routes`;
- `own_revenue_travel_commissions`;
- `own_revenue_fuel_plans`;
- `own_revenue_technical_sheet_needs`;
- `own_revenue_work_sheet_months`;
- `own_revenue_work_sheet_lines`;
- `own_revenue_abpre_months`;
- `own_revenue_abpre_justifications`;
- `own_revenue_abpre_lines`;
- `own_revenue_import_decisions`;
- `own_revenue_import_origins`;
- `own_revenue_import_issues`;
- `own_revenue_import_rows`;
- `own_revenue_import_files`;
- `own_revenue_import_sessions`;
- `own_revenue_signatories`;
- `own_revenue_activities`;
- `own_revenue_budgets`.

Se eliminarán estas raíces del disco `local`:

- `own-revenue/imports`;
- `own-revenue/exports`;
- `finance/own-revenue`.

Se conservarán `expense_classifications` y `finance/expense-classifications/imports`, porque la clasificación del gasto es un catálogo compartido. También se conservarán Ventanilla, U300, usuarios y accesos.

## Inventario adicional del reinicio general

Además de los tres inventarios anteriores, el alcance `all` vaciará:

- identidad y acceso: `passkeys`, `password_reset_tokens`, `sessions`, `authorized_accesses`, `users`;
- infraestructura con datos efímeros: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`;
- catálogos: `charge_concept_official_links`, `official_fee_concepts`, `official_fee_schedules`, `charge_concepts`, `expense_classifications`;
- todas las filas restantes de `finance_folio_sequences`.

También eliminará `finance/expense-classifications/imports` del disco `local`. `migrations` será la única tabla de la aplicación excluida del vaciado; las tablas internas del motor tampoco forman parte del inventario. Una prueba de cobertura comparará el esquema actual contra este registro y fallará si una tabla nueva no tiene una decisión explícita de reinicio.

## Respuesta y mensajes

Los reinicios parciales volverán a **Datos locales** con un mensaje como: “Ingresos Propios se reinició correctamente: 248 registros eliminados”. Las advertencias de archivos se mostrarán aparte y no ocultarán que la base ya fue limpiada.

Errores de ambiente, autorización y confirmación usarán respuestas HTTP coherentes con el resto de la aplicación. Los detalles internos de excepciones no se mostrarán en pantalla. El comando devolverá código distinto de cero ante un rechazo o fallo y mostrará un resumen por tabla y directorio.

No se guardará una bitácora de estos reinicios en la base porque el alcance general tendría que eliminarla y la función sólo existe localmente. Los mensajes de aplicación podrán registrar inicio, alcance, actor, resultado y advertencias sin incluir datos personales de los registros borrados.

## Pruebas y criterios de aceptación

Las pruebas Pest cubrirán como mínimo:

1. La pantalla y las rutas sólo existen en `local`.
2. Sólo el propietario puede abrir y ejecutar el centro.
3. Una frase incorrecta no altera base ni archivos.
4. Cada reinicio parcial elimina exclusivamente sus tablas y conserva registros testigo de los otros módulos, catálogos compartidos y usuarios.
5. Ventanilla elimina únicamente sus tres claves de folio.
6. U300 elimina sus importaciones y fotos, sin tocar archivos de Ingresos Propios.
7. Ingresos Propios elimina importaciones, exportaciones y expedientes, pero conserva la clasificación del gasto y su archivo fuente.
8. Un fallo durante el borrado de base revierte toda la operación y conserva archivos.
9. Un fallo de archivo posterior al commit produce una advertencia recuperable.
10. El reinicio general deja sólo al usuario propietario y su acceso activo, conserva `migrations` y elimina los catálogos y archivos funcionales.
11. El comando y la interfaz producen el mismo resultado mediante el servicio compartido.
12. La cobertura del inventario detecta tablas de aplicación nuevas que no hayan sido clasificadas.

La verificación final incluirá las pruebas dirigidas, Pint para PHP modificado, comprobación de tipos y compilación del frontend si cambia React.

## Fuera de alcance

- habilitar reinicios en producción o staging;
- respaldar o restaurar datos antes del borrado;
- seleccionar presupuestos, periodos o registros individuales;
- borrar migraciones, `.env`, claves, logs o dependencias;
- incorporar colas, procesos persistentes o paquetes nuevos;
- convertir esta herramienta en administración general de base de datos.
