# Respaldo y restauración de U300 por ejercicio

## Objetivo

Permitir que una persona autorizada descargue un paquete autosuficiente de un
programa U300 y lo restaure posteriormente. La restauración sustituye por
completo el U300 del ejercicio fiscal indicado en el paquete; no afecta otros
ejercicios ni otros módulos.

## Alcance de un paquete

Cada archivo ZIP contiene un `manifest.json` versionado, los datos en JSON y
los archivos físicos asociados. El paquete incluye:

- El programa U300 y sus datos de identificación y responsables.
- Proyectos, metas y acciones.
- Versiones presupuestales, partidas, adecuaciones y conversiones al COG.
- Solicitudes y los importes y porcentajes del veredicto federal.
- Fichas técnicas, incluidos sus perfiles de bienes y fotos de referencia.
- Movimientos de ejecución, cancelaciones y sus datos de auditoría.
- El documento fuente importado, si está disponible.
- La referencia del COG por código y ejercicio, junto con los datos necesarios
  para comprobar su compatibilidad durante la restauración.

Los identificadores de base de datos no se exportan como referencias
restaurables. El manifiesto usa claves estables propias del paquete y conserva
el orden de los registros.

## Formato e integridad

El manifiesto declara la versión del formato, el ejercicio fiscal, fecha de
creación, conteos por entidad y hashes SHA-256 de todos los JSON y archivos.
Los archivos se guardan solamente bajo directorios controlados como
`data/`, `files/source/` y `files/technical-sheets/`.

El sistema rechaza paquetes que tengan una versión no compatible, rutas no
seguras, archivos faltantes, hashes que no coincidan, relaciones inválidas o un
ejercicio fiscal ausente. La validación ocurre antes de modificar datos.

## Generación

En el índice U300 se ofrece la acción **Generar respaldo** para cada ejercicio.
La descarga se nombra `u300-{ejercicio}-{fecha-hora}.zip`. Al concluir, se crea
un registro de bitácora con el usuario, fecha y hora, ejercicio, nombre,
tamaño, hash del paquete, conteos de contenido y resultado.

## Restauración

La acción **Restaurar respaldo** acepta un ZIP y muestra una vista previa con
el ejercicio, su contenido, conteos y la advertencia de reemplazo total. Sólo
puede continuar después de una confirmación explícita.

La restauración sigue este orden:

1. Validar por completo el paquete y comprobar que el COG identificado por
   código y ejercicio existe y es compatible.
2. Generar un respaldo preventivo del U300 actual del mismo ejercicio.
3. Iniciar una transacción de base de datos y eliminar ordenadamente el U300
   actual de ese ejercicio.
4. Crear el programa y reconstruir sus relaciones con nuevos identificadores.
5. Copiar los archivos validados a rutas controladas y asociarlos a sus fichas
   técnicas o al archivo fuente.
6. Confirmar la transacción y registrar el resultado de la restauración.

Si falla cualquier paso previo a la confirmación, se revierte la transacción,
se eliminan los archivos temporales y el U300 original permanece intacto. La
bitácora conserva el fallo y su causa. El respaldo preventivo permite volver al
estado anterior incluso tras una restauración confirmada por error.

## Bitácora y autorización

Se almacenan respaldos generados y operaciones de restauración. Cada entrada
incluye tipo de operación, ejercicio afectado, usuario, fecha y hora, paquete
relacionado, hash, resultado (`exitoso`, `fallido` o `cancelado`) y detalle.
Las entradas son inmutables y sólo se consultan por personal autorizado.

Las autorizaciones para generar paquetes, restaurarlos y consultar la bitácora
se definen de forma independiente. Restaurar requiere el permiso más
restrictivo.

## Pruebas de aceptación

- Un respaldo y restauración de U300 reconstruye todos los datos y archivos
  declarados en el paquete.
- La restauración de 2026 reemplaza exclusivamente el U300 2026.
- Un ZIP modificado, incompleto o de formato no compatible se rechaza sin
  alterar información existente.
- Una referencia COG ausente o incompatible detiene la operación antes del
  reemplazo.
- Un fallo durante la persistencia revierte la base de datos y no deja archivos
  definitivos incompletos.
- Las operaciones generan entradas completas de bitácora y se respetan sus
  permisos.
