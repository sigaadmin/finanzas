# Especificacion general para plataformas institucionales CREN

## Proposito

Esta especificacion define la base reusable para crear nuevas plataformas dentro del ecosistema institucional del CREN, tomando a SIGA2 como referencia operativa.

Debe usarse cuando se quiera construir otro sistema institucional, por ejemplo Finanzas, Biblioteca, Recursos Humanos, Seguimiento Docente o cualquier modulo administrativo conectado al entorno CREN.

El documento separa:

- reglas transversales que deben conservarse en cualquier aplicacion CREN;
- decisiones tecnicas base que ya funcionan en SIGA2;
- reglas de negocio, autenticacion, autorizacion y gobierno de datos;
- un apartado editable para capturar los requerimientos propios de la nueva plataforma.

## Principios institucionales

Toda plataforma CREN debe comportarse como parte de un mismo ecosistema, no como un sitio aislado.

Principios obligatorios:

- La identidad institucional se valida con correo del dominio `crenfcp.edu.mx`.
- La autenticacion externa no equivale a autorizacion interna.
- El acceso a la aplicacion se abre de forma administrativa y explicita.
- El usuario propietario institucional conserva control superior sobre usuarios, roles y configuracion.
- Las acciones sensibles se autorizan en servidor; ocultar botones en la interfaz no es suficiente.
- Las reglas de negocio deben quedar expresadas en modelos, acciones, servicios, form requests, policies, gates o middleware, no solo en vistas.
- Las pantallas internas deben favorecer operacion diaria: tablas, filtros, formularios compactos, estados claros, trazabilidad y acciones predecibles.
- Cada cambio de comportamiento debe cubrirse con pruebas automatizadas.

## Stack base recomendado

La plataforma nueva debe conservar el stack institucional salvo que exista una razon aprobada para cambiarlo:

- Laravel como framework principal.
- Fortify como base de autenticacion.
- Socialite para autenticacion OAuth con Google institucional.
- Spatie Permission para roles y permisos.
- Livewire para interfaces reactivas del lado servidor.
- Flux UI para componentes de formularios, modales, botones, badges, tabs y tablas.
- Tailwind CSS para estilos.
- Pest para pruebas automatizadas.
- Laravel Boost para inspeccion de la aplicacion, base de datos y documentacion versionada.
- Base de datos MySQL-compatible para produccion.
- Despliegue PHP/MySQL compatible con Hostinger.

Adaptacion para este portal: el repositorio `finanzas` ya esta creado con Inertia React 3, Wayfinder y Tailwind CSS 4. Para el Portal Financiero se usara ese stack de interfaz, conservando los principios institucionales de operacion densa, formularios compactos, tablas, filtros, estados visibles y autorizacion en servidor.

La plataforma no debe depender de Redis, workers, colas permanentes, Docker, servicios Node en produccion o procesos de larga duracion, a menos que el ambiente de despliegue lo soporte y quede aprobado.

## Identidad, autenticacion y acceso

### Autenticacion principal

El acceso de usuarios internos debe realizarse con Google OAuth mediante Socialite.

Reglas:

- Solo se aceptan correos del dominio institucional configurado, por defecto `crenfcp.edu.mx`.
- Las credenciales OAuth viven en `.env` y `config/services.php`.
- Google prueba identidad, pero no decide si la persona puede entrar.
- El sistema no debe habilitar registro publico para obtener privilegios.
- En produccion, el flujo normal no debe depender de contrasena local para usuarios institucionales.

### Acceso autorizado

Cada plataforma debe tener una tabla equivalente a `authorized_accesses`, que representa el padron interno de personas autorizadas.

Campos minimos:

- `email`
- `role`
- `is_active`
- vinculo opcional con una entidad de negocio, si aplica
- `last_used_at`
- timestamps

Reglas:

- El correo se normaliza en minusculas y sin espacios.
- El correo debe ser unico en el padron de acceso.
- Un acceso inactivo bloquea el inicio de sesion aunque Google autentique correctamente.
- El acceso autorizado puede crear o sincronizar el usuario interno en el primer login valido.
- Si el usuario interno existe pero esta inactivo, se rechaza el acceso.
- `last_used_at` solo se actualiza despues de un login exitoso.

### Cuenta propietaria institucional

Toda aplicacion CREN debe garantizar una cuenta propietaria inicial:

`administrador.siga@crenfcp.edu.mx`

Aunque el correo incluya `siga`, debe tratarse como cuenta propietaria institucional por defecto, salvo que el proyecto defina otra cuenta aprobada.

Responsabilidades del propietario:

- administrar accesos autorizados;
- activar o inactivar usuarios;
- asignar roles;
- abrir acceso a administradores;
- custodiar configuracion sensible;
- ejecutar acciones reservadas de alto impacto.

### Roles base

Roles institucionales minimos:

- `owner`: propietario permanente de la aplicacion, con control superior.
- `admin`: administrador operativo con capacidad de gestionar datos y procesos institucionales.
- `public`: usuario autenticado con permisos limitados o contexto especifico.

La plataforma puede agregar roles propios, por ejemplo `finance-admin`, `cashier`, `auditor` o `viewer`, pero no debe eliminar los roles base.

Reglas:

- El rol inicial proviene del acceso autorizado.
- Los privilegios no se conceden automaticamente por existir en una tabla de negocio.
- Las acciones reservadas de propietario deben comprobar `owner`.
- Las acciones administrativas generales deben comprobar `owner` o `admin`, salvo que el modulo defina permisos mas finos.
- Los permisos se validan con roles, policies, gates, middleware o metodos de dominio.

