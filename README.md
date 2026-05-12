# Inventario Taller

Aplicación web para gestión de inventario de taller. Stack: PHP 8.2 + Apache + MySQL 8.0.  
Dockerizada y compatible con **CasaOS Custom Install**.

## Requisitos

- Docker + Docker Compose (v2)
- Puerto 8085 disponible (configurable)

## Instalación rápida

```bash
git clone https://github.com/zpma82/inventario-taller.git
cd inventario-taller
cp .env.example .env
# Edita .env con tus contraseñas
docker compose up -d
```

Accede en: `http://<IP>:8085`

## Usuarios por defecto (contraseña: `1234`)

| Usuario  | Rol       |
|----------|-----------|
| admin    | Administrador |
| carlos   | Técnico   |
| laura    | Técnico   |
| pedro    | Usuario   |
| ana      | Usuario   |
| invitado | Solo lectura |

> ⚠️ Cambia las contraseñas antes de usar en producción.

## CasaOS — Custom Install

1. En CasaOS → **App Store → Custom Install**
2. Pega el contenido de `docker-compose.yml`
3. Ajusta el puerto si 8085 ya está ocupado

## Estructura

```
inventario-taller/
├── app/
│   ├── index.html          # Frontend SPA
│   ├── api/                # Backend PHP
│   │   ├── config.php
│   │   ├── auth.php
│   │   ├── equipos.php
│   │   ├── movimientos.php
│   │   ├── catalogos.php
│   │   └── ubicaciones.php
│   └── sql/                # Scripts SQL (init automático)
│       ├── 01_schema.sql
│       ├── 02_seed.sql
│       └── ...
├── docker/
│   ├── Dockerfile
│   └── apache.conf
├── docker-compose.yml
└── .env.example
```

## Variables de entorno

| Variable             | Valor por defecto              |
|----------------------|-------------------------------|
| APP_PORT             | 8085                          |
| MYSQL_ROOT_PASSWORD  | RootPass_Cambia1!             |
| MYSQL_APP_PASSWORD   | CambiaEstaPassword_Local1!    |

## Comandos útiles

```bash
# Ver logs
docker compose logs -f

# Parar
docker compose down

# Borrar también la BD (⚠️ destruye datos)
docker compose down -v
```
