# OpsEvidence — Seguridad

> Estado: Fase 0. Versión 1.0 — 2026-09-10. Este documento es el threat model inicial y el catálogo
> de controles. Se revisa en cada fase (Fase 15 = hardening formal).

## 1. Principios

1. **El agente es read-only.** Nunca ejecuta nada que venga del servidor. No hay canal de comandos.
2. **Nunca confiar en el cliente del agente.** El token identifica y acota; no autoriza nada más.
3. **Los secretos no existen en claro**: ni en BD, ni en logs, ni en respuestas de API.
4. **El aislamiento entre tenants es una propiedad testeada**, no una convención.
5. **Falla cerrado.** Ante duda de autorización, se deniega.

## 2. Activos a proteger

| Activo | Impacto si se compromete |
|---|---|
| Credenciales de integraciones (tokens de GitHub, API keys) | Acceso del atacante a los sistemas del cliente |
| Tokens de agente | Inyección de evidencia falsa; reconocimiento de la infraestructura |
| Datos de infraestructura (IPs, hostnames, versiones, puertos) | Mapa de ataque de los clientes del MSP |
| Evidencia e informes | Fuga de información comercial entre tenants |
| Cuenta de usuario (sesión) | Acceso completo al tenant |
| Disponibilidad del servicio | Los informes no se entregan; se pierde la confianza |

## 3. Adversarios

| Adversario | Capacidad | Objetivo |
|---|---|---|
| Tenant malicioso o curioso | Cuenta válida en su propio account | Leer datos de otro account |
| Atacante externo | Internet | Enumerar tokens, romper auth, DoS |
| Servidor comprometido del cliente | Controla el host donde corre el agente | Escalar hacia OpsEvidence o hacia otros clientes |
| Insider negligente (técnico del MSP) | Credenciales legítimas | Borrar evidencia, ocultar fallos |
| Suplantador de webhook | Conoce o adivina una URL | Inyectar evidencia falsa |

## 4. Threat model (STRIDE aplicado)

| # | Amenaza | Vector | Mitigación | Verificación |
|---|---|---|---|---|
| T1 | **Spoofing** — suplantar a otro tenant | Manipular `client_id` en la URL del request | `account_id` derivado **siempre** del usuario autenticado, nunca del request; FK validadas dentro del alcance; policies por recurso | Tests de aislamiento (§7) |
| T2 | **Spoofing** — webhook falso | POST a `/api/webhooks/backup/{token}` con token adivinado | Token opaco de 32+ bytes aleatorios; comparación en tiempo constante; rate limit; registro de IP | Tests de webhook con token inválido |
| T3 | **Tampering** — modificar evidencia histórica | API o SQL | `evidence` append-only: no existe endpoint de update/delete; sin `updated_at` | Test: no hay ruta que muta evidencia |
| T4 | **Tampering** — inyección SQL | Filtros, ordenamiento, búsqueda | Eloquent y query builder con bindings; **nunca** concatenar `order`/`sort` del request: se valida contra una lista blanca | Tests de ordenamiento con payload malicioso |
| T5 | **Repudiation** — negar una acción | Borrado de cliente, revocación de token | `audit_logs` con actor, IP, timestamp y cambios | Test de auditoría en acciones sensibles |
| T6 | **Information disclosure** — fuga entre tenants | Consultas sin filtro de account | Global scope `BelongsToAccount` + policies + `account_id` desnormalizado | Suite dedicada de aislamiento |
| T7 | **Information disclosure** — secretos en logs | Volcado de `configuration`/`credentials` | Casts `encrypted`; `$hidden` en modelos; redacción explícita en el logger; nunca `Log::info($config)` | Test: los logs no contienen el string del secreto |
| T8 | **Information disclosure** — enumeración de recursos | IDs secuenciales | IDs numéricos aceptados por simplicidad, pero **toda** lectura pasa por policy y responde `404` (no `403`) cuando el recurso es de otro tenant, para no filtrar existencia | Tests de aislamiento |
| T9 | **DoS** — saturación por agente | Agente en bucle enviando evidencia | Rate limit por token; límite de tamaño de payload; límite de evidencias por request; cuarentena del token ante abuso | Tests de rate limiting |
| T10 | **DoS** — coste de recolección | Miles de checks mal configurados | Intervalo mínimo forzado (60 s); límite de checks por cuenta; `consecutive_failures` con backoff | Test de validación de intervalo |
| T11 | **Elevation of privilege** — cambio de rol | Modificar el propio rol | El rol no es asignable por el propio usuario; solo `owner`/`admin` gestionan usuarios; no se puede degradar al último owner | Tests de RBAC |
| T12 | **Elevation of privilege** — ejecución remota vía agente | El servidor envía comandos al agente | **El agente no implementa ninguna recepción de comandos.** Solo tiene un sentido: él → servidor | Revisión de código + test que verifica que el agente no abre puertos |
| T13 | **Supply chain** — dependencia maliciosa | Composer/npm/PyPI | `composer.lock` y `package-lock.json` versionados; Dependabot; Gitleaks; Trivy en CI | Pipeline de CI |
| T14 | **CSRF** | Navegador con sesión activa | API usa tokens Bearer (no cookies) para la SPA; si se usa modo cookie de Sanctum, `VerifyCsrfToken` obligatorio y CORS restringido | Test de CORS y de origen |
| T15 | **XSS** | Contenido de evidencia renderizado | Vue escapa por defecto; **prohibido `v-html`** salvo en el informe, donde el HTML se genera en el servidor a partir de datos validados y escapados | Revisión + test de escape en el informe |
| T16 | **Compromiso del host del cliente** | El atacante controla el servidor con el agente | Token de alcance mínimo (`evidence:write`, un cliente/asset); revocable en un clic; sin lectura de otros datos; el token **no permite leer nada** | Tests de scope del token |