### Auto-login local

Puede existir auto-login solo para desarrollo local.

Reglas:

- Debe estar deshabilitado fuera del entorno `local`.
- Debe usar un correo configurado.
- Debe pasar por la misma resolucion de acceso autorizado.
- Nunca debe debilitar el flujo OAuth real.

## Autorizacion y politicas

Toda plataforma debe distinguir:

- autenticacion: quien es la persona;
- autorizacion: que puede hacer;
- contexto de negocio: sobre que entidad puede operar.

Patrones aceptados:

- middleware para proteger rutas completas;
- policies para acciones sobre modelos;
- gates para capacidades globales;
- metodos del modelo `User` para reglas transversales sencillas;
- servicios o actions para reglas de negocio que afectan varias entidades.

Reglas:

- Las rutas internas usan `auth` y, cuando aplique, `verified`.
- Las rutas publicas deben tener alcance limitado, throttling y no exponer informacion sensible.
- Las APIs internas deben autenticarse con token, OAuth client, firma o mecanismo equivalente aprobado.
- Toda accion de escritura debe validar permisos en servidor.
- Toda accion sensible debe tener prueba de usuario autorizado y no autorizado.

## Modelo de datos institucional

Las plataformas deben conservar una separacion clara entre:

- `users`: cuenta operativa interna;
- `authorized_accesses`: padron administrativo de entrada;
- entidades de negocio: estudiantes, docentes, pagos, constancias, expedientes, solicitudes, etc.;
- roles y permisos: capacidades sobre el sistema.

Reglas:

- Una entidad de negocio no debe ser por si misma una cuenta de acceso.
- Si una cuenta se vincula con una entidad de negocio, ese vinculo debe ser opcional, explicito y validado.
- Las migraciones deben ser reversibles y MySQL-compatible.
- Usar enums para estados de negocio cuando el estado controle flujos.
- Usar `legacy_id` solo cuando exista integracion o migracion desde sistemas previos.
- Preservar trazabilidad con `created_at`, `updated_at`, usuario responsable y fechas operativas cuando aplique.

## Integraciones y APIs

SIGA2 ya expone una API interna de Finanzas protegida por token y una API publica de constancias con throttling y token/firma segun el caso. Las nuevas plataformas deben seguir esta separacion.

Tipos de API:

- API interna: intercambio entre plataformas CREN.
- API publica: consulta limitada por terceros o usuarios externos.
- API firmada: descarga o acceso temporal a un recurso concreto.

Reglas:

- Versionar rutas: por ejemplo `/api/internal/finance/v1`.
- Proteger APIs internas con token o mecanismo aprobado.
- Configurar secretos en `.env`, nunca en codigo.
- Usar Form Requests para validar entrada.
- Usar Resources para respuestas JSON estables.
- Aplicar throttling a APIs publicas.
- No exponer datos personales innecesarios.

## Interfaz y experiencia operativa

Las plataformas CREN son herramientas institucionales internas.

La interfaz debe:

- usar layouts, navegacion, tablas, formularios, modales y badges consistentes con SIGA2;
- presentar informacion densa pero legible;
- usar estados visibles para activo, inactivo, abierto, cerrado, emitido, cancelado, pagado o pendiente;
- favorecer busqueda, filtros y acciones repetidas;
- usar nombres de rutas y configuracion de navegacion;
- ocultar pantallas no disponibles para el rol, sin sustituir la autorizacion de servidor;
- evitar portadas tipo marketing dentro de sistemas internos.

## Pruebas y calidad

Toda implementacion debe incluir pruebas automatizadas.

Cobertura minima:

- autenticacion y rechazo de usuarios no autorizados;
- autorizacion por rol o permiso;
- validacion de formularios;
- flujo feliz de cada proceso principal;
- fallos relevantes del negocio;
- APIs internas y publicas;
- visibilidad de navegacion por rol;
- reglas de estado y transiciones.

Comandos base:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Para cambios pequenos, ejecutar el subconjunto de pruebas afectado.

## Especificacion inicial del Portal Financiero CREN

Este apartado captura los requerimientos propios del Portal Financiero del CREN. Debe actualizarse durante la planeacion antes de escribir codigo de producto.

Plan de tareas: `docs/superpowers/plans/2026-06-03-portal-financiero-cren.md`.

Estado de avance de la primera implementacion:

- Se usa control de acceso por `authorized_accesses` y roles financieros `finance-manager`, `finance-assistant` y `finance-auditor`.
- El flujo base permite crear conceptos, iniciar tramites con estudiante obtenido de SIGA2, registrar pago y generar recibos.
- Al pagar, se genera un recibo interno por el total del tramite y un recibo externo por cada concepto externo.
- Los importes se manejan como pesos enteros; el sistema no captura ni calcula centavos.
- Cada recibo externo puede registrar un deposito SEQ individual, con importe exacto 1:1 contra el recibo.
- Cada recibo conserva folio, token de validacion publica, cantidad en letras, estado y datos congelados del estudiante/tramite.
- El reporte SEQ ya existe como vista filtrable por periodo y exporta una primera version `.xls` que respeta los filtros visibles. El XLSX con plantilla oficial queda pendiente de aprobacion de formato.
- La cancelacion de recibos ya exige rol permitido y motivo, y guarda auditoria con usuario y fecha.
- La navegacion inicial ya parte de dashboard, listados, detalles y acciones especificas.

