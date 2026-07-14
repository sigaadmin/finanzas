# Reanálisis desde la vista previa

## Objetivo

Permitir que una persona con permiso para gestionar importaciones vuelva a analizar cualquiera de los cinco archivos desde su propia vista previa y permanezca en esa misma pantalla al finalizar.

## Alcance

- Aplica a ABPRE, Hoja de trabajo, Ficha técnica, Combustible y Viáticos.
- La acción se muestra en la cabecera de la vista previa cuando el archivo tiene formato asignado, el usuario puede gestionar importaciones y el archivo es mutable.
- Se consideran mutables los estados `uploaded`, `parser_pending`, `needs_correction`, `ready` y `failed`.
- La acción no se muestra para archivos `analyzing`, `confirmed`, `replaced` o `discarded`, ni cuando `confirmed_at` tenga valor.
- La etiqueta será “Volver a analizar” si el archivo ya fue analizado y “Analizar archivo” en caso contrario. Durante la solicitud mostrará “Analizando…”.

## Flujo

La interfaz reutilizará el endpoint de análisis existente y enviará un indicador booleano `return_to_preview`. El controlador validará ese indicador; al concluir el análisis redirigirá mediante una ruta nombrada a la vista previa del mismo presupuesto y archivo. Cuando el indicador no esté presente conservará el retorno actual al listado de importaciones.

No se aceptarán URLs de retorno proporcionadas por el cliente. La autorización y las reglas de mutabilidad seguirán residiendo en la acción de análisis del servidor.

## Interfaz

El control se ubicará en la cabecera común de la vista previa para evitar cinco implementaciones distintas. Usará el controlador Wayfinder existente, mostrará estado de procesamiento y presentará en la propia pantalla cualquier error de validación del análisis.

La matriz compartida de presentación reconocerá el estado `ready` como reanalizable para los cinco formatos. Esto mantendrá consistente la acción del listado con la acción de la vista previa.

## Pruebas

- Prueba de estado compartido: los cinco formatos permiten reanalizar en estados mutables y lo impiden en estados terminales o durante un análisis.
- Prueba HTTP: `return_to_preview` redirige a la vista previa y una solicitud ordinaria conserva el retorno al listado.
- Prueba de vista: el control aparece con permiso de gestión y no aparece para consulta o archivos no mutables.
- Pruebas de tipos, estilo y compilación del cliente.

## Fuera de alcance

- Crear otro endpoint de análisis.
- Abrir ventanas nuevas.
- Reanalizar archivos confirmados, reemplazados o descartados.
- Modificar el parser o las reglas de negocio de los cinco formatos.
