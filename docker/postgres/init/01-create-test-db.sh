#!/bin/bash

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_test" OWNER "${POSTGRES_USER}";
    GRANT ALL PRIVILEGES ON DATABASE "${POSTGRES_DB}_test" TO "${POSTGRES_USER}";
EOSQL

echo "[postgres-init] Base de datos de tests '${POSTGRES_DB}_test' creada."