### 1. Identidad del proyecto

- Nombre de la plataforma: Portal Financiero del CREN.
- Siglas o identificador corto: Finanzas CREN.
- Area responsable: Oficina de Finanzas del CREN.
- Personas propietarias del proceso: responsable de Finanzas y personal directivo autorizado.
- Usuarios principales: responsable de Finanzas y dos auxiliares de la oficina.
- Ambiente de produccion esperado: `finanzas.crenfcp.edu.mx`.
- Repositorio remoto: `git@github.com:sigaadmin/finanzas.git`.
- Stack operativo de este repositorio: Laravel 13, Fortify, Inertia React 3, Wayfinder, Tailwind CSS 4 y Pest 4.

### 2. Objetivo institucional

Ordenar y registrar los cobros institucionales del CREN mediante un flujo unico de tramite, seleccion de conceptos, vinculacion con estudiantes provenientes de SIGA2, calculo de importes, registro de pago, emision de recibos foliados y consulta trazable de transacciones.

El portal debe sustituir controles manuales dispersos por un registro financiero centralizado, auditable y operable por la oficina de Finanzas.

### 3. Alcance

Incluido:

- Administracion del catalogo de conceptos de cobro.
- Clasificacion de conceptos como internos o externos para separar uso institucional y reporte mensual a Finanzas de la SEQ.
- Inicio de tramites de pago para estudiantes.
- Seleccion de uno o varios conceptos de cobro en una misma transaccion.
- Consulta o sincronizacion del listado de estudiantes desde SIGA2.
- Calculo automatico del total con base en conceptos seleccionados.
- Registro de la transaccion y del estado del pago.
- Emision e impresion de recibo interno del tramite, con uno o varios conceptos.
- Emision adicional de recibos externos tipo SEQ, uno por cada concepto externo involucrado.
- Registro de deposito SEQ por cada recibo externo, con fecha, folio bancario, tipo de deposito, concepto e importe exacto.
- Busqueda, filtros y consulta historica de transacciones.
- Cancelacion controlada de recibos o transacciones, con motivo y usuario responsable.
- Exportacion basica para revision administrativa y reporte mensual de conceptos externos.
- Navegacion jerarquica que lleve de vistas generales a detalles especificos, evitando concentrar todo el flujo en una sola ventana.

Fuera de alcance:

- Cobro en linea con pasarela bancaria.
- Facturacion fiscal electronica.
- Contabilidad completa, polizas o conciliacion bancaria automatica.
- Edicion directa de expedientes academicos de estudiantes.
- Registro publico de estudiantes o usuarios externos.

### 4. Roles especificos

Conservar `owner`, `admin` y `public`. Agregar aqui los roles propios:

| Rol | Proposito | Permisos principales | Restricciones |
| --- | --- | --- | --- |
| `finance-manager` | Responsable operativa de Finanzas | Gestionar catalogos, registrar pagos, cancelar con motivo, consultar reportes y administrar auxiliares | No sustituye al `owner` para configuracion global |
| `finance-assistant` | Auxiliar de la oficina de Finanzas | Crear tramites, seleccionar estudiante y conceptos, registrar pagos e imprimir recibos | No configura catalogos ni cancela transacciones sin permiso superior |
| `finance-auditor` | Revision administrativa o directiva | Consultar transacciones, recibos y reportes | Solo lectura; no crea, edita ni cancela |

### 5. Procesos principales

| Proceso | Actor | Entrada | Resultado | Estados |
| --- | --- | --- | --- | --- |
| Gestionar catalogo de conceptos | `finance-manager` | Nombre, descripcion, importe, tipo interno/externo, clave contable o administrativa, vigencia, estado | Concepto disponible para tramites y reportes | Activo, inactivo |
| Iniciar tramite de pago | `finance-manager`, `finance-assistant` | Estudiante seleccionado desde SIGA2, uno o varios conceptos de cobro | Tramite con total calculado | Borrador, pendiente de pago |
| Registrar pago | `finance-manager`, `finance-assistant` | Tramite pendiente, forma de pago, importe recibido, referencia opcional | Transaccion pagada y recibos emitibles | Pagado |
| Emitir e imprimir recibo interno | `finance-manager`, `finance-assistant` | Transaccion pagada | Recibo interno foliado con todos los conceptos del tramite | Emitido, reimpreso |
| Emitir e imprimir recibos externos SEQ | `finance-manager`, `finance-assistant` | Transaccion pagada con conceptos externos | Un recibo externo por cada concepto externo | Emitido, reimpreso |
| Registrar deposito SEQ | `finance-manager`, `finance-assistant` | Recibo externo emitido, fecha de deposito, folio bancario, tipo, concepto e importe | Deposito bancario vinculado 1:1 al recibo externo | Pendiente de deposito, registrado |
| Cancelar transaccion o recibo | `owner`, `finance-manager` | Transaccion, motivo de cancelacion | Registro cancelado con trazabilidad | Cancelado |
| Consultar y exportar movimientos | `owner`, `finance-manager`, `finance-auditor` | Filtros por fecha, estudiante, concepto, tipo interno/externo, folio, estado | Listado o archivo exportable | Consultado |
| Preparar reporte mensual SEQ | `finance-manager` | Mes, filtros aplicados, conceptos externos, transacciones pagadas no canceladas | Vista filtrable y exportacion Excel con formato SEQ | Consultado, exportado |

