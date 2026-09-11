# Agente Linux

El agente es un ejecutable **read-only** que recolecta información de un servidor
Linux y la envía por HTTPS. Ver `agent/README.md` para la instalación paso a paso.

## Modelo de seguridad

| El agente SÍ | El agente NO |
|---|---|
| Lee `/proc`, `/etc/os-release` y el socket/CLI de Docker | Abre puertos de escucha |
| Envía un POST saliente con TLS verificado | Acepta comandos del servidor |
| Guarda su token en un archivo `0600` | Ejecuta scripts descargados |
| | Ofrece shell remoto |
| | Lee archivos arbitrarios o envía variables de entorno |

El token es **hash en el servidor**, revocable, con alcance por cliente/activo y
**nunca aparece en logs** (redacción en la propia capa de logging).

## Configuración

Precedencia: CLI → variables de entorno → archivo → valores por defecto.

```bash
opsevidence-agent enroll \
  --endpoint https://ops.example.com \
  --token ops_xxxxxxxx \
  --asset web-server-01

opsevidence-agent collect --dry-run   # ver payload sin enviar
opsevidence-agent collect             # enviar
opsevidence-agent check-config        # validar (token redactado)
```

## Payload

El agente envía **datos crudos**; el estado lo decide el servidor con los umbrales
del check. Un token comprometido no puede inyectar un "todo está bien" falso.

## Rotación de token

1. En OpsEvidence: revoca el token actual y emite uno nuevo.
2. En el servidor: `opsevidence-agent enroll` de nuevo (o edita el archivo `0600`).

## Reintentos

Solo ante errores de red y 5xx, con backoff exponencial (máx. 3). Un `401` nunca se
reintenta: indica token inválido o revocado.
