# Diseño del módulo de Presupuesto de Ingresos Propios

Fecha: 2026-07-13
Estado: aprobado para planeación de implementación

## 1. Propósito

Incorporar al portal financiero del CREN un módulo anual para planear, autorizar, ejercer y auditar el Presupuesto de Ingresos Propios. La plataforma sustituirá las hojas de cálculo como fuente operativa, pero conservará la capacidad de importar información existente y generar los formatos oficiales en Excel.

El primer ejercicio importable será 2026. La preparación de 2027 deberá poder tomar 2026 como base, actualizar sus parámetros y conservar únicamente los elementos de planeación reutilizables.

## 2. Principios del diseño

- La base de datos se convierte en la fuente oficial después de confirmar el presupuesto inicial autorizado.
- Los Excel son mecanismos de entrada, salida y evidencia; no gobiernan los cálculos internos.
- La propuesta, los recortes, el presupuesto inicial y el presupuesto modificado son momentos distintos y auditables.
- La región presupuestal es siempre `02-001 — Felipe Carrillo Puerto`, independientemente del destino de un viaje o recorrido.
- Los importes confirmados no se sobrescriben. Las modificaciones posteriores se registran mediante movimientos formales.
- El sistema nunca permitirá reservas, transferencias ni consumos operativos por encima del saldo disponible.
- Todas las reglas anuales relevantes quedan congeladas dentro del ejercicio que las utilizó.

## 3. Alcance funcional

El módulo incluirá:

- Configuración institucional y parámetros por ejercicio fiscal.
- Planeación de conceptos técnicos, viáticos y combustible.
- Copia de un ejercicio anterior.
- Importación parcial o completa de los formatos oficiales.
- Versionado de propuestas y recálculo por actualización de UMA.
- Distribución y conciliación de recortes.
- Confirmación del presupuesto inicial autorizado.
- Transferencias entre partidas y recalendarizaciones.
- Expedientes internos para solicitud y ejercicio de recursos.
- Listas de verificación y archivos de evidencia.
- Control operativo del combustible adquirido mediante vales.
- Reportes, auditoría y exportación de los cinco formatos oficiales.

No incluye integraciones directas con plataformas de SEQ, control de folios o denominaciones de vales, contabilidad por momentos legales completos ni ampliaciones presupuestales como flujo ordinario.

## 4. Ejercicio presupuestal anual

Cada ejercicio representa un año fiscal y contiene una copia histórica de:

- Nombre de la institución.
- Clave y nombre de la unidad responsable.
- Programa presupuestario.
- Componente.
- Actividad programática oficial.
- Región `02-001`.
- Firmantes, cargos y grados académicos.
- Ingresos estimados.
- Meta o porcentaje de recorte.
- UMA y su estado provisional o definitivo.
- Precio de combustible.
- Tarifas de viáticos y hospedaje.
- Clasificador por Objeto del Gasto vigente.

Estados del ejercicio:

1. Borrador de planeación.
2. Propuesta calculada.
3. Propuesta ajustada.
4. Presupuesto inicial autorizado.
5. En ejecución.
6. Cerrado.

El presupuesto inicial autorizado es inmutable. Los cambios posteriores afectan exclusivamente al presupuesto modificado.

## 5. Clasificador por Objeto del Gasto

El módulo reutilizará el catálogo `expense_classifications` que actualmente utiliza U300. No se creará un COG paralelo.

Cuando no exista un clasificador nuevo para el siguiente ejercicio, se copiará el último vigente y se marcará como pendiente de confirmación. Cada asignación confirmada conservará una referencia histórica suficiente —código, nombre y capítulo— para evitar que una actualización futura cambie la interpretación de ejercicios anteriores.

## 6. Planeación de necesidades

### 6.1 Conceptos de ficha técnica

Cada concepto tendrá:

- Actividad informativa A01, A02, A03 o A04.
- Partida COG.
- Descripción.
- Cantidad opcional.
- Unidad de medida opcional.
- Precio unitario opcional.
- Importe total presupuestario editable.
- Mes presupuestado.
- Impacto en metas.
- Justificación.

Cuando existan cantidad y precio unitario, se calculará un importe de referencia. El importe total seguirá siendo la cifra presupuestaria definitiva y podrá diferir del cálculo, conservando la diferencia para auditoría.