### 5.1 Diseno jerarquico de navegacion

La interfaz debe organizarse de lo mas externo a lo mas interno:

- Nivel 1: dashboard y resumen operativo del dia.
- Nivel 2: listados filtrables por modulo, por ejemplo tramites, recibos, conceptos y reportes.
- Nivel 3: detalle de un registro seleccionado, por ejemplo tramite, recibo o reporte mensual.
- Nivel 4: acciones especificas dentro del detalle, como registrar pago, imprimir recibo, cancelar, exportar o validar.

Reglas:

- No apilar todo el proceso en una sola pantalla.
- Evitar modales grandes para flujos completos; usarlos solo para confirmaciones, ediciones pequenas o acciones puntuales.
- Mantener listados, detalles, formularios e impresion como superficies separadas cuando el flujo tenga suficiente informacion propia.
- Cada pantalla debe tener una tarea principal clara y acciones secundarias predecibles.
- El usuario debe poder volver del detalle al listado conservando filtros cuando sea posible.

### 6. Entidades de negocio

| Entidad | Descripcion | Campos clave | Relaciones |
| --- | --- | --- | --- |
| `ChargeConcept` | Concepto institucional de cobro | nombre, descripcion, importe, tipo financiero, permite cantidad variable, estado, vigencia, clave interna | Se usa en partidas de tramite y reportes |
| `OfficialFeeSchedule` | Publicacion oficial anual de cuotas o derechos | ejercicio fiscal, fuente, URL, fecha de publicacion, estado, notas | Contiene conceptos oficiales publicados para un ejercicio |
| `OfficialFeeConcept` | Concepto oficial publicado en el DOF u otra fuente aprobada | clave oficial, nombre oficial, importe oficial, notas | Pertenece a una publicacion anual; puede vincularse a conceptos operativos |
| `ChargeConceptOfficialLink` | Vinculo anual entre concepto operativo y concepto oficial | concepto operativo, ejercicio fiscal, estado de vinculo, concepto oficial opcional, notas | Permite estados `enlazado`, `no aplica DOF` o `pendiente de revision` |
| `StudentSnapshot` | Datos minimos del estudiante obtenidos de SIGA2 al momento de operar | siga_student_id, matricula, nombre, carrera, semestre o grupo, estado academico | Se vincula a tramites y recibos |
| `PaymentProcedure` | Tramite iniciado antes de confirmar pago | folio temporal o identificador, estudiante, estado, total, usuario creador | Contiene partidas de cobro |
| `PaymentProcedureItem` | Concepto apilado dentro de un tramite | concepto, cantidad, precio unitario, subtotal, snapshot de clave/nombre oficial si aplica | Pertenece a un tramite y conserva el historico del vinculo oficial usado |
| `PaymentTransaction` | Registro financiero confirmado | folio, fecha de pago, total, forma de pago, estado, usuario responsable | Pertenece a un tramite y genera recibos |
| `Receipt` | Comprobante imprimible | folio, tipo interno/externo, fecha de emision, datos del estudiante, conceptos o concepto asociado, total, estado | Pertenece a una transaccion; puede ser interno o externo |
| `ExternalReceipt` | Recibo con formato SEQ para ingresos propios | folio, fecha, bueno_por, estudiante, grupo, cantidad_letra, concepto, importe exacto, QR, estado | Pertenece a un recibo externo y a una partida externa |
| `SeqDeposit` | Evidencia del deposito bancario a SEQ | recibo externo, fecha de deposito, folio de transaccion bancaria, tipo de deposito, concepto, importe en pesos, usuario responsable | Pertenece a un recibo externo; la relacion es 1:1 |
| `ReceiptCancellation` | Trazabilidad de cancelaciones | recibo, motivo, usuario, fecha | Pertenece a un recibo |
| `MonthlySeqReport` | Concentrado mensual de ingresos externos reportables a Finanzas de la SEQ | mes, periodo, filtros, total, estado, usuario generador, fecha de exportacion | Agrupa transacciones externas pagadas y conserva la trazabilidad de exportaciones |

### 7. Reglas de negocio

Registrar reglas en formato verificable:

- Regla: un tramite debe tener al menos un concepto de cobro.
  Condicion: la persona intenta guardar o pagar un tramite sin partidas.
  Resultado esperado: el sistema rechaza la accion con validacion clara.
  Excepciones: ninguna.
  Prueba requerida: validacion de formulario y prueba de flujo.

- Regla: los conceptos pueden apilarse en una misma transaccion.
  Condicion: la persona selecciona dos o mas conceptos para el mismo estudiante.
  Resultado esperado: el total es la suma de subtotales y cada concepto aparece en el recibo interno.
  Excepciones: conceptos inactivos no pueden seleccionarse.
  Prueba requerida: flujo feliz con multiples conceptos.

- Regla: todos los importes se manejan como pesos enteros.
  Condicion: Finanzas captura un concepto, crea un tramite, registra pago, emite recibos o registra deposito SEQ.
  Resultado esperado: los importes se guardan y muestran como pesos completos, sin conversion a centavos ni decimales.
  Excepciones: la cantidad en letras puede conservar la leyenda `00/100 M.N.` por formato tradicional de recibo, sin habilitar captura de centavos.
  Prueba requerida: catalogo de conceptos expone `amount_pesos` y los flujos calculan `total_pesos`, `subtotal_pesos` e importes de recibo en pesos.

