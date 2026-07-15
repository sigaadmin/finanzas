# Planeación editable y cierre de la Fase 3

Fecha: 2026-07-14  
Estado: aprobado

## Objetivo

Convertir las importaciones confirmadas del Presupuesto de Ingresos Propios en una propuesta editable, versionada y auditable dentro del sistema; distribuir los recortes contra el ABPRE final; autorizar un presupuesto inicial inmutable y regenerar los cinco formatos oficiales XLSX desde la base de datos.

El trabajo se realizará en dos incrementos consecutivos:

1. Planeación editable y versiones de propuesta.
2. Cierre de Fase 3, autorización inicial y exportación XLSX.

La integración del primer incremento no implica que el objetivo completo esté terminado. El cierre funcional requiere completar ambos.

## Decisiones aprobadas

- Las importaciones confirmadas permanecen inmutables como evidencia documental.
- La planeación editable vive en entidades canónicas y versiones propias.
- La primera propuesta se crea mediante la acción explícita `Crear propuesta desde importaciones`.
- Un borrador se edita en el lugar; `Calcular propuesta` produce un snapshot inmutable.
- Un ajuste posterior crea un nuevo borrador basado en la versión anterior.
- Los recortes se calculan contra el ABPRE confirmado y se distribuyen entre necesidades concretas.
- La distribución proporcional es sólo una sugerencia y nunca se aplica sin confirmación.
- Se editan las necesidades de Ficha técnica, Combustible y Viáticos; la Hoja de trabajo y el ABPRE son proyecciones derivadas.
- Las correcciones manuales a cálculos requieren justificación y auditoría.
- El flujo de estados es `Borrador → Propuesta calculada → Propuesta ajustada → Inicial autorizado`.
- No se exige separación obligatoria entre quien prepara y quien autoriza; ambas acciones quedan auditadas.
- El presupuesto inicial autorizado es inmutable.
- Se autoriza agregar `phpoffice/phpspreadsheet` en el incremento de exportación.
- La implementación se realizará sin subagentes, con pruebas dirigidas y una sola verificación integral al final.

## Estado de partida

Los cinco formatos ya cuentan con carga, análisis, incidencias, vista previa y confirmación. Ficha técnica, Combustible y Viáticos conservan sus renglones importados y pueden conciliarse con actividades de la Hoja de trabajo mediante reglas grupales o excepciones individuales.

Las tablas actuales ligadas a archivos de importación constituyen evidencia de origen. No se editarán para representar la planeación operativa.

## 1. Arquitectura y límites

### 1.1 Capas separadas

El presupuesto mantendrá cuatro capas independientes:

1. Archivos y registros importados confirmados.
2. Propuestas editables y sus versiones inmutables.
3. Presupuesto inicial autorizado e inmutable.
4. Presupuesto modificado y ejercicio, fuera del alcance de estos incrementos.

La base de datos será la fuente oficial. Los XLSX serán adaptadores de entrada y salida.

### 1.2 Materialización explícita

La acción `Crear propuesta desde importaciones` estará disponible únicamente cuando:

- exista una versión confirmada vigente de los cinco formatos;
- todos los registros vigentes de Ficha técnica, Combustible y Viáticos tengan actividad;
- no existan incidencias bloqueantes pendientes;
- el COG anual esté vigente;
- UMA, combustible y demás parámetros requeridos estén completos;
- datos institucionales y firmantes estén completos.

Cuando falte una condición, la pantalla mostrará una lista operativa de pendientes. No se crearán propuestas parciales desde importaciones.

La materialización se ejecutará en una transacción, bloqueará el presupuesto y las versiones fuente, y conservará referencias a cada archivo y renglón importado utilizado.

### 1.3 Agregado de planeación

La propuesta tendrá una cabecera versionada y colecciones especializadas para:

- conceptos de Ficha técnica;
- recorridos y necesidades de Combustible;
- comisiones y participantes de Viáticos;
- distribuciones mensuales derivadas;
- recortes por necesidad;
- correcciones justificadas;
- referencias y huellas de las fuentes importadas.

Cada unidad tendrá identificadores estables entre versiones para facilitar comparaciones. Las eliminaciones de un borrador serán lógicas o quedarán representadas en el snapshot siguiente; nunca borrarán evidencia importada ni versiones congeladas.

## 2. Edición y cálculos

### 2.1 Pantalla de planeación

La planeación tendrá tres áreas navegables en la misma ventana:

- Ficha técnica.
- Combustible.
- Viáticos.

Mientras la propuesta esté en borrador se podrán crear, editar, retirar lógicamente y reordenar necesidades. La vista incluirá totales, estado de validación, diferencias y origen de cada registro sin mostrar nombres técnicos o claves internas.

### 2.2 Ficha técnica

Cada concepto almacenará:

- actividad A01–A04;
- partida COG;
- descripción;
- cantidad, unidad y precio unitario opcionales;
- importe de referencia calculado;
- importe total presupuestario editable;
- mes presupuestado;
- impacto en metas;
- justificación;
- origen importado opcional.

Cantidad por precio unitario genera una referencia. El importe total editable es la cifra definitiva y puede diferir, conservando la diferencia y la justificación para auditoría.

