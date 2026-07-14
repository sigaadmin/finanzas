# Diseño de importación XLSX para Presupuesto de Ingresos Propios

Fecha: 2026-07-13
Estado: aprobado para revisión documental

## 1. Propósito

Permitir la creación y alimentación progresiva de un ejercicio de Presupuesto de Ingresos Propios mediante los cinco formatos oficiales: combustible, viáticos, ficha técnica, hoja de trabajo y ABPRE.

La importación será una fuente auditable de planeación, no una sobrescritura directa de la base de datos. Los archivos se analizarán, validarán y conciliarán antes de incorporar cualquier información al ejercicio.

## 2. Modalidades de creación

La pantalla de nuevo presupuesto ofrecerá tres modalidades:

1. Crear un ejercicio en blanco.
2. Copiar la configuración de otro ejercicio.
3. Crear desde archivos XLSX.

La tercera modalidad utilizará un asistente en dos etapas. Primero creará un ejercicio con año fiscal y datos institucionales mínimos en el estado ordinario `Borrador`. La sesión asociada indicará `Importación en proceso` sin agregar un estado nuevo al ciclo presupuestario. Después abrirá el asistente de carga, análisis y conciliación.

El año elegido al crear el ejercicio será la autoridad. Un año diferente incrustado en los archivos generará una advertencia, pero no cambiará el ejercicio ni bloqueará por sí solo la importación.

## 3. Importaciones parciales

No será obligatorio cargar los cinco formatos simultáneamente. El usuario podrá importar uno o varios, guardar el avance y continuar posteriormente.

El asistente mostrará cinco espacios identificados:

- Combustible.
- Viáticos.
- Ficha técnica.
- Hoja de trabajo.
- ABPRE.

Se permitirá arrastrar varios archivos. El sistema intentará identificar el tipo de cada uno y solicitará una clasificación manual cuando el resultado sea ambiguo.

Cada archivo tendrá uno de estos estados:

1. No cargado.
2. Analizando.
3. Requiere correcciones.
4. Listo para confirmar.
5. Importado.
6. Reemplazado por una versión posterior.
7. Descartado.

La ausencia de formatos secundarios no impedirá continuar capturando información, pero el ejercicio indicará que su detalle está incompleto. La confirmación futura del presupuesto inicial conservará los requisitos generales definidos en el diseño principal, incluida la existencia de ABPRE y la conciliación de totales.

## 4. Autoridad de la información

Cada formato tendrá una responsabilidad delimitada:

- Combustible aporta recorridos planeados, distancias, litros, precio de referencia, mes operativo y actividad.
- Viáticos aporta comisiones, destinos, días, alimentación, hospedaje, zona tarifaria, UMA y actividad.
- Ficha técnica aporta necesidades distintas de combustible, alimentación y hospedaje.
- Hoja de trabajo aporta la relación consolidada entre actividad, partida, región y mes de aplicación.
- ABPRE aporta el importe presupuestario final después de los recortes y la justificación definitiva por partida.

Cuando existan discrepancias:

- ABPRE será la autoridad para el total final por partida.
- La hoja de trabajo será la autoridad para actividad y calendarización, salvo resolución manual.
- Las fichas conservarán el detalle que explica cómo se calculó o planeó el importe.
- La región se normalizará a `02-001 — Felipe Carrillo Puerto` y se informarán las correcciones realizadas.
- Ninguna diferencia se distribuirá automáticamente entre necesidades concretas sin confirmación del usuario.

## 5. Análisis sin efectos

La carga de un archivo no modificará el presupuesto. Primero se creará una versión de importación y se analizará en una zona temporal.

El análisis deberá:

1. Identificar el tipo de formato y las hojas relevantes.
2. Reconocer encabezados normalizados sin depender exclusivamente de posiciones fijas.
3. Admitir formatos oficiales limpios y archivos internos con hojas auxiliares.
4. Detectar el año declarado o incrustado.
5. Normalizar meses, partidas, cantidades, unidades, importes y región.
6. Relacionar las partidas con el COG del ejercicio.
7. Conservar hoja, fila y valores originales.
8. Calcular una vista previa sin escribir entidades presupuestarias definitivas.

Los importes monetarios se procesarán como enteros en centavos y los parámetros decimales como cadenas exactas. No se utilizarán conversiones binarias de punto flotante.

## 6. Validaciones e incidencias

La vista previa clasificará los resultados en:

- Errores bloqueantes: archivo ilegible, plantilla desconocida, partida inexistente, importe inválido o información mínima ausente.
- Advertencias: año distinto, región corregida, total diferente al ABPRE, UMA o combustible pendiente de actualización, filas duplicadas o información incompleta.
- Ajustes informativos: normalización de nombres, códigos, meses y espacios vacíos.

La vista previa se abrirá mediante navegación interna en la misma pestaña. Todas las vistas conservarán una acción visible para volver a la pantalla de importaciones y al archivo seleccionado.

Una versión con errores bloqueantes no podrá confirmarse. Las advertencias podrán aceptarse mediante una decisión explícita cuando no violen una regla presupuestaria.

## 7. Versionado y protección de ediciones manuales