- Regla: cada concepto debe clasificarse como interno o externo.
  Condicion: se crea o actualiza un concepto de cobro.
  Resultado esperado: el sistema exige el tipo financiero y lo conserva en las partidas del tramite.
  Excepciones: ninguna.
  Prueba requerida: validacion del catalogo y persistencia del tipo en partidas.

- Regla: solo los conceptos internos pueden permitir cantidad variable.
  Condicion: Finanzas crea o edita un concepto de cobro.
  Resultado esperado: los conceptos externos SEQ quedan siempre con cantidad 1; los conceptos internos pueden marcarse como cantidad variable cuando el cobro permita solicitar mas de una unidad, por ejemplo constancias.
  Excepciones: si un concepto externo se envia con cantidad mayor a 1, el sistema lo registra como una sola unidad.
  Prueba requerida: concepto interno con cantidad variable calcula subtotal por cantidad; concepto externo enviado con cantidad mayor a 1 se guarda como cantidad 1.

- Regla: la vinculacion DOF de un concepto operativo es anual y opcional.
  Condicion: se revisa un concepto de cobro para un ejercicio fiscal.
  Resultado esperado: el concepto puede quedar `enlazado` a una clave oficial, `no aplica DOF` cuando es manejo interno sin publicacion oficial, o `pendiente de revision`.
  Excepciones: un concepto marcado como `enlazado` debe referenciar un concepto oficial capturado para el ejercicio.
  Prueba requerida: crear concepto oficial anual, enlazar concepto operativo y marcar otro concepto como no aplicable.

- Regla: la fuente predeterminada para captura oficial es el Periodico Oficial del Estado de Quintana Roo.
  Condicion: se registra un concepto oficial anual desde la pantalla de catalogos.
  Resultado esperado: el formulario propone esa fuente por defecto, aunque permite capturar URL, fecha de publicacion y datos propios del ejercicio.
  Excepciones: si una publicacion aprobada proviene de otra fuente, la responsable de Finanzas puede capturar el nombre de fuente correspondiente.
  Prueba requerida: captura de concepto oficial con fuente estatal y consulta posterior.

- Regla: el catalogo oficial anual debe poder consultarse sin editar ni eliminar registros historicos.
  Condicion: la persona filtra el catalogo por ejercicio fiscal.
  Resultado esperado: el sistema muestra un listado de solo lectura con clave oficial, nombre, fuente, fecha de publicacion e importe para el ejercicio seleccionado.
  Excepciones: si el ejercicio no tiene registros, se muestra estado vacio.
  Prueba requerida: consulta de conceptos oficiales por ejercicio y exclusión de conceptos de otros años.

- Regla: las partidas conservan snapshot de la vinculacion oficial usada.
  Condicion: se crea un tramite con conceptos que tienen vinculo DOF, no aplican DOF o estan pendientes.
  Resultado esperado: cada partida guarda ejercicio fiscal, estado de vinculo, clave oficial, nombre oficial e importe oficial cuando aplique; los recibos historicos no cambian aunque el catalogo oficial se modifique despues.
  Excepciones: conceptos `no aplica DOF` guardan el estado sin clave oficial.
  Prueba requerida: creacion de tramite con concepto enlazado y verificacion del snapshot.

- Regla: los conceptos internos y externos pueden convivir en un mismo tramite y en el recibo interno.
  Condicion: la persona apila conceptos de ambos tipos en una transaccion.
  Resultado esperado: el recibo interno muestra el desglose completo, y los reportes separan los importes por tipo.
  Excepciones: conceptos inactivos no pueden seleccionarse.
  Prueba requerida: flujo con conceptos mixtos y validacion de totales por tipo.

- Regla: los recibos externos se generan de forma individual por concepto externo.
  Condicion: una transaccion pagada incluye uno o varios conceptos externos.
  Resultado esperado: el sistema genera un recibo externo por cada partida externa, cada uno con el monto exacto del concepto correspondiente.
  Excepciones: conceptos internos no generan recibo externo SEQ.
  Prueba requerida: tramite con dos conceptos externos y un concepto interno genera un recibo interno y dos recibos externos.

- Regla: cada recibo externo debe tener un deposito SEQ individual.
  Condicion: Finanzas realiza el deposito bancario a la cuenta SEQ de la institucion.
  Resultado esperado: el deposito se registra sobre un recibo externo especifico, con fecha, folio de transaccion, tipo de deposito, concepto e importe exacto del recibo.
  Excepciones: no se permite registrar deposito SEQ para recibos internos, recibos cancelados o recibos externos que ya tengan deposito registrado.
  Prueba requerida: recibo externo acepta un deposito con importe exacto, rechaza importes diferentes y rechaza un segundo deposito.

- Regla: los conceptos externos se reportan mensualmente a Finanzas de la SEQ.
  Condicion: se genera un reporte mensual.
  Resultado esperado: el reporte incluye solo recibos externos de transacciones pagadas, no canceladas, asociadas a conceptos externos dentro del periodo.
  Excepciones: recibos cancelados quedan excluidos o marcados como cancelados segun el formato de reporte aprobado.
  Prueba requerida: reporte mensual con pagos internos, externos y cancelados.