### 6.2 Comisiones y viáticos

La comisión almacenará los datos comunes del viaje y tendrá uno o varios participantes o perfiles comisionados. Incluirá:

- Motivo, fechas, mes y destino.
- Participantes, cargo y días de comisión.
- Zona de alimentación.
- Zona de hospedaje.
- UMA y tarifas aplicadas.
- Alimentación, hospedaje, transporte aéreo y total.

La zona se asignará automáticamente según el destino. Se utilizará la fila `Puestos no considerados en los anteriores`. Será posible corregir zona o tarifa manualmente, pero se guardarán el valor original, el nuevo valor, la justificación, el usuario y la fecha.

La UMA se fija por versión presupuestal. Un ejercicio futuro puede comenzar con UMA provisional. Cuando se publique la UMA definitiva, el sistema generará una nueva versión y recalculará integralmente todas las comisiones planeadas sin modificar la versión anterior.

### 6.3 Combustible planeado

Cada necesidad registrará:

- Fecha o mes operativo previsto.
- Motivo.
- Origen, destino y kilómetros.
- Vehículo y rendimiento.
- Kilómetros adicionales.
- Litros estimados.
- Precio por litro.
- Importe matemático.
- Importe al peso superior.
- Importe presupuestado al siguiente múltiplo de $50.
- Diferencia de redondeo.

Regla de redondeo: un cálculo de `$1,021.17` produce `$1,022` al peso superior y `$1,050` como importe presupuestado. Un importe que ya sea múltiplo exacto de $50 no se incrementa.

Los recorridos podrán seleccionarse desde un catálogo de origen, destino y kilómetros, con corrección puntual en una necesidad. El mes operativo conserva cuándo ocurrirá la comisión; el mes de aplicación presupuestal será abril, porque en ese mes se realiza la adquisición anual de vales.

## 7. Actividades y región

Las actividades A01–A04 son informativas y describen el origen de la planeación. Cada necesidad las almacenará directamente. Se podrán aplicar reglas editables por palabras clave, tipo de gasto, partida o recorrido; la clasificación resultante siempre podrá corregirse.

La región presupuestal no será editable por renglón. El ejercicio forzará `02-001` en capturas, importaciones y exportaciones. Los destinos de comisiones y recorridos no afectarán la región presupuestal.

## 8. Creación, copia e importación

Un ejercicio podrá iniciarse vacío, copiarse desde el año anterior, importarse desde Excel o combinar copia e importaciones parciales.

La copia anual incluirá necesidades, recorridos, comisiones, conceptos, justificaciones y reglas de clasificación. No incluirá ejecución, transferencias, reservas, compromisos ni consumos reales. UMA, combustible, fechas e importes quedarán marcados para revisión.

La importación:

1. Identifica formato y ejercicio.
2. Reconoce hojas y columnas por encabezados normalizados, no por posiciones rígidas.
3. Admite formatos oficiales limpios y archivos internos enriquecidos con hojas auxiliares.
4. Ignora datos de ejemplo, columnas auxiliares sin encabezado y fórmulas rotas.
5. Normaliza meses, partidas, cantidades, unidades e importes.
6. Fuerza la región `02-001`.
7. Relaciona partidas con el COG vigente.
8. Aplica reglas de actividad.
9. Presenta una vista previa con errores, advertencias y diferencias.
10. Confirma cada capa mediante una operación transaccional.

Las fichas aportan detalle. La hoja de trabajo aporta actividad y calendarización. ABPRE aporta el presupuesto final autorizado después de los recortes. Las diferencias entre archivos se presentan como cambios de versión y no se consideran automáticamente errores.

Se conservarán los archivos originales de forma privada con nombre, tipo, tamaño, hash, usuario y fecha de importación.

## 9. Propuestas, recortes y confirmación

Las versiones permitirán comparar:

`Propuesta original | Recorte | Propuesta ajustada | ABPRE final`

Los recortes deberán distribuirse entre necesidades concretas antes de confirmar. La plataforma podrá sugerir una distribución proporcional, pero el usuario decidirá qué necesidades reducir o eliminar. Esta distribución garantiza que combustible, viáticos, ficha técnica, hoja de trabajo y ABPRE puedan regenerarse con totales conciliados.

