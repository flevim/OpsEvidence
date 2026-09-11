# Política de seguridad

## Reportar una vulnerabilidad

Si encuentras una vulnerabilidad, **no la abras como issue público**. Envía los
detalles a `security@opsevidence.app` con:

- Versión afectada.
- Pasos para reproducir.
- Impacto potencial.

Responderemos en un plazo razonable y coordinaremos la publicación del fix contigo.

## Versiones soportadas

Al tratarse de un MVP en desarrollo activo, solo se da soporte de seguridad a la
rama `main`/`master` más reciente.

## Modelo de seguridad

Los principios y el threat model completo están en [docs/security.md](docs/security.md).
Resumen de las garantías que consideramos **no negociables**:

1. **El agente es read-only.** No abre sockets de escucha, no acepta comandos del
   servidor y no ejecuta scripts descargados.
2. **Los secretos no existen en claro.** Las credenciales se cifran con `APP_KEY`;
   los tokens de agente se guardan solo como hash SHA-256.
3. **El aislamiento entre tenants es una propiedad testeada**, no una convención.
4. **Falla cerrado.** Ante duda de autorización, se deniega y se devuelve 404 (no
   403) para no revelar la existencia de recursos de otro tenant.
5. **Nunca mostrar 0 donde no hay dato.** La frescura (`fresh`/`stale`/
   `never_collected`) es explícita en toda la superficie.

## Qué verificar en cada release

- `vendor/bin/phpstan analyse` sin errores nuevos.
- `php artisan test` (incluye la suite de aislamiento de tenants).
- Gitleaks en CI sin fugas.
- Trivy config scan en CI sin hallazgos HIGH/CRITICAL.