- Regla: el reporte mensual SEQ se revisa primero como vista filtrable.
  Condicion: Finanzas consulta el reporte mensual antes de exportarlo.
  Resultado esperado: el sistema muestra una vista filtrable por periodo, concepto, estudiante, folio, fecha, estado y otros filtros aprobados; la exportacion Excel respeta exactamente los filtros visibles.
  Excepciones: ninguna.
  Prueba requerida: vista filtrada y exportacion que incluya solo los registros filtrados.

- Regla: el estudiante se obtiene de SIGA2.
  Condicion: se inicia un tramite.
  Resultado esperado: el sistema permite buscar por autocompletar y seleccionar una estudiante activa o egresada existente en SIGA2, guardando una copia minima de los datos usados para el recibo.
  Excepciones: si SIGA2 no esta disponible, el sistema debe impedir nuevos tramites o permitir solo un modo manual aprobado posteriormente.
  Prueba requerida: integracion simulada con estudiante activa encontrada, egresada encontrada y estudiante no encontrado.

- Regla: el folio del recibo se asigna al confirmar pago.
  Condicion: el tramite pasa a pagado.
  Resultado esperado: se crea un folio unico para el recibo interno y, si aplica, folios unicos para cada recibo externo.
  Excepciones: recibos cancelados conservan su folio y no lo liberan.
  Prueba requerida: prueba de unicidad y prueba de cancelacion.

- Regla: el recibo debe incluir validacion QR.
  Condicion: se emite un recibo pagado.
  Resultado esperado: el recibo contiene un codigo QR que apunta a una pagina de validacion del portal, con token o firma que no exponga datos sensibles.
  Excepciones: si la verificacion publica no se habilita en la primera version, el sistema debe conservar el identificador necesario para generarla despues.
  Prueba requerida: recibo emitido con URL firmada o token de validacion.

- Regla: una transaccion pagada no puede editar sus conceptos.
  Condicion: la persona intenta modificar partidas despues del pago.
  Resultado esperado: el sistema bloquea la edicion y exige cancelacion si hubo error.
  Excepciones: correcciones administrativas solo mediante flujo aprobado de cancelacion y nueva transaccion.
  Prueba requerida: autorizacion y regla de estado.

- Regla: toda cancelacion requiere motivo.
  Condicion: `owner` o `finance-manager` cancela un recibo o transaccion.
  Resultado esperado: se guarda motivo, usuario, fecha y estado cancelado.
  Excepciones: `finance-assistant` no puede cancelar por defecto.
  Prueba requerida: autorizacion por rol y persistencia de auditoria.

#### Catalogo inicial de conceptos de cobro

El catalogo no tendra tipo financiero predeterminado. Cada concepto debe clasificarse como `interno` o `externo` antes de quedar activo para tramites.

| Concepto | Tipo financiero |
| --- | --- |
| Constancias de estudios de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Constancias de estudios con calificacion de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Examenes extraordinarios de regularizacion por asignatura de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Examenes profesionales de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Examen de ingreso a licenciatura de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Gestion administrativa de nuevo ingreso a educacion normal | Requiere clasificacion al cargar catalogo |
| Gestion administrativa de licenciatura en educacion normal | Requiere clasificacion al cargar catalogo |
| Expedicion de credenciales de Educacion Normal | Requiere clasificacion al cargar catalogo |
| Transferencias internas para sinodalia de Educacion Normal | Requiere clasificacion al cargar catalogo |

### 8. Autorizacion

| Accion | Owner | Admin | Rol especifico | Public | Notas |
| --- | --- | --- | --- | --- | --- |
| Ver modulo | Si | Si | `finance-manager`, `finance-assistant`, `finance-auditor` | No | Acceso solo por padron autorizado |
| Crear tramite | Si | Si | `finance-manager`, `finance-assistant` | No | Requiere estudiante valido |
| Registrar pago | Si | Si | `finance-manager`, `finance-assistant` | No | Requiere tramite pendiente |
| Editar tramite borrador | Si | Si | `finance-manager`, `finance-assistant` | No | Solo antes del pago |
| Cancelar recibo o transaccion | Si | Si | `finance-manager` | No | Requiere motivo; `finance-assistant` queda excluido |
| Exportar datos | Si | Si | `finance-manager`, `finance-auditor` | No | Debe respetar filtros y datos sensibles |
| Configurar catalogos | Si | Si | `finance-manager` | No | Cambios auditables |
| Generar reporte mensual SEQ | Si | Si | `finance-manager` | No | Solo recibos externos de transacciones pagadas no canceladas |

### 9. Integraciones

| Sistema | Tipo | Datos enviados | Datos recibidos | Autenticacion |
| --- | --- | --- | --- | --- |
| SIGA2 | Interna | Termino de busqueda, indicador para incluir egresadas, identificador de estudiante o parametros de consulta autorizados | Listado de estudiantes y egresadas con datos academicos minimos para recibo | Token interno o mecanismo aprobado para `/api/internal/finance/v1` |

### 10. APIs requeridas

| Ruta | Metodo | Tipo | Autenticacion | Proposito |
| --- | --- | --- | --- | --- |
| `/api/internal/students/search` | `GET` | Interna | Token interno hacia SIGA2 | Buscar estudiantes y egresadas desde el portal financiero mediante autocompletar |
| `/api/internal/students/{student}` | `GET` | Interna | Token interno hacia SIGA2 | Obtener datos minimos actualizados de un estudiante |
| `/receipts/{receipt}/verify` | `GET` | Firmada/Publica limitada | Firma o token de verificacion | Validar autenticidad basica de un recibo si se aprueba verificacion publica |

