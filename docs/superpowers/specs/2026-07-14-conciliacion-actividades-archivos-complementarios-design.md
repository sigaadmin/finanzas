# Conciliación de actividades para archivos complementarios

Fecha: 2026-07-14
Estado: aprobado para planificación

## Propósito

Asignar de forma auditable las actividades A01–A04 a las necesidades de Ficha técnica, recorridos de Combustible y comisiones de Viáticos importados. La Hoja de trabajo confirmada será la autoridad para actividad y calendarización; los archivos complementarios conservarán el detalle operativo que explica la planeación.

La conciliación se realizará por grupos reutilizables y permitirá excepciones individuales. Una regla aprobada se reaplicará automáticamente a versiones futuras del mismo formato dentro del mismo presupuesto.

## Alcance

Este incremento incluye:

- reglas de actividad por presupuesto y formato;
- asignaciones auditadas a necesidades, recorridos y comisiones;
- aplicación automática de reglas al confirmar versiones futuras;
- una pantalla de conciliación agrupada;
- detalle de renglones en ventana modal;
- excepciones individuales;
- comparación monetaria informativa contra la Hoja de trabajo;
- consulta de historial y procedencia;
- autorización, control de concurrencia y pruebas automatizadas.

Quedan fuera de este incremento:

- distribuir recortes entre necesidades concretas;
- inventar registros para cubrir diferencias monetarias;
- modificar importes o calendarización de la Hoja de trabajo;
- confirmar el presupuesto inicial;
- exportar archivos XLSX;
- reutilizar reglas automáticamente entre presupuestos o ejercicios distintos.

## Autoridad y criterio de terminación

La Hoja de trabajo confirmada conserva la autoridad presupuestal para actividad, partida y calendarización. Ficha técnica, Combustible y Viáticos conservan sus importes y datos operativos sin ser ajustados automáticamente.

La conciliación de actividades termina cuando todos los registros pertenecientes a las versiones complementarias confirmadas vigentes tienen una actividad asignada. Las diferencias monetarias se muestran y permanecen disponibles para etapas posteriores, pero no bloquean esta conciliación.

## Agrupación

Las claves se normalizarán con una única clase de dominio: eliminación de espacios exteriores, reducción de espacios repetidos, conversión a mayúsculas y comparación sin acentos. La clave técnica no se mostrará en la interfaz.

- Ficha técnica: partida específica y descripción normalizada.
- Combustible: motivo normalizado.
- Viáticos: motivo normalizado.

Una regla pertenece a un solo presupuesto y formato. No se cruzarán reglas entre Combustible y Viáticos aunque compartan el mismo motivo.

## Candidatos y sugerencias

La pantalla calculará las actividades compatibles usando exclusivamente la Hoja de trabajo confirmada vigente:

- Ficha técnica: actividades presentes para la misma partida; el mes con importe distinto de cero se utilizará como evidencia adicional.
- Combustible: actividades presentes en la partida `26101`.
- Viáticos: unión de las actividades presentes en `37501` y, cuando exista transportación aérea, `37101`.

Si existe una sola actividad compatible, se mostrará preseleccionada como sugerencia, pero la primera regla deberá ser aprobada explícitamente. Si existen varias actividades compatibles, la interfaz explicará la ambigüedad y exigirá una selección. El usuario autorizado podrá seleccionar cualquiera de las actividades A01–A04 del presupuesto, porque la especificación general permite corregir la clasificación sugerida.

## Reglas persistentes y revisiones

Se agregará una entidad de regla con los siguientes datos conceptuales:

- presupuesto y formato;
- clave normalizada y datos legibles del grupo;
- actividad seleccionada;
- fotografía del código y nombre de la actividad;
- motivo de asignación y nota;
- usuario creador y fecha;
- vigencia;
- referencia a la regla sustituida, cuando corresponda.

Las reglas serán append-only en su significado. Cambiar la actividad de un grupo desactivará la regla vigente y creará una revisión nueva. Las asignaciones históricas de versiones reemplazadas no se modificarán. La nueva regla se aplicará a los registros de la versión confirmada vigente del grupo y a versiones futuras.