Volver a cargar un formato no sobrescribirá la versión anterior. Se creará una versión nueva y se mostrarán sus diferencias antes de reemplazarla.

Una importación confirmada será inmutable. Las versiones posteriores conservarán el archivo, análisis, incidencias y decisiones de la versión sustituida.

Si un registro fue modificado manualmente después de una importación, una nueva versión lo marcará como conflicto. Para cada conflicto se podrá:

1. Conservar el valor manual.
2. Aceptar el valor del nuevo XLSX.
3. Capturar un valor distinto.

Las decisiones que afecten importes, partidas, actividad o mes registrarán usuario, fecha y justificación.

## 8. Conciliación y procedencia

La conciliación mostrará:

- Valor vigente.
- Valor propuesto por el nuevo archivo.
- Diferencia monetaria o textual.
- Origen de cada valor.
- Cambios manuales posteriores a la versión anterior.
- Decisión requerida o aplicada.

Cada recorrido, comisión, necesidad o asignación incorporada conservará un vínculo con el archivo, hoja, fila y versión que la originó. La interfaz distinguirá información importada, copiada y capturada manualmente.

## 9. Estructura conceptual

La importación se modelará separadamente del presupuesto confirmado:

- Sesión de importación: agrupa una operación de carga y su progreso.
- Archivo importado: conserva tipo, nombre, tamaño, hash, año detectado, versión, usuario y estado.
- Fila analizada: conserva ubicación y valores originales normalizados.
- Incidencia: representa errores, advertencias y ajustes informativos.
- Decisión de conciliación: conserva la selección, justificación, usuario y fecha.
- Vínculo de procedencia: conecta las entidades presupuestarias con su fila de origen.

Los archivos originales se almacenarán de forma privada. Una huella digital impedirá importaciones accidentales del mismo archivo; cualquiera de los roles autorizados para administrar importaciones podrá solicitar explícitamente un nuevo análisis sin borrar el anterior.

Eliminar una carga no eliminará información histórica confirmada. La versión quedará marcada como descartada o reemplazada.

## 10. Confirmación transaccional

Confirmar una versión ejecutará una operación atómica:

1. Verificar que la versión analizada sigue siendo la vigente.
2. Comprobar que no aparecieron nuevas ediciones manuales.
3. Validar nuevamente COG, ejercicio, región e importes.
4. Incorporar solamente las filas aceptadas.
5. Registrar decisiones, procedencia, usuario y fecha.
6. Recalcular resúmenes y discrepancias.
7. Revertir toda la operación si falla cualquier paso.

Después de confirmar, el ejercicio mostrará los formatos importados, faltantes o reemplazados y el estado de conciliación con ABPRE.

## 11. Permisos

- `Owner`, `Admin` y `FinanceManager` podrán crear sesiones, cargar archivos, corregir su tipo, resolver conflictos, descartar versiones y confirmar importaciones.
- `FinanceAssistant` y `FinanceAuditor` podrán consultar archivos, incidencias, conciliaciones y procedencia, sin confirmar ni reemplazar información.
- Usuarios externos al área financiera no podrán acceder a archivos ni sesiones.

La autorización se validará en la interfaz, solicitudes HTTP y acciones de dominio. Las operaciones críticas requerirán confirmación explícita.

## 12. Compatibilidad de archivos

Los importadores deberán probarse con:

- Formatos 2026 proporcionados por el CREN.
- Plantillas 2027 que todavía contienen referencias a 2026.
- Formatos oficiales sin hojas auxiliares.
- Versiones internas de combustible y viáticos con tablas y hojas auxiliares.
- Filas de ejemplo, fórmulas, celdas combinadas y encabezados repetidos presentes en los archivos reales.

La detección se basará en contenido y encabezados reconocibles, no solamente en el nombre del archivo.

## 13. Estrategia de pruebas

Las pruebas automatizadas y con archivos representativos cubrirán:

- Identificación automática y corrección manual del tipo.
- Importaciones parciales, repetidas y reanudables.
- Advertencias de año sin reasignación automática del ejercicio.
- Normalización forzada de la región `02-001`.
- Partidas ausentes o inválidas en el COG.
- Discrepancias entre fichas, hoja de trabajo y ABPRE.
- Protección de ediciones manuales y resolución de conflictos.
- Precisión monetaria y decimal.
- Permisos para todos los roles financieros.
- Confirmación idempotente y reversión completa ante errores.
- Conservación de archivos, versiones, decisiones y procedencia.

## 14. Orden de implementación

La implementación se dividirá en incrementos verificables:

1. Infraestructura común de sesiones, archivos privados, versiones, filas, incidencias y procedencia.
2. Importador ABPRE y conciliación de totales finales.
3. Importador de hoja de trabajo y calendarización.
4. Importador de ficha técnica y necesidades generales.
5. Importador de combustible, recorridos y reglas de redondeo.
6. Importador de viáticos, zonas, UMA y hospedaje.
7. Conciliación transversal de los cinco formatos y preparación para confirmar el presupuesto inicial.

Cada incremento incluirá pruebas de dominio, autorización, integración HTTP y archivos reales representativos antes de iniciar el siguiente.