### 11. Pantallas

| Pantalla | Ruta sugerida | Usuarios | Acciones |
| --- | --- | --- | --- |
| Dashboard | `/dashboard` | `owner`, `admin`, roles financieros | Ver resumen diario, accesos rapidos y movimientos recientes |
| Tramites de pago | `/payments/procedures` | `finance-manager`, `finance-assistant` | Buscar, filtrar, crear tramite, continuar pendiente |
| Nuevo tramite | `/payments/procedures/create` | `finance-manager`, `finance-assistant` | Buscar estudiante, agregar conceptos, calcular total |
| Detalle de tramite | `/payments/procedures/{procedure}` | `finance-manager`, `finance-assistant`, `finance-auditor` | Revisar partidas, registrar pago, ver recibo |
| Recibos | `/receipts` | `finance-manager`, `finance-assistant`, `finance-auditor` | Buscar por folio, estudiante, fecha, tipo interno/externo y estado |
| Detalle de recibo | `/receipts/{receipt}` | `finance-manager`, `finance-assistant`, `finance-auditor` | Ver datos completos, tipo, estado, validacion, reimpresiones y cancelacion si aplica |
| Impresion de recibo | `/receipts/{receipt}/print` | `finance-manager`, `finance-assistant` | Imprimir o reimprimir recibo |
| Validacion de recibo | `/receipts/{receipt}/verify` | Publico limitado | Validar folio, estado y autenticidad mediante token o firma |
| Conceptos de cobro | `/charge-concepts` | `owner`, `admin`, `finance-manager` | Crear, editar, activar o inactivar conceptos |
| Reportes | `/reports/finance` | `owner`, `admin`, `finance-manager`, `finance-auditor` | Filtrar y exportar movimientos, separando conceptos internos y externos |
| Reporte mensual SEQ | `/reports/seq/monthly` | `owner`, `admin`, `finance-manager` | Consultar vista filtrable de conceptos externos y exportar el resultado a Excel |
| Accesos autorizados | `/authorized-accesses` | `owner`, `admin` | Administrar usuarios y roles |

### 12. Reportes, comprobantes o documentos

- Nombre: Recibo interno de pago CREN.
- Datos incluidos: folio interno, fecha y hora de emision, estudiante, matricula o identificador SIGA2, nombre, grado, grupo, carrera o programa, desglose de todos los conceptos del tramite, tipo financiero de cada concepto para control interno, importes, total, cantidad en letras, forma de pago, usuario que registra, codigo QR de validacion, datos institucionales y leyendas requeridas.
- Responsable: Oficina de Finanzas.
- Reglas de folio o identificador: folio unico interno, no reutilizable, asignado al confirmar pago; los folios cancelados no se liberan.
- Vigencia: el recibo permanece como comprobante historico; su estado puede ser emitido, reimpreso o cancelado.
- Forma de verificacion publica, si aplica: URL firmada o codigo de verificacion limitado al estado del recibo y datos no sensibles.
- Identidad visual: usar logo institucional CREN y paleta derivada del archivo `logo-cren.svg`: carbon `#363334`, azul institucional `#40b2e4`, indigo `#434596`, rojo `#ec3336`, amarillo `#f9b601` y verde `#17aa56`. El recibo debe ser sobrio, imprimible y legible en blanco y negro.

- Nombre: Recibo externo SEQ de ingresos propios.
- Fuente de formato: `/Users/willix/Downloads/RECIBO OFICIAL.docx`.
- Uso: conceptos externos que deben reportarse a Finanzas de la SEQ.
- Regla de generacion: se genera un recibo externo separado por cada concepto externo pagado, incluso cuando el estudiante haya pagado dos o mas conceptos en el mismo tramite.
- Datos incluidos: folio externo, fecha, bueno por, nombre del estudiante, grupo, cantidad en letras, concepto externo unico, importe exacto del concepto, espacios de `RECIBI PAGO` y `SELLO`, codigo QR de validacion, marca de original/copia y datos institucionales.
- Formato visual: hoja con original y copia del recibo `RECIBO INGRESOS PROPIOS`, encabezado del Centro Regional de Educacion Normal, clave `23DNL0002N`, domicilio, telefonos, QR y notas SEQ.
- Notas visibles del formato: verificacion del importe en estado de cuenta BBVA y plazo maximo de 5 dias posteriores al deposito para facturacion via `tesoreria@seq.edu.mx`, segun texto del formato oficial.
- Reglas de folio o identificador: folio unico externo, no reutilizable, asociado a la partida externa y al tramite interno que le dio origen.
- Deposito bancario asociado: cada recibo externo puede recibir un unico registro de deposito SEQ; el importe del deposito debe coincidir exactamente con el importe del recibo externo para sostener la relacion concepto-recibo-deposito exigida por auditoria.
- Vigencia: el recibo externo permanece como comprobante historico; su estado puede ser emitido, reimpreso o cancelado.

Reportes financieros:

- Nombre: Concentrado mensual para Finanzas de la SEQ.
- Datos incluidos: periodo mensual, folios externos, fechas, estudiantes, conceptos externos, importes exactos por recibo externo, datos de deposito SEQ cuando existan, totales por concepto y total mensual.
- Responsable: Oficina de Finanzas.
- Reglas de inclusion: solo recibos externos emitidos por conceptos externos en transacciones pagadas no canceladas dentro del periodo.
- Forma de consulta: vista filtrable dentro del portal antes de exportar.
- Forma de exportacion: archivo Excel XLSX con formato particular de la SEQ; la primera version implementada puede exportar una tabla `.xls` compatible con Excel que refleje exactamente los registros y totales visibles en la vista filtrada. El XLSX final se generara cuando se apruebe la plantilla definitiva.

