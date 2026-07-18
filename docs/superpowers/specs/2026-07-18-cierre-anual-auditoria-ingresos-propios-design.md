# Cierre anual y auditoría consolidada de Ingresos Propios — Diseño

## Objetivo

Completar la Fase 5 de Ingresos Propios con un cierre anual explícito, definitivo y auditable, además de una consulta cronológica que reconstruya las decisiones relevantes del ejercicio sin duplicar los registros de cada flujo.

## Alcance

Este incremento incorpora:

- revisión previa del cierre anual;
- impedimentos operativos calculados con información vigente;
- confirmación explícita mediante frase y nota;
- acta de cierre inmutable con resumen, responsable, fecha y huella digital;
- cambio definitivo del presupuesto al estado `closed`;
- historial consolidado de importaciones, planeación, autorización, exportaciones, ejecución, combustible y cierre;
- acceso de sólo lectura al acta y al historial después del cierre.

Quedan fuera:

- reapertura del ejercicio, incluso para el propietario;
- exportación del acta o del historial;
- comparación entre ejercicios;
- creación de una bitácora genérica que duplique eventos existentes;
- incorporación de actividad al modelo de ejecución.

## Decisión arquitectónica

El cierre se representará mediante un registro especializado y único por presupuesto. No se añadirán únicamente campos sueltos a `own_revenue_budgets` ni se introducirá un sistema general de eventos.

La tabla `own_revenue_budget_closures` almacenará:

- presupuesto anual;
- nota de cierre;
- resumen inmutable serializado;
- huella SHA-256 del resumen canónico;
- usuario que cerró;
- fecha y hora del cierre;
- marcas de tiempo técnicas.

La relación con el presupuesto será uno a uno y tendrá una restricción única. El registro no admitirá actualizaciones ni eliminación mediante la aplicación.

El historial consolidado será una proyección de lectura. Consultará los registros auditables ya existentes y los transformará a un contrato común; no copiará eventos a otra tabla.

## Elegibilidad y permisos

Sólo el propietario, un administrador o el responsable de Finanzas podrá ejecutar el cierre. Asistentes y auditores podrán consultar la revisión, el historial y el acta, pero no cerrarán el ejercicio.

El cierre sólo podrá iniciar cuando el presupuesto esté en `initial_authorized` o `in_execution`. Un presupuesto en borrador, con propuesta pendiente o ya cerrado no será elegible.

La política incorporará una capacidad específica `closeAnnualBudget`. Las mutaciones existentes continuarán basándose en los estados ejecutables y, por tanto, rechazarán automáticamente un presupuesto cerrado.

## Impedimentos de cierre

La revisión calculará los impedimentos cada vez que se consulte y los volverá a comprobar dentro de la transacción de cierre.

Bloquean el cierre:

1. cualquier expediente cuyo estado no sea `paid`, `rejected` o `cancelled`;
2. cualquier requisito con estado `pending` perteneciente al presupuesto;
3. cualquier comisión de combustible con estado `pending`.

Los impedimentos se devolverán como elementos operativos con tipo, cantidad y mensaje comprensible. No expondrán nombres de tablas, enums o variables.

No bloquean el cierre:

- saldo presupuestal disponible;
- saldo remanente del fondo de combustible;
- inexistencia de fondo de combustible cuando el ejercicio no lo haya requerido;
- expedientes pagados, rechazados o cancelados;
- requisitos completados o exceptuados.

Los remanentes se registrarán en el acta para que el cierre no oculte recursos sin ejercer.

## Revisión previa

La página de cierre mostrará:

- ejercicio, región y estado actual;
- los seis saldos presupuestales: inicial, modificado, reservado, comprometido, pagado y disponible;
- conteo de expedientes por estado y requisitos pendientes;
- fondo adquirido, consumo confirmado, necesidades pendientes y saldo operativo;
- lista de impedimentos;
- estado de elegibilidad;
- acta existente cuando el ejercicio ya esté cerrado.

La revisión será de sólo lectura. Si existen impedimentos, explicará la acción necesaria y no mostrará el formulario de confirmación como disponible.

## Confirmación y transacción

La confirmación requerirá:

- frase exacta `CERRAR {AÑO}`, respetando el año del presupuesto;
- nota obligatoria de 10 a 1000 caracteres.

El servidor validará ambos campos; la interfaz sólo anticipará esas reglas.

La acción de cierre ejecutará una transacción y bloqueará el renglón del presupuesto con `lockForUpdate`. Dentro de la transacción:

1. volverá a comprobar estado, permisos e inexistencia de acta;
2. recalculará impedimentos y resumen a partir de las fuentes vigentes;
3. rechazará la operación si apareció un impedimento concurrente;
4. construirá una representación canónica estable del resumen;
5. calculará su huella SHA-256;
6. creará el acta inmutable;
7. cambiará el presupuesto a `closed`.