## 5. Controles por capa

### 5.1 Autenticación

- Laravel Sanctum. La SPA autentica y recibe un token Bearer.
- Contraseñas con bcrypt (coste 12).
- Rate limiting agresivo en login (5 intentos/min por IP+email) con bloqueo progresivo.
- Sin registro público en el MVP: las cuentas se crean por invitación o por el `owner`.
- Comparación de tokens con `hash_equals` (tiempo constante).

### 5.2 Autorización y RBAC

Cuatro roles, sin jerarquías dinámicas:

| Rol | Alcance |
|---|---|
| `owner` | Todo, incluida la gestión de usuarios y la eliminación de la cuenta |
| `admin` | Gestión completa de clientes, activos, checks, integraciones e informes |
| `technician` | Crear/editar clientes y activos, gestionar incidentes, registrar actividades |
| `viewer` | Solo lectura (preparado para el portal del cliente final, post-MVP) |

Reglas duras:
- Toda consulta pasa por una **policy** que verifica pertenencia al `account`.
- El rol no puede auto-modificarse.
- El último `owner` de una cuenta no puede ser degradado ni eliminado.
- Los tokens de agente **solo** pueden escribir evidencia; no pueden leer ningún recurso.

### 5.3 Aislamiento de tenants

Defensa en tres capas:

1. **Global scope** en `BelongsToAccount`: añade `where account_id = ?` a toda consulta Eloquent.
2. **Policies** explícitas que verifican la pertenencia del recurso concreto.
3. **Tests dedicados** (`TenantIsolationTest`): para cada recurso, un usuario del account A intenta
   leer/modificar/borrar un recurso del account B y debe recibir `404`.

La capa 3 es la que garantiza que las capas 1 y 2 sigan funcionando cuando alguien añada una
consulta nueva con `withoutGlobalScopes()`.

### 5.4 Secretos y credenciales

- Casts `encrypted:array` (AES-256-GCM con `APP_KEY`) para `integrations.credentials`.
- `credentials` en `$hidden` de los modelos: nunca se serializa a la API.
- La API expone `has_credentials: true/false`, jamás el valor.
- `APP_KEY` fuera del repositorio; `.env` en `.gitignore`; `.env.example` sin valores reales.
- Gitleaks en CI y en el hook `pre-commit`.
- Los logs redactan cualquier clave que contenga `token`, `secret`, `password`, `key`, `credential`.

### 5.5 Tokens de agente

Ciclo de vida completo:

```
generar (32 bytes aleatorios, base64url)
   │  ── devuelto UNA vez al usuario
   ▼
almacenar SHA-256 (nunca el valor original)
   │
   ▼
usar: Authorization: Bearer <token>  →  hash  →  buscar  →  verificar revocación/expiración
   │
   ▼
rotar: emitir nuevo + revocar el anterior
   │
   ▼
revocar: revoked_at = now()  (efecto inmediato, la fila se conserva para auditoría)
```

Propiedades: scoped por `client_id`/`asset_id`, con `abilities`, expirables, revocables,
rotables, con `last_used_at` visible en la UI. **Nunca se registran en logs.**

### 5.6 Validación de entrada

- Form Requests en todos los endpoints de escritura.
- Listas blancas para `sort`/`order`/`filter` (nunca interpolación directa).
- Límites de tamaño de payload en el endpoint de agente/webhooks.
- Validación de tipos de asset y check contra constantes de dominio.
- URLs de checks HTTP: se rechazan esquemas distintos de `http`/`https` y direcciones que apunten a
  loopback, redes privadas o rangos reservados. Cada destino de una redirección se vuelve a resolver
  y validar antes de realizar la siguiente petición.

### 5.7 Cabeceras y transporte

