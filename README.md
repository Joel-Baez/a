# Sistema de soporte con microservicios

Este repositorio contiene una implementación base del sistema de tickets solicitado, separando la lógica en dos microservicios independientes (usuarios y tickets) y un frontend en HTML/CSS/JS.

## Estructura
- `backend/users`: autenticación, gestión de usuarios y validación de tokens.
- `backend/tikets`: creación y administración de tickets y comentarios.
- `frontend`: vistas en HTML5/JS/CSS sin frameworks.
- `database/schema.sql`: script SQL para crear la base de datos relacional `soporte_tickets` con todas las tablas requeridas.

Cada microservicio tiene su propio `composer.json` y depende de Slim 4 y Eloquent; deben ejecutarse de forma independiente y sin API Gateway.

> Nota: las carpetas anteriores `microservicio-usuarios` y `microservicio-tickets` fueron eliminadas; todo el backend vive ahora en `backend/users` y `backend/tikets`.

## Configuración de backend
1. Copia el archivo `.env` en cada microservicio (opcional) o exporta las variables `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Por defecto se usa `soporte_tickets` en `127.0.0.1` con usuario `root` sin contraseña.
2. Dentro de cada microservicio ejecuta:
   ```bash
   composer install
   php -S localhost:8003 -t public   # backend/users
   php -S localhost:8002 -t public   # backend/tikets
   ```
3. Importa `database/schema.sql` en tu servidor MySQL/MariaDB para crear las tablas según el esquema solicitado (users, auth_tokens, tickets y ticket_actividad) con datos de prueba y contraseñas hash.

## Endpoints principales
El esquema usa las tablas y campos solicitados: `users` (roles `gestor`/`admin` con columna `is_active` para desactivar cuentas), `auth_tokens`, `tickets` (campos `titulo`, `descripcion`, `estado` con valores `abierto|en_progreso|resuelto|cerrado`, `gestor_id`, `admin_id`) y `ticket_actividad` para el historial de mensajes por ticket.
### Microservicio de usuarios (`/backend/users`)
- `POST /register`: registro de usuarios (rol por defecto `gestor`).
- `POST /login`: inicia sesión y genera token persistido en `auth_tokens`.
- `POST /logout`: cierra sesión y elimina el token.
- `GET /validate`: valida el token y devuelve el usuario.
- `GET /users`: listado solo para administradores.
- `PUT /users/{id}`: actualización de datos, rol y estado `is_active` (solo admin).
- `DELETE /users/{id}`: eliminación de usuarios (solo admin).

### Microservicio de tickets (`/backend/tikets`)
Todas las rutas requieren token válido:
- `POST /tickets`: crea un ticket (gestor o admin).
- `GET /tickets`: lista tickets; los gestores ven solo los propios, los administradores pueden filtrar por estado, creador o asignación.
- `GET /tickets/{id}`: detalle con comentarios e historial (restricción por rol/propiedad).
- `PUT /tickets/{id}/status`: actualiza estado (`abierto`, `en_progreso`, `resuelto`, `cerrado`) solo admin.
- `PUT /tickets/{id}/assign`: asigna un ticket a un administrador.
- `POST /tickets/{id}/comments`: agrega comentario; gestores solo en sus tickets, administradores en todos.

## Frontend
- Punto de entrada: `http://127.0.0.1/a/frontend/index.html` (redirige si ya hay sesión activa).
- El frontend consume directamente los microservicios usando `fetch`, maneja tokens en `localStorage/sessionStorage` y actualiza el DOM para los paneles de gestor y administrador.

## Seguridad y roles
- Autenticación por token almacenado en `auth_tokens`.
- Validación centralizada en `AuthMiddleware` en cada microservicio.
- Roles `gestor` y `admin` con restricciones en controladores.
- Contraseñas almacenadas con `password_hash`.

## Notas
- Si tu entorno no permite descargar dependencias, conserva los `composer.json` y ejecuta `composer install` cuando tengas acceso a internet.
- Cada microservicio puede desplegarse y escalarse de forma independiente.