La creación del acta y el cambio de estado serán atómicos. Cualquier error revertirá ambos. Repetir la solicitud no creará otra acta ni modificará la original.

## Contenido del acta

El resumen persistido incluirá únicamente datos necesarios para reconstruir el momento del cierre:

- identidad del ejercicio y región `02-001`;
- seis saldos presupuestales como cadenas de centavos;
- conteos de expedientes por estado;
- total de requisitos pendientes, que debe ser cero;
- resumen del fondo de combustible;
- número y totales de modificaciones presupuestales;
- número de exportaciones oficiales generadas;
- identificador y fecha de autorización del presupuesto inicial;
- versión del contrato del acta.

El resumen se almacenará como JSON y se serializará con claves y orden deterministas antes de calcular la huella. Los importes permanecerán como cadenas para preservar precisión.

## Historial consolidado

Un servicio de lectura proyectará eventos con el contrato:

- `id`: clave estable compuesta por origen e identificador;
- `type`: categoría funcional;
- `occurred_at`: fecha y hora;
- `title`: acción en lenguaje operativo;
- `description`: contexto breve;
- `actor_name`: responsable cuando exista;
- `reference`: folio, formato o referencia visible cuando corresponda.

Las categorías serán:

- configuración;
- importación;
- planeación y autorización;
- exportación;
- modificación presupuestal;
- expediente;
- combustible;
- cierre.

La primera versión incluirá los eventos que ya tienen responsable y fecha confiables:

- creación del presupuesto y confirmación del COG;
- archivos importados confirmados;
- autorización del presupuesto inicial;
- exportaciones oficiales generadas;
- modificaciones presupuestales registradas;
- transiciones de expedientes;
- apertura del fondo, registro de comisiones y confirmación de consumos;
- cierre anual.

Los eventos se ordenarán por fecha descendente y luego por clave estable. La página permitirá filtrar por categoría. Los filtros inválidos se ignorarán de forma segura. La consulta siempre quedará limitada al presupuesto de la ruta.

## Navegación e interfaz

El tablero anual incorporará dos accesos visibles para los roles con consulta financiera:

- **Revisar cierre anual**;
- **Historial del ejercicio**.

Ambas navegaciones usarán Inertia en la misma ventana.

La revisión de cierre empleará tarjetas y listas compactas consistentes con Reportes internos. La acción irreversible se presentará en un diálogo modal. El botón tendrá lenguaje directo y no se habilitará hasta que la frase coincida, la nota sea válida y no existan impedimentos.

Después del cierre, la misma página mostrará el acta, su responsable, fecha, nota, huella y resumen. No ofrecerá reapertura ni controles de edición.

El historial será una pantalla de sólo lectura con filtros compactos y una línea cronológica legible. Los estados vacíos usarán lenguaje operativo y no anotaciones técnicas.

## Errores y concurrencia

- Un cambio concurrente entre revisión y confirmación producirá un mensaje que solicite actualizar la revisión.
- Una frase incorrecta o nota inválida conservará la página y mostrará el error junto al campo correspondiente.
- Un usuario sin permiso recibirá `403` y no se modificará ningún dato.
- Un presupuesto ya cerrado mostrará su acta; intentar cerrarlo de nuevo será rechazado sin alterar la huella ni la fecha originales.
- Si el acta no pudiera persistirse, el presupuesto conservará su estado anterior.

## Pruebas y aceptación

La implementación se considerará aceptada cuando las pruebas demuestren que:

- cada impedimento se detecta y se expresa en lenguaje operativo;
- los estados terminales de expedientes no bloquean;
- los remanentes presupuestales o de combustible no bloquean y aparecen en el acta;
- sólo los roles administrativos autorizados pueden cerrar;
- frase, nota y estado se validan en el servidor;
- la transacción vuelve a comprobar condiciones bajo bloqueo;
- acta y estado se crean atómicamente;
- el acta tiene una sola fila por presupuesto y una huella determinista;
- no existe reapertura ni cierre repetido;
- las mutaciones de ejecución y combustible quedan rechazadas después del cierre;
- el historial sólo contiene eventos del presupuesto solicitado, se ordena correctamente y filtra por categoría;
- responsables, asistentes y auditores consultan historial y acta sin controles de modificación;
- la navegación permanece en la misma ventana y el texto no expone identificadores técnicos.

## Criterio de terminación

El incremento estará terminado cuando un ejercicio elegible pueda revisarse, cerrarse de forma definitiva y consultarse posteriormente mediante su acta e historial consolidado, manteniendo bloqueadas todas las mutaciones y preservando evidencia suficiente para reconstruir el cierre.