La confirmación del presupuesto inicial validará:

- Existencia de ABPRE.
- Partidas válidas en el COG.
- Región `02-001`.
- Totales mensuales y anuales conciliados.
- Parámetros anuales completos.
- Datos institucionales y firmantes completos.

La confirmación congela el presupuesto inicial y habilita transferencias y ejercicio.

## 10. Transferencias y recalendarizaciones

El presupuesto mostrará:

`Inicial | Entradas | Salidas | Modificado vigente`

Se permitirán dos modalidades:

1. Transferencia entre partidas diferentes del mismo capítulo y en el mismo mes.
2. Recalendarización de la misma partida desde un mes hacia un mes futuro.

Una sola operación no podrá cambiar simultáneamente partida y mes. El importe puede ser parcial o total, pero nunca excederá el saldo disponible del origen. Cada movimiento guardará fecha, motivo, usuario, origen, destino y distribución anterior y posterior.

La actividad informativa original no se modifica con las transferencias.

## 11. Expedientes de gasto

Cada solicitud de ejercicio será un expediente interno con este flujo:

1. Borrador.
2. Suficiencia solicitada: importe reservado.
3. Suficiencia confirmada: importe comprometido.
4. Compra o contratación en proceso.
5. Pago solicitado.
6. Autorizado por Finanzas.
7. Autorizado por el área presupuestal o Pagaduría.
8. Pagado.

También existirán rechazo y cancelación. Estos liberarán reservas o compromisos según corresponda y conservarán la razón y el historial.

El expediente incluirá partida, mes, concepto, importe, solicitante, responsable de la compra —CREN o SEQ—, referencias, fechas, observaciones y documentos.

Saldos:

- Presupuesto modificado.
- Reservado.
- Comprometido.
- Pagado.
- Disponible real.

El disponible real descuenta reservas y compromisos desde la solicitud para evitar solicitudes simultáneas sobre el mismo recurso. El pago mueve el importe de comprometido a pagado sin descontarlo nuevamente.

## 12. Requisitos y evidencias

Las listas de verificación serán configurables según responsable de compra, tipo de gasto, partida, capítulo, importe y etapa. Podrán exigir:

- Cotizaciones firmadas.
- Coincidencia del concepto entre suficiencia, orden y solicitudes de pago.
- Factura firmada.
- Factura y XML cargados en Docentes en Línea.
- Proveedor inscrito en el padrón estatal.
- Actividad económica compatible con el bien o servicio.
- Tabla comparativa y tres cotizaciones firmadas cuando el importe supere $15,000.
- Evidencias fotográficas con fecha y descripción.
- Solicitudes dirigidas a las áreas contable y presupuestal correspondientes.

Los requisitos aplicables son obligatorios y bloquean el avance. Una excepción sólo podrá autorizarla un administrador financiero y requerirá justificación y evidencia.

Los documentos serán privados, estarán protegidos por autorización y se validarán por tamaño, extensión y tipo real.

## 13. Control operativo de combustible

La compra anual de vales se ejerce presupuestalmente en abril mediante un expediente. El valor realmente adquirido abre un fondo operativo independiente.

Cada comprobación de comisión registrará:

- Fecha y mes.
- Motivo.
- Recorrido.
- Vehículo.
- Kilómetros.
- Litros.
- Importe total.
- Precio efectivo por litro calculado.
- Necesidad planeada relacionada, cuando exista.
- Justificación de comisión extraordinaria.
- Saldo posterior.

Se admitirán comisiones planeadas y extraordinarias. El fondo nunca podrá quedar negativo. Una comisión sin saldo suficiente permanecerá como necesidad pendiente y no podrá confirmarse.

Indicadores:

`Fondo adquirido | Consumo confirmado | Necesidades pendientes | Saldo disponible`

Comparación:

`Planeado | Real | Extraordinario | Variación`

## 14. Exportaciones y reportes

Se generarán los formatos oficiales limpios:

- Ficha para costeo de combustible.
- Ficha para costeo de viáticos.
- Ficha técnica.
- Hoja de trabajo de presupuestación.
- ABPRE-01 y justificación de partidas.