## Asignaciones auditadas

Cada necesidad, recorrido o comisión conservará la actividad vigente directamente en `own_revenue_activity_id` para mantener consultas simples y eficientes. Además, cada aplicación o cambio generará un registro de asignación con:

- presupuesto, archivo importado y entidad afectada;
- regla aplicada, si existe;
- actividad anterior y nueva;
- fotografía del código y nombre de la actividad;
- modo: regla de grupo, regla automática o excepción individual;
- clave del grupo utilizada;
- motivo y nota de justificación;
- usuario y fecha de aplicación.

Las asignaciones serán históricas y no se sobrescribirán. Una excepción individual crea una nueva asignación para el renglón, actualiza su actividad vigente y no modifica la regla del grupo.

## Aplicación a versiones futuras

Durante la confirmación de una versión complementaria, el servidor buscará la regla activa correspondiente a cada registro recién creado. Cuando exista una coincidencia exacta de formato y clave normalizada:

1. asignará la actividad de la regla;
2. registrará una asignación automática vinculada a esa regla;
3. conservará como usuario de aplicación a quien confirma la nueva versión;
4. dejará sin actividad los grupos nuevos que no tengan regla.

Una regla desactivada nunca se aplicará. Si la actividad ya no pertenece al presupuesto o cambió la versión confirmada de la Hoja de trabajo durante la operación, la confirmación se revertirá y solicitará actualizar la revisión.

## Pantalla de conciliación

La pantalla de importaciones mostrará la acción `Conciliar actividades` cuando exista una Hoja de trabajo confirmada y al menos un archivo complementario confirmado.

La conciliación se abrirá mediante navegación interna en la misma pestaña. Contendrá:

- resumen general de registros asignados y pendientes;
- resumen separado para Ficha técnica, Combustible y Viáticos;
- diferencias monetarias contra la Hoja de trabajo con carácter informativo;
- grupos paginados con descripción o motivo, cantidad de registros, importe acumulado, actividad vigente o sugerida y estado;
- selector de actividad A01–A04;
- motivo de asignación y nota;
- acción para guardar la regla y aplicarla al grupo;
- acción `Ver detalle` para abrir los renglones en una ventana modal;
- estado `Actividades conciliadas` cuando no existan registros pendientes.

La interfaz utilizará lenguaje operativo. No mostrará claves normalizadas, nombres de tablas, identificadores, nombres de variables ni códigos internos de incidencias.

## Detalle y excepciones

La ventana modal de un grupo mostrará sus renglones paginados con los campos relevantes del formato, actividad vigente y procedencia legible. Desde el modal se podrá seleccionar un renglón y registrar una actividad distinta como excepción.

Guardar una excepción requerirá actividad, motivo y, cuando el motivo sea `Otro`, una explicación. Al terminar, el modal conservará el contexto del grupo y actualizará sus contadores sin abrir una pestaña nueva.

## Justificaciones

Las asignaciones ofrecerán motivos de negocio, no variables técnicas:

- Coincide con la Hoja de trabajo.
- Clasificación por el motivo o descripción.
- Criterio administrativo.
- Otro.

La nota será opcional para los tres primeros motivos. Para `Otro` será obligatoria. El código interno del motivo se almacenará para consistencia, pero la interfaz y el historial mostrarán siempre la etiqueta legible.

## Comparaciones monetarias

Las comparaciones usarán centavos enteros y no modificarán datos:

- Ficha técnica: importes por actividad, partida y mes frente a la Hoja de trabajo.
- Combustible: importe total por actividad frente a `26101`; el mes operativo del recorrido se conserva y la aplicación presupuestal se compara contra abril.
- Viáticos: alimentación y hospedaje frente a `37501`, y transportación aérea frente a `37101`, conservando una sola actividad por comisión.

Se mostrarán importe detallado, importe de Hoja de trabajo y diferencia. Una diferencia no genera asignaciones, distribuciones ni registros sintéticos.

## Operaciones transaccionales y concurrencia

Crear o revisar una regla ejecutará una transacción que:

1. bloqueará el presupuesto y las versiones confirmadas involucradas;
2. comprobará que la Hoja de trabajo confirmada continúa vigente;
3. comprobará que el archivo complementario confirmado sigue siendo el vigente;
4. comprobará que la actividad pertenece al presupuesto;
5. desactivará la regla anterior cuando exista;
6. creará la nueva revisión;
7. aplicará la actividad a todos los registros vigentes del grupo;
8. registrará una asignación por cada registro modificado;
9. revertirá toda la operación ante cualquier fallo.

Las solicitudes incluirán los identificadores de las versiones confirmadas observadas al cargar la pantalla. Si alguna versión cambió, se rechazará la operación completa con el mensaje `Los archivos confirmados cambiaron; actualiza la página antes de continuar.`

Las excepciones usarán el mismo control de versiones y bloqueo del registro individual.

## Permisos

- Owner, Admin y FinanceManager podrán crear reglas, revisar reglas y registrar excepciones.
- FinanceAssistant y FinanceAuditor podrán consultar grupos, asignaciones, diferencias e historial, sin modificar datos.
- Los demás usuarios no podrán acceder a la conciliación.

La autorización se validará en rutas, solicitudes HTTP y acciones de dominio. Los selectores y botones se ocultarán en modo de consulta, pero el servidor seguirá siendo la autoridad.

## Estructura de aplicación

La implementación seguirá los patrones existentes:

- una acción de dominio para guardar y aplicar reglas;
- una acción de dominio para excepciones individuales;
- un servicio de lectura para construir grupos, candidatos, resúmenes y diferencias;
- un servicio único para normalizar claves;
- controladores Inertia delgados;
- Form Requests para validación;
- relaciones Eloquent tipadas y consultas con carga anticipada;
- rutas bajo `finance.own-revenue.*` y funciones Wayfinder.

No se agregarán dependencias externas.

## Estados vacíos y errores

- Sin Hoja de trabajo confirmada: se explicará que debe confirmarse antes de conciliar.
- Sin archivos complementarios confirmados: se explicará que no hay detalle disponible.
- Grupo sin candidatos: se permitirá seleccionar manualmente A01–A04 y se mostrará que la Hoja de trabajo no ofrece una coincidencia directa.
- Grupo con varios candidatos: se mostrarán las actividades compatibles y se requerirá una selección.
- Versiones cambiadas: no habrá cambios parciales y se solicitará actualizar la página.
- Regla futura sin actividad válida: la confirmación de la nueva versión se revertirá para no crear asignaciones inconsistentes.

## Pruebas

Las pruebas cubrirán:

- normalización y separación de claves por formato;
- agrupación de los tres tipos de registro;
- candidatos únicos, múltiples y ausentes;
- creación y revisión append-only de reglas;
- aplicación transaccional al grupo actual;
- excepciones que no cambian la regla;
- aplicación automática durante confirmaciones futuras;
- preservación de asignaciones históricas de archivos reemplazados;
- rechazo de actividades, presupuestos, entidades o versiones ajenas;
- protección frente a cambios concurrentes;
- permisos de administración y consulta;
- cálculo exacto de diferencias monetarias;
- criterio de terminación basado en registros con actividad;
- props Inertia, navegación interna, modal, paginación y lenguaje no técnico;
- validación con datos representativos de los cinco archivos reales.

## Criterios de aceptación

El incremento estará completo cuando:

1. sea posible asignar una actividad a un grupo y conservar una regla auditable;
2. sea posible corregir un renglón mediante una excepción sin alterar la regla;
3. una versión futura reutilice automáticamente las reglas activas;
4. todas las decisiones conserven usuario, fecha, justificación y procedencia;
5. la pantalla distinga asignados, pendientes y diferencias informativas;
6. la conciliación se marque terminada sólo cuando todos los registros vigentes tengan actividad;
7. los roles de consulta no puedan modificar información;
8. cambios concurrentes no produzcan resultados parciales;
9. las pruebas automatizadas y la revisión con los archivos reales sean satisfactorias.