### 2.3 Combustible

Cada necesidad almacenará:

- fecha o mes operativo;
- motivo;
- origen, destino, kilómetros y kilómetros adicionales;
- vehículo y rendimiento;
- litros estimados;
- precio por litro;
- importe matemático;
- importe al peso superior;
- importe presupuestado al siguiente múltiplo de $50;
- diferencia de redondeo;
- actividad y origen importado opcional.

Un importe que ya sea múltiplo exacto de $50 no se incrementará. El mes operativo describe cuándo ocurre el recorrido, pero la aplicación presupuestal será abril. Se podrán reutilizar recorridos catalogados y corregir sus valores en una necesidad concreta con justificación.

### 2.4 Viáticos

La comisión almacenará los datos comunes del viaje y uno o varios participantes:

- motivo, fechas, mes y destino;
- participante o perfil, cargo y días;
- zona de alimentación y hospedaje;
- UMA y tarifas aplicadas;
- alimentación, hospedaje, transporte aéreo y total;
- actividad y origen importado opcional.

Destino y cargo determinarán las zonas y tarifas predeterminadas. Se utilizará la fila `Puestos no considerados en los anteriores` cuando corresponda. Una corrección manual de zona o tarifa conservará valor original, nuevo valor, motivo, usuario y fecha.

### 2.5 Precisión y auditoría

Los importes se almacenarán en centavos. UMA, litros, kilómetros, rendimientos y precios unitarios usarán decimales con precisión explícita. No se utilizarán números flotantes para decisiones monetarias.

Toda excepción a un valor calculado registrará:

- campo corregido;
- valor calculado u original;
- valor confirmado;
- justificación;
- usuario;
- fecha.

## 3. Versiones y recortes

### 3.1 Borrador y snapshot calculado

El borrador activo se edita sin crear una versión por cada pulsación. `Calcular propuesta` validará actividades, partidas, meses, región y parámetros; generará totales por actividad, partida y mes; y producirá un snapshot inmutable.

Si se requieren cambios después de calcular, el sistema creará un nuevo borrador basado en el snapshot seleccionado. Las versiones anteriores continuarán disponibles para consulta y comparación.

### 3.2 Comparación con ABPRE

El snapshot calculado se comparará con la versión ABPRE confirmada observada al calcular. La pantalla mostrará:

`Propuesta calculada | Reducción requerida | Reducción distribuida | Saldo por distribuir | Propuesta ajustada | ABPRE final`

Si cambia cualquier archivo confirmado, parámetro anual o COG que forme parte de la huella de cálculo, la propuesta quedará marcada como desactualizada. No se actualizará silenciosamente.

### 3.3 Distribución de recortes

Cuando la propuesta calculada supere el ABPRE, la diferencia constituirá un recorte pendiente. El usuario podrá:

- reducir parcialmente una necesidad;
- eliminar su importe presupuestario de la versión ajustada;
- solicitar una sugerencia proporcional;
- confirmar o modificar manualmente la sugerencia.

La sugerencia no persistirá cambios hasta que el usuario la confirme. No se permitirán importes negativos, reducciones superiores al disponible ni recortes aplicados a una actividad, partida o mes incompatible.

Una propuesta se convertirá en ajustada únicamente cuando:

- la reducción distribuida coincida exactamente con la requerida;
- los totales por actividad, partida y mes concilien;
- el total anual coincida con el ABPRE;
- no existan validaciones bloqueantes.

La versión calculada y la ajustada serán snapshots separados e inmutables.

## 4. Proyecciones derivadas

La Hoja de trabajo se derivará de la propuesta por actividad, partida, región y mes. El ABPRE se derivará por estructura institucional, partida, región y mes. Ninguno tendrá edición independiente dentro de la planeación.

Las proyecciones se usarán para:

- conciliación visible;
- validación del presupuesto inicial;
- comparación con las importaciones confirmadas;
- generación de los formatos oficiales.

La región presupuestal será siempre `02-001 — Felipe Carrillo Puerto`.

## 5. Cierre de Fase 3

### 5.1 Flujo de estados

El presupuesto avanzará por:

`draft → proposal_calculated → proposal_adjusted → initial_authorized`

- FinanceAssistant podrá capturar y corregir borradores.
- Owner, Admin y FinanceManager podrán crear desde importaciones, calcular, confirmar recortes y autorizar.
- FinanceAuditor tendrá acceso de consulta.

No se exige que una persona distinta realice la autorización. Se conservarán por separado el usuario y la fecha de preparación, cálculo, ajuste y autorización.

### 5.2 Revisión final

La pantalla de autorización mostrará el resultado de cada validación:

- propuesta ajustada vigente;
- cinco formatos confirmados vigentes;
- conciliación completa de actividades;
- recortes distribuidos;
- partidas válidas en COG;
- región fija;
- parámetros anuales completos;
- datos institucionales y firmantes completos;
- conciliación por actividad, partida, mes y total anual;
- ausencia de incidencias bloqueantes.

Una validación fallida impedirá autorizar y mostrará la acción necesaria para resolverla.

### 5.3 Autorización inicial