Las herramientas auxiliares existirán únicamente dentro de la plataforma. Las exportaciones no agregarán hojas auxiliares.

Se utilizarán plantillas versionadas, con reconocimiento por encabezados y secciones. Antes de exportar se validarán año, región, COG, totales, separación entre alimentación y hospedaje, aplicación de combustible en abril, fórmulas y datos institucionales.

Reportes internos:

- Resumen por capítulo, partida y mes.
- Inicial, modificado, reservado, comprometido, pagado y disponible.
- Transferencias y recalendarizaciones.
- Expedientes por etapa y requisitos pendientes.
- Comparación de versiones y UMA.
- Recortes entre propuesta y ABPRE.
- Planeado contra ejercido.
- Fondo operativo de combustible.
- Bitácora de cambios y excepciones.

Cada exportación conservará usuario, fecha, ejercicio, plantilla y totales.

## 15. Permisos

- Administrador financiero: configura ejercicios, confirma presupuesto inicial, registra transferencias y autoriza excepciones.
- Operador: importa, captura y corrige borradores; crea solicitudes, actualiza etapas y adjunta evidencias.
- Auditor o consulta: visualiza historial, saldos y exportaciones sin modificar información.

Las operaciones sensibles estarán protegidas en el servidor mediante políticas. La visibilidad de botones no sustituirá la autorización.

## 16. Arquitectura técnica

El módulo seguirá los patrones existentes del portal:

- Controladores delgados.
- Form Requests para autorización y validación.
- Acciones transaccionales para importación, confirmación, transferencias y estados.
- Servicios especializados para lectura y generación de Excel.
- Políticas por ejercicio y expediente.
- Bloqueos de base de datos para impedir sobregiros y carreras.
- Pantallas Inertia/React con tablas compactas, filtros, pestañas y paneles de saldo.

Grupos de persistencia:

- Ejercicios y configuración institucional anual.
- Parámetros, tarifas, destinos y recorridos.
- Necesidades planeadas especializadas.
- Versiones y asignaciones mensuales.
- Transferencias y recalendarizaciones.
- Expedientes, etapas, requisitos y adjuntos.
- Fondo y consumos de combustible.
- Importaciones, plantillas, exportaciones y auditoría.

Los importes se almacenarán como enteros en centavos. UMA, litros, kilómetros, rendimientos y precios unitarios usarán decimales con precisión explícita. Las reglas de redondeo estarán encapsuladas y cubiertas por pruebas.

La solución será compatible con MySQL y no dependerá de Redis, procesos permanentes ni servicios exclusivos del entorno local.

## 17. Estrategia de pruebas

La implementación deberá cubrir con Pest:

- Importación de variantes 2026 y 2027.
- Importaciones parciales y confirmación transaccional.
- Copia del ejercicio anterior.
- UMA provisional y recálculo integral.
- Redondeo de combustible en límites y múltiplos exactos.
- Región fija `02-001`.
- COG vigente y copiado pendiente de confirmación.
- Distribución y conciliación de recortes.
- Inmutabilidad del presupuesto inicial.
- Modalidades y límites de transferencias.
- Reservas, compromisos, pagos y liberaciones.
- Prevención de sobregiros concurrentes.
- Transiciones y requisitos bloqueantes.
- Archivos privados y permisos por rol.
- Control operativo y comisiones extraordinarias.
- Estructura, valores, fórmulas y totales de los cinco Excel.

## 18. Criterios de aceptación

El diseño se considerará implementado cuando:

- Pueda importarse 2026 y reconstruirse su propuesta y ABPRE autorizado.
- Pueda crearse 2027 copiando 2026 y recalculando parámetros revisados.
- Todos los registros utilicen la región `02-001`.
- Los recortes queden distribuidos y los cinco formatos concilien.
- El presupuesto inicial confirmado sea inmutable.
- Las transferencias cumplan capítulo, mes y temporalidad.
- Ninguna reserva, transferencia o comisión produzca saldo negativo.
- Los expedientes no avancen con requisitos obligatorios pendientes.
- El fondo operativo de combustible sea independiente del ejercicio de abril.
- Los tres roles respeten sus permisos.
- Las exportaciones oficiales sean legibles, estructuralmente válidas y libres de errores de fórmula.
