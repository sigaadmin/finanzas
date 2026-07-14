# Control de alcance y consumo en entregas asistidas

Fecha: 2026-07-14
Estado: vigente

## Propósito

Evitar que una entrega parcial se comunique como terminada y reducir el consumo innecesario durante implementaciones amplias. Estas reglas complementan las especificaciones funcionales del proyecto y se aplican a futuros trabajos asistidos.

## Unidad de entrega

La unidad de entrega es el resultado solicitado por el usuario, no el último componente implementado. Cuando el alcance enumere varios formatos, pantallas o flujos, todos deben aparecer desde el inicio en una matriz con uno de estos estados:

- pendiente;
- en desarrollo;
- analizable;
- revisable;
- confirmable;
- terminado;
- bloqueado por una dependencia identificada.

No se podrá declarar completado el objetivo general mientras algún elemento permanezca pendiente o bloqueado. Una integración a `main` sólo significa que el incremento actual fue integrado; no implica que el objetivo funcional completo esté terminado.

## Contrato previo a la implementación

Antes de modificar código se deberá registrar, en una sola revisión del repositorio:

1. Los elementos incluidos en el resultado completo.
2. El estado real de cada elemento.
3. Las dependencias que todavía no existen.
4. El incremento útil que puede terminarse con los recursos disponibles.
5. Las acciones expresamente excluidas del incremento.

Las decisiones ya aprobadas en una especificación vigente no se volverán a consultar. Sólo se pedirá intervención cuando falte una decisión que cambie datos, arquitectura o reglas de negocio.

## Presupuesto de uso

El trabajo se organizará para minimizar ciclos repetidos:

- una exploración consolidada del código y de los archivos reales;
- infraestructura compartida antes de adaptadores específicos;
- pruebas dirigidas durante el desarrollo;
- una sola ejecución de la suite completa, compilación y revisión visual al final;
- sin subagentes, revisiones duplicadas ni documentación adicional salvo que aporten un resultado solicitado;
- sin repetir verificaciones que ya cuenten con evidencia vigente y no hayan sido afectadas por cambios posteriores.

Si el usuario informa que queda menos de 20 % de su límite, se priorizará código funcional y pruebas dirigidas. La verificación integral se ejecutará una sola vez cuando exista un incremento coherente. Si queda menos de 10 %, se evitarán mejoras cosméticas y se dejará un punto de reanudación verificable antes de iniciar otro subsistema.

## Comunicación de progreso

Cada entrega deberá indicar por separado:

- qué quedó funcionando;
- qué sigue pendiente;
- qué depende de trabajo futuro;
- qué pruebas se ejecutaron;
- si los cambios sólo están locales, integrados o publicados.

Las expresiones “terminado”, “listo” o “resuelto” se reservan para el alcance completo descrito. Para avances parciales se usarán “incremento integrado”, “análisis habilitado” o “pendiente de confirmación”.

## Aplicación al Presupuesto de Ingresos Propios

El objetivo comprende cinco formatos y su estado actual es:

| Formato | Análisis | Vista previa | Confirmación |
| --- | --- | --- | --- |
| ABPRE | implementado | implementada | implementada |
| Hoja de trabajo | implementado | implementada | implementada con decisiones ABPRE |
| Ficha técnica | pendiente | pendiente | depende de las entidades de necesidades de la Fase 2 |
| Combustible | pendiente | pendiente | depende de recorridos y necesidades de combustible de la Fase 2 |
| Viáticos | pendiente | pendiente | depende de comisiones, participantes y tarifas de la Fase 2 |

El siguiente incremento útil habilitará análisis, incidencias y vista previa para Ficha técnica, Combustible y Viáticos usando la infraestructura temporal existente. No se presentará como confirmación definitiva ni escribirá entidades presupuestarias que todavía no existen.

## Criterio de reanudación

Al interrumpir el trabajo se dejarán registrados el último commit válido, las pruebas aprobadas, los archivos pendientes y la siguiente prueba roja o tarea concreta. La reanudación partirá de ese punto y no repetirá el descubrimiento completo salvo que el repositorio haya cambiado de manera relevante.
