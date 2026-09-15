# Despliegue de piloto privado

Esta guía instala OpsEvidence en un VPS Linux con frontend compilado, Laravel sobre
nginx + PHP-FPM, PostgreSQL, Redis protegido y HTTPS automático mediante Caddy.
PostgreSQL, Redis y los servicios internos no publican puertos en el host.

Si aún no tienes dominio, utiliza el modo LAN descrito en la sección 3.1. Ese modo
sirve para probar desde la red privada, pero no debe exponerse a Internet.

## 1. Requisitos

- VPS de prueba con Linux, Docker Engine y el plugin Docker Compose.
- Un dominio o subdominio, por ejemplo `ops.example.com`, apuntando a la IP del VPS.
- Puertos TCP 80 y 443 abiertos; SSH restringido a las IP necesarias.
- Al menos 2 vCPU, 4 GB de RAM y 20 GB de disco para el piloto.

No uses todavía esta instalación como servicio público con clientes finales. Es un
piloto para recopilar evidencia real, revisar el informe y ajustar el producto.

## 2. Configurar secretos

En el servidor, copia el repositorio y crea el archivo privado de configuración:

```bash
cp .env.pilot.example .env.pilot
chmod 600 .env.pilot
```

Genera valores diferentes para PostgreSQL y Redis:

```bash
openssl rand -hex 32
openssl rand -hex 32
```

Edita `.env.pilot` y reemplaza el dominio, correo ACME, URLs y todos los valores
`REEMPLAZAR_*`. `POSTGRES_PASSWORD` y `DB_PASSWORD` deben contener el mismo valor.

Construye la imagen y genera `APP_KEY`:

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml build backend
docker compose --env-file .env.pilot -f docker-compose.pilot.yml run --rm --no-deps backend php artisan key:generate --show
```

Copia la salida completa `base64:...` a `APP_KEY`. No cambies esa clave después de
guardar credenciales cifradas sin preparar antes una rotación controlada.

Para el primer piloto deja `SEED_DEMO_DATA=false`. Los usuarios de demostración y su
contraseña conocida no deben existir en un servidor accesible desde Internet.

## 3. Levantar y crear el propietario

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml --profile https up -d --build --wait
docker compose --env-file .env.pilot -f docker-compose.pilot.yml exec backend php artisan opsevidence:create-owner
```

El segundo comando solicita de forma interactiva el nombre de la cuenta, nombre del
propietario, correo y una contraseña de al menos 12 caracteres.

### 3.1. Prueba dentro de la red sin dominio

Para tu servidor `192.168.1.13`, usa el override LAN. Expone únicamente el frontend
en `5180` y la API en `8010`; PostgreSQL, Redis y Caddy permanecen apagados o internos.

```bash
cp .env.lan.example .env.lan
chmod 600 .env.lan
openssl rand -hex 32
openssl rand -hex 32
```

Reemplaza los dos secretos y genera `APP_KEY` como en la sección anterior. Después:

```bash
docker compose --env-file .env.lan \
  -f docker-compose.pilot.yml -f docker-compose.lan.yml \
  config --quiet

docker compose --env-file .env.lan \
  -f docker-compose.pilot.yml -f docker-compose.lan.yml \
  up -d --build --wait

docker compose --env-file .env.lan \
  -f docker-compose.pilot.yml -f docker-compose.lan.yml \
  exec backend php artisan opsevidence:create-owner
```

Abre desde un equipo de la misma red [http://192.168.1.13:5180](http://192.168.1.13:5180).
La API estará en `http://192.168.1.13:8010/api`. El navegador mostrará HTTP sin
certificado: es esperado y solo es aceptable dentro de una red de confianza.

En el firewall del servidor permite TCP `5180` y `8010` únicamente desde tu red LAN.
No abras esos puertos en el router ni configures port forwarding. Cuando compres el
dominio, cambia a `.env.pilot`, elimina `docker-compose.lan.yml` y activa el perfil
HTTPS con `--profile https`.

Comprueba el estado:

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml ps
curl -fsS https://ops.example.com/api/health
```

Si Caddy no obtiene el certificado, revisa primero DNS y que los puertos 80/443 no
estén ocupados. Consulta los logs con:

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml logs --tail=100 caddy backend
```

## 4. Correo

El valor inicial `MAIL_MAILER=log` permite generar y descargar informes sin enviar
correo. Para probar el envío real, configura un SMTP en `.env.pilot`, cambia
`MAIL_MAILER=smtp` y recrea backend, worker y scheduler:

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml --profile https up -d --force-recreate backend worker scheduler
```

## 5. Backup diario

Da permiso de ejecución al script y pruébalo:

```bash
chmod 700 scripts/backup-pilot.sh
./scripts/backup-pilot.sh
```

Los archivos quedan en `backups/`, fuera de Git y con permisos privados. Copia cada
backup a otra máquina o almacenamiento cifrado; un backup en el mismo VPS no protege
ante pérdida total del servidor.

Ejemplo de cron diario a las 02:30 UTC:

```cron
30 2 * * * cd /opt/opsevidence && ./scripts/backup-pilot.sh >> /var/log/opsevidence-backup.log 2>&1
```

Para restaurar, detén backend, worker y scheduler, confirma que elegiste el archivo
correcto y carga el dump. Esta operación reemplaza el contenido actual de la base:

```bash
docker compose --env-file .env.pilot -f docker-compose.pilot.yml stop backend worker scheduler
gunzip -c backups/opsevidence-FECHA.sql.gz | docker compose --env-file .env.pilot -f docker-compose.pilot.yml exec -T postgres sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose --env-file .env.pilot -f docker-compose.pilot.yml start backend worker scheduler
```

## 6. Primera prueba de informe

1. Entra por HTTPS y crea un cliente, un ambiente y un activo.
2. Emite en **Ajustes** un token limitado a ese cliente.
3. Instala el agente siguiendo [Primer servidor real](first-real-server.md).
4. Añade checks HTTP/SSL para un dominio público que controles.
5. Deja recopilar evidencia durante varios días y revisa **Problemas**.
6. En **Informes**, genera un período que incluya esos días y descarga el PDF.

Registra durante la prueba qué métricas faltan, qué textos resultan poco claros y si
el PDF puede enviarse al cliente sin edición manual. Ese resultado alimentará el
siguiente sprint con evidencia real de uso.

## 7. Actualizar el piloto

Haz un backup antes de actualizar y conserva siempre el mismo `.env.pilot`:

```bash
git pull --ff-only
./scripts/backup-pilot.sh
docker compose --env-file .env.pilot -f docker-compose.pilot.yml --profile https up -d --build --wait
```

El servicio `init` aplica migraciones antes de iniciar la nueva aplicación. Si una
actualización falla, guarda los logs y no elimines volúmenes con `down -v`.
