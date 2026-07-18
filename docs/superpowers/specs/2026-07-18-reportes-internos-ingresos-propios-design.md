# Reportes internos de Ingresos Propios — Diseño

## Objetivo

Incorporar una vista de consulta que permita entender el estado presupuestal y operativo de un ejercicio de Ingresos Propios sin modificar sus datos. Este incremento cubre la consulta dentro del sistema; la exportación se realizará en un incremento posterior.

## Alcance

La pantalla reunirá en un solo tablero:

- presupuesto inicial, modificado, reservado, comprometido, pagado y disponible;
- transferencias y recalendarizaciones;
- expedientes agrupados por etapa y requisitos pendientes;
- comparación entre lo planeado y lo ejercido;
- fondo operativo de combustible, consumo confirmado, necesidades pendientes y saldo disponible.

La información podrá filtrarse por actividad, capítulo, partida y mes. Los filtros afectarán únicamente las secciones presupuestales compatibles; las secciones operativas conservarán el contexto del mismo ejercicio y explicarán cuando un filtro no les corresponda.

Quedan fuera de este incremento:

- exportaciones XLSX, PDF o CSV;
- cierre anual;
- nuevos movimientos o cambios de estado desde la pantalla de reportes;
- fotografías o tablas materializadas de reportes;
- comparaciones entre ejercicios distintos.

## Navegación y acceso

Cada ejercicio mostrará una acción **Reportes** junto a Planeación, Ejecución y Combustible. La ruta pertenecerá al espacio `finance.own-revenue.budgets.reports.show` y conservará el ejercicio seleccionado.

Los roles administrativos, operativos y de auditoría con acceso a Ingresos Propios podrán consultar el tablero. La pantalla será estrictamente de sólo lectura y no mostrará acciones de captura, autorización o modificación.

## Arquitectura

Un servicio de lectura especializado construirá un contrato estable para Inertia. El controlador se limitará a autorizar el acceso, validar filtros y renderizar la página.

El servicio consultará las fuentes de verdad existentes:

- presupuesto inicial autorizado y líneas del presupuesto modificado;
- modificaciones presupuestales;
- expedientes y requisitos;
- planeación autorizada;
- fondo y comisiones de combustible.

No se crearán tablas ni se duplicarán saldos. Los importes se calcularán en centavos en el servidor y se entregarán como cadenas decimales para evitar pérdida de precisión en JavaScript.

## Contrato del tablero

La respuesta contendrá:

- contexto del ejercicio y filtros disponibles;
- filtros aplicados;
- seis totales presupuestales;
- desglose presupuestal agrupado por actividad, capítulo, partida y mes;
- resumen y listado de modificaciones;
- conteo de expedientes por etapa y total de requisitos pendientes;
- comparación de importe planeado, pagado y diferencia;
- resumen del fondo de combustible y sus comisiones.

Los renglones de presupuesto conservarán las claves y nombres históricos guardados en el presupuesto autorizado. Ningún reporte reinterpretará ejercicios anteriores con catálogos actuales.

## Interfaz

La pantalla utilizará una sola página con:

1. encabezado del ejercicio y acceso para volver al tablero anual;
2. filtros compactos por actividad, capítulo, partida y mes;
3. tarjetas de totales presupuestales;
4. tabla de desglose presupuestal;
5. secciones resumidas de modificaciones, expedientes y combustible;
6. comparación de planeado contra ejercido.

Los filtros viajarán en la URL y se aplicarán mediante navegación Inertia en la misma ventana. Cada sección tendrá un estado vacío con lenguaje operativo. La página reutilizará componentes visuales existentes y mantendrá compatibilidad con modo oscuro y pantallas pequeñas.

## Reglas de cálculo

- **Inicial:** importe del presupuesto inicial autorizado dentro del filtro.
- **Modificado:** importe vigente después de transferencias y recalendarizaciones.
- **Reservado:** suficiencias confirmadas que todavía no se han convertido en compromiso.
- **Comprometido:** expedientes con compra iniciada o autorización de pago que todavía no estén pagados.
- **Pagado:** expedientes concluidos como pagados.
- **Disponible:** modificado menos reservado, comprometido y pagado.
- **Planeado contra ejercido:** presupuesto inicial autorizado contra importe pagado, mostrando diferencia y porcentaje de ejercicio cuando el inicial sea mayor que cero.
- **Fondo de combustible:** valor adquirido, consumo confirmado, necesidades pendientes y saldo disponible, sin mezclarlo con el ejercicio presupuestal de abril.

Los expedientes cancelados o rechazados que hayan liberado saldos no contribuirán a reservado, comprometido ni pagado. Las modificaciones presupuestales son movimientos confirmados e inmutables; no se inventará un estado de cancelación que el flujo actual no admite.

## Errores y estados incompletos

Si el ejercicio aún no tiene presupuesto inicial autorizado, la pantalla explicará que los reportes presupuestales estarán disponibles después de la autorización y mostrará únicamente la información operativa que exista.

Si todavía no se inicializó el presupuesto modificado o el fondo de combustible, se mostrarán estados vacíos y no valores inventados. Los filtros inválidos se ignorarán de forma segura o se normalizarán a valores permitidos; nunca producirán consultas fuera del ejercicio actual.

## Pruebas y aceptación

La implementación se considerará aceptada cuando las pruebas demuestren que:

- los seis saldos se calculan correctamente a partir de datos existentes;
- los filtros por actividad, capítulo, partida y mes preservan totales coherentes;
- cada presupuesto está aislado de otros ejercicios;
- cancelaciones y rechazos liberados no consumen saldo;
- expedientes y requisitos se agrupan por estado;
- el fondo de combustible permanece separado del presupuesto;
- los tres roles autorizados pueden consultar sin recibir permisos de modificación;
- importes superiores al entero seguro de JavaScript conservan precisión;
- la navegación permanece en la misma ventana y conserva los filtros en la URL;
- los estados sin información usan lenguaje operativo.