La autorización requerirá confirmación explícita. Dentro de una transacción se bloquearán presupuesto, propuesta ajustada, fuentes y parámetros; se comprobarán las huellas observadas y se creará el snapshot del presupuesto inicial.

El snapshot conservará:

- versión ajustada origen;
- archivos confirmados y sus versiones;
- parámetros y COG observados;
- líneas y mensualidades autorizadas;
- totales de control;
- usuario y fecha de autorización;
- huella canónica del contenido.

Después de autorizar, la planeación y el presupuesto inicial no podrán editarse. Los cambios posteriores pertenecerán exclusivamente al presupuesto modificado de la Fase 4.

## 6. Exportación XLSX

Se agregará `phpoffice/phpspreadsheet`, dependencia expresamente autorizada para este incremento.

Los cinco archivos oficiales se generarán desde el snapshot autorizado:

- ABPRE;
- Hoja de trabajo;
- Ficha técnica;
- Combustible;
- Viáticos.

No se regenerarán copiando los archivos importados. Cada exportación conservará formato, plantilla, ejercicio, usuario, fecha, hash y totales. Los archivos serán privados y se descargarán mediante autorización.

Las pruebas verificarán estructura de hojas, encabezados, tipos, importes, fórmulas necesarias, totales y compatibilidad de lectura. El diseño exacto de celdas se basará en las muestras oficiales vigentes del repositorio. Si falta una plantilla oficial, el incremento de ese formato se detendrá hasta recibirla; no se inventará una estructura sustituta.

## 7. Concurrencia y errores

Las solicitudes sensibles incluirán identificadores y huellas de la versión observada. Las acciones usarán transacciones con reintentos y `lockForUpdate()` sobre el presupuesto y los agregados afectados.

Si cambia una fuente, versión o parámetro durante una operación, toda la transacción se revertirá y se pedirá actualizar la página. No habrá cálculos ni recortes parciales.

Los mensajes serán operativos y evitarán nombres de tablas, campos internos, hashes o variables. Ejemplos:

- `La propuesta cambió; actualiza la página antes de continuar.`
- `Aún falta distribuir parte de la reducción requerida.`
- `Completa los firmantes antes de autorizar el presupuesto inicial.`
- `Los archivos confirmados cambiaron; vuelve a calcular la propuesta.`

## 8. Interfaz

La navegación permanecerá dentro de la misma ventana. El tablero del presupuesto mostrará:

- estado actual;
- versión de propuesta activa;
- totales y diferencias;
- lista de condiciones pendientes;
- accesos a Ficha técnica, Combustible, Viáticos, Recortes y Autorización;
- historial de versiones y comparación.

Los formularios extensos usarán páginas dedicadas; los detalles, correcciones y auditoría podrán usar ventanas modales. Para volúmenes grandes se utilizará paginación del servidor.

Las vistas de consulta ocultarán mutaciones, pero conservarán totales, diferencias, versiones y auditoría.

## 9. Pruebas

La implementación cubrirá:

- materialización exacta y transaccional desde los cinco archivos;
- rechazo de fuentes incompletas o desactualizadas;
- independencia entre importaciones y planeación;
- creación, edición, retiro y reordenamiento de necesidades;
- auditoría de correcciones manuales;
- cálculos de Ficha técnica;
- redondeo de Combustible en límites y múltiplos exactos;
- cálculos de Viáticos, UMA, zonas y participantes;
- precisión portable de importes y decimales;
- snapshots inmutables y creación de nuevos borradores;
- comparación con ABPRE;
- distribución manual y sugerencia proporcional de recortes;
- detección de cambios concurrentes;
- permisos por rol;
- validación final, autorización e inmutabilidad;
- generación y lectura de los cinco XLSX;
- navegación Inertia, formularios y estados de consulta;
- archivos reales representativos sin modificar evidencia confirmada durante la verificación.

Durante el desarrollo se ejecutarán pruebas dirigidas por tarea. La suite completa, frontend, tipos, lint y build se ejecutarán una sola vez al completar un incremento coherente.

## 10. Alcance excluido

Estos incrementos no incluyen:

- transferencias o recalendarizaciones;
- reservas, compromisos, pagos o expedientes de gasto;
- presupuesto modificado;
- fondo operativo y consumo real de combustible;
- comisiones extraordinarias de ejecución;
- reportes de ejercicio y cierre anual.

Esas capacidades pertenecen a las Fases 4 y 5.

## 11. Criterios de aceptación

El objetivo quedará completado cuando:

- pueda materializarse una propuesta desde las cinco importaciones confirmadas;
- la propuesta pueda editarse sin alterar evidencia importada;
- los cálculos especializados sean reproducibles y auditables;
- pueda congelarse una propuesta calculada;
- los recortes queden distribuidos y produzcan una propuesta ajustada conciliada;
- pueda autorizarse un presupuesto inicial inmutable;
- los cinco XLSX puedan regenerarse desde ese snapshot;
- todas las acciones respeten permisos y concurrencia;
- el historial permita reconstruir fuentes, versiones, correcciones, recortes y autorización;
- las pruebas dirigidas e integrales pasen.