### 13. Datos sensibles

Identificar datos personales, financieros, academicos o administrativos sensibles:

- Nombre del estudiante.
- Matricula o identificador institucional.
- Carrera, semestre, grupo o estado academico.
- Conceptos pagados e importes.
- Clasificacion interna o externa de cada concepto.
- Vinculo entre tramite interno y recibos externos generados.
- Forma de pago y referencias administrativas.
- Usuarios responsables de registro, emision o cancelacion.

Controles requeridos:

- Autenticacion institucional obligatoria.
- Autorizacion por roles y permisos en servidor.
- Trazabilidad de altas, pagos, emisiones, reimpresiones y cancelaciones.
- Exportaciones limitadas por rol.
- Respuestas de API con datos minimos necesarios.
- Proteccion de recibos publicos mediante firma o token cuando se habilite verificacion.

### 14. Criterios de aceptacion

- Una persona autorizada puede iniciar un tramite, buscar por autocompletar una estudiante activa o egresada desde SIGA2, seleccionarla, agregar uno o varios conceptos y ver el total correcto.
- Los conceptos internos marcados como cantidad variable permiten capturar cantidad; los conceptos externos SEQ siempre se cobran una sola vez por tramite.
- Una persona autorizada puede registrar el pago y emitir el recibo interno foliado, ademas de los recibos externos SEQ cuando apliquen.
- El recibo interno conserva todas las partidas de cobro aun si el catalogo cambia posteriormente.
- El recibo interno muestra nombre, grado, grupo, desglose de conceptos, total, cantidad en letras, folio y codigo QR de validacion.
- Cuando un tramite incluye conceptos externos, el sistema genera un recibo externo SEQ por cada concepto externo con el monto exacto de ese concepto.
- Cada recibo externo puede registrar un solo deposito SEQ con importe exacto, fecha, folio bancario, tipo y concepto de deposito.
- Un tramite con dos conceptos externos y uno interno genera un recibo interno con tres conceptos y dos recibos externos unitarios.
- Los conceptos internos y externos se distinguen en catalogo, partidas, recibos y reportes.
- El reporte mensual SEQ incluye solo recibos externos de transacciones pagadas no canceladas.
- El reporte mensual SEQ puede revisarse como vista filtrable antes de exportarse.
- La exportacion Excel del reporte mensual SEQ respeta los filtros aplicados en pantalla.
- No se pueden seleccionar conceptos inactivos en nuevos tramites.
- No se pueden editar partidas despues de pagar.
- Las cancelaciones requieren rol permitido y motivo.
- Los tres usuarios financieros pueden operar segun sus permisos y un usuario no autorizado no puede ingresar.
- Los movimientos pueden buscarse por fecha, estudiante, concepto, folio y estado.
- La interfaz guia de dashboard a listado, de listado a detalle y de detalle a acciones especificas sin apilar todos los controles en una sola ventana.
- Los listados y reportes conservan filtros al entrar a un detalle y volver, cuando la navegacion lo permita.

### 15. Pruebas requeridas

- Autenticacion institucional y rechazo de correos no autorizados.
- Autorizacion de `owner`, `admin`, `finance-manager`, `finance-assistant` y `finance-auditor`.
- CRUD del catalogo de conceptos para roles permitidos.
- Validacion de tramite con estudiante requerido y al menos un concepto.
- Calculo de total con uno y multiples conceptos.
- Calculo de total con cantidad variable para conceptos internos permitidos.
- Rechazo operativo de cantidad variable para conceptos externos SEQ, registrandolos siempre como una unidad.
- Persistencia del tipo interno/externo al crear conceptos y partidas.
- Reporte separado de importes internos y externos.
- Reporte mensual SEQ con vista filtrable, exclusion de conceptos internos y recibos cancelados.
- Exportacion XLSX del reporte mensual SEQ respetando filtros aplicados.
- Registro de pago y generacion de folio unico.
- Generacion de recibo interno por tramite pagado.
- Generacion de recibos externos unitarios por cada concepto externo pagado.
- Generacion de cantidad en letras para el total del recibo.
- Generacion o persistencia de URL/token para QR de validacion.
- Bloqueo de edicion despues de pago.
- Cancelacion con motivo y rechazo para roles no permitidos.
- Busqueda de estudiantes activas y egresadas mediante cliente interno de SIGA2 simulado.
- Render de recibo interno imprimible con datos esperados.
- Render de recibo externo SEQ con original y copia, concepto unico e importe exacto.
- Navegacion jerarquica de listados a detalles para tramites, recibos y reportes.

## Checklist de inicio para implementacion

- Confirmar nombre, alcance y area responsable.
- Definir roles especificos sin romper `owner`, `admin` y `public`.
- Definir entidades y estados.
- Definir matriz de autorizacion.
- Definir integraciones y APIs.
- Crear migraciones, modelos, factories y seeders necesarios.
- Crear rutas nombradas y navegacion configurada.
- Implementar pantallas Inertia React con navegacion jerarquica.
- Cubrir flujos con Pest.
- Ejecutar pruebas enfocadas.
- Ejecutar Pint si se modifico PHP.