- HTTPS obligatorio en producción; redirección desde HTTP; HSTS.
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Content-Security-Policy` razonable (ajustada a Vuetify), `Permissions-Policy`.
- CORS restringido al origen del frontend, no `*`.
- Cookies (si se usan) con `Secure`, `HttpOnly`, `SameSite=Lax`.

### 5.8 Rate limiting

| Endpoint | Límite |
|---|---|
| `POST /api/auth/login` | 5/min por IP+email |
| `POST /api/agent/evidence` | 60/min por token |
| `POST /api/webhooks/backup/{token}` | 30/min por token |
| API autenticada general | 120/min por usuario |
| Lecturas pesadas (informe, evidencia) | 30/min por usuario |

### 5.9 Auditoría y logging

- Logging estructurado en JSON con `correlation_id` propagado desde el request hasta el job.
- Eventos auditados: login, creación/borrado de clientes, cambios de rol, emisión y revocación de
  tokens, generación y envío de informes, cambios de umbrales de reglas.
- **Nunca** se registran credenciales, tokens ni cuerpos completos de webhooks con secretos.

### 5.10 Contenedores

- Imágenes base fijadas por versión; usuario no root donde sea posible.
- Sin secretos en la imagen ni en `docker-compose.yml` (siempre vía `.env`).
- Escaneo con Trivy en CI.
- Postgres y Redis **no** publican puertos al host en producción (`expose` interno, no `ports`).
- Volúmenes con permisos mínimos.

## 6. Modelo de confianza del agente (detalle)

El agente es la pieza que más exposición introduce, porque vive **dentro** de la infraestructura del
cliente. Su contrato de seguridad es deliberadamente restrictivo:

### Lo que el agente HACE
- Lee información del sistema operativo: hostname, kernel, uptime, CPU, memoria, discos, carga.
- Lee la API local de Docker (socket Unix) si está disponible y autorizado.
- Envía un POST HTTPS al servidor con la evidencia recolectada.
- Guarda localmente su configuración y su token (permisos `0600`).

### Lo que el agente NO HACE (nunca, por diseño)
- **No** abre puertos de escucha.
- **No** acepta comandos del servidor.
- **No** ejecuta scripts descargados.
- **No** ofrece shell ni ejecución remota.
- **No** lee archivos arbitrarios: solo las rutas conocidas y no sensibles que necesita.
- **No** envía el contenido de archivos de configuración ni variables de entorno.
- **No** escribe en el sistema salvo su propio archivo de configuración.

### Superficie de ataque resultante

| Vector | Estado |
|---|---|
| Servidor comprometido → comando al agente | **Imposible**: no existe el canal |
| Token robado del servidor del cliente | Acotado a escribir evidencia de un asset; revocable |
| Hombre en el medio | TLS con verificación de certificado obligatoria |
| Escalada de privilegios local | El agente se ejecuta sin privilegios (salvo lectura del socket de Docker) |

## 7. Tests de seguridad obligatorios

| Test | Verifica |
|---|---|
| `TenantIsolationTest` | Account A no puede ver ni tocar nada de Account B (404, no 403) |
| `AuthenticationTest` | Login, logout, token inválido/expirado, rate limit |
| `RoleAuthorizationTest` | Cada rol solo puede lo que le corresponde; el último owner está protegido |
| `AgentTokenTest` | Token hasheado, revocado, expirado, scope limitado, no puede leer |
| `WebhookSecurityTest` | Token inválido → 401; rate limit; payload sobredimensionado rechazado |
| `SecretLeakageTest` | Las credenciales no aparecen en respuestas de API ni en logs |
| `SqlInjectionTest` | `sort`/`order`/`filtros` maliciosos no rompen ni filtran |
| `RateLimitTest` | Los límites se aplican |
| `EvidenceImmutabilityTest` | No existe forma de modificar o borrar evidencia por API |
| `ReportEscapingTest` | Contenido malicioso en la evidencia se escapa en el informe HTML |

## 8. Divulgación de vulnerabilidades

Ver `SECURITY.md` en la raíz del repositorio para el canal de reporte y los plazos de respuesta.

## 9. Riesgos aceptados conscientemente en el MVP

| Riesgo | Aceptado porque | Mitigación futura |
|---|---|---|
| Sin 2FA | Añade fricción al onboarding de un producto sin usuarios | POST-MVP, obligatorio para `owner` |
| Sin rotación automática de tokens | La rotación manual está soportada y es suficiente en la escala del MVP | POST-MVP |
| Sin WAF ni protección DDoS dedicada | El proveedor de hosting cubre lo básico | POST-MVP |
| Sin cifrado en reposo a nivel de BD | Depende del proveedor; los campos sensibles sí van cifrados | POST-MVP |
| IDs secuenciales | Mitigado con policies y `404`; el coste de UUIDs no se justifica | Revisable |
| Sin pentest externo | Fuera del presupuesto del MVP | Antes de vender a un cliente grande |
