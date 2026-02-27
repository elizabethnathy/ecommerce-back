# E-Commerce API

API REST para un sistema de e-commerce construida con **Laravel 12** + **Laravel Sanctum**. Consume productos desde [dummyjson.com](https://dummyjson.com/products), los persiste localmente para manejar stock real, e implementa un carrito de compras persistente con checkout simulado.

---

## Stack

| Tecnología | Versión |
|---|---|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Laravel Sanctum | ^4.3 |
| MySQL | 8.x |
| Guzzle HTTP | ^7.10 |

> Los tests corren sobre **SQLite en memoria** (no necesitan MySQL).

---

## Requisitos previos

- PHP 8.2+
- Composer
- MySQL 8.x
- Extensiones PHP: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`

---

## Instalación

```bash
# 1. Instalar dependencias
composer install

# 2. Copiar el archivo de entorno
cp .env.example .env

# 3. Generar la clave de la aplicación
php artisan key:generate
```

---

## Configuración de base de datos

Editar `.env` con los datos de tu base de datos MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=tu_password
```

---

## Migraciones y seeders

```bash
# Crear las tablas
php artisan migrate

# Usuarios de prueba (alice@example.com, bob@example.com, carlos@example.com)
# Contraseña de todos: password123
php artisan db:seed

# Sincronizar todos los productos desde dummyjson a la tabla local
# (necesario para que el stock se descuente correctamente)
php artisan db:seed --class=ProductSeeder
```

> **Importante:** El `ProductSeeder` debe ejecutarse para que el sistema de stock funcione correctamente. Sin él, el stock se lee desde dummyjson (solo lectura) y no se descuenta.

---

## Levantar el servidor

```bash
php artisan serve
# Disponible en http://localhost:8000
```

---

## Ejecutar tests

Los tests usan SQLite en memoria y mockean `DummyJsonService`, por lo que no requieren MySQL ni conexión a internet.

```bash
# Todos los tests
php artisan test

# Solo unitarios
php artisan test --testsuite=Unit

# Solo de integración
php artisan test --testsuite=Feature

# Con detalle de cada test
php artisan test --verbose
```

### Cobertura de tests

**Unitarios** (`tests/Unit/`)

| Archivo | Qué prueba |
|---|---|
| `CartServiceTest.php` | Lógica de negocio del carrito: stock insuficiente, carrito cerrado, incremento de cantidad, eliminación con recálculo de total |
| `ProductDTOTest.php` | Cálculo correcto de `original_price`, manejo de descuento 0%, descuento 100% sin división por cero, redondeo a 2 decimales, campos por defecto |

**Integración** (`tests/Feature/`)

| Archivo | Qué prueba |
|---|---|
| `CartFlowTest.php` | Flujo completo del carrito: crear, agregar, incrementar, eliminar, checkout, carrito nuevo tras checkout, autenticación requerida, validación de body |
| `AuthTest.php` | Registro, login, logout y protección de rutas |

---

## Endpoints

Base URL: `http://localhost:8000/api`

### Autenticación

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| POST | `/auth/register` | Registrar usuario | No |
| POST | `/auth/login` | Iniciar sesión | No |
| POST | `/auth/logout` | Cerrar sesión | Sí |

**Body register/login:**
```json
{
  "nombre": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "user": { "id": 1, "nombre": "Juan Pérez", "email": "juan@example.com" },
    "token": "1|abc123..."
  }
}
```

---

### Productos (públicos)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/products` | Listar productos con paginación, ordenamiento y búsqueda |
| GET | `/products/{id}` | Detalle completo de un producto |

**Query params para `/products`:**

| Parámetro | Tipo | Default | Descripción |
|---|---|---|---|
| `page` | integer | 1 | Número de página |
| `per_page` | integer | 12 | Productos por página (máx. 100) |
| `sort_price` | string | `asc` | Ordenamiento: `asc` o `desc` |
| `search` | string | — | Buscar por nombre o marca |

**Respuesta `/products`:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 1,
      "sku": "BEA-ESS-ESS-001",
      "title": "Essence Mascara Lash Princess",
      "brand": "Essence",
      "thumbnail": "https://cdn.dummyjson.com/...",
      "price": 9.99,
      "discount_percentage": 10.48,
      "original_price": 11.16,
      "stock": 99,
      "category": "beauty",
      "rating": 2.56,
      "minimum_order_quantity": 48
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 12,
    "total": 194,
    "last_page": 17
  }
}
```

**Respuesta `/products/{id}` (incluye campos adicionales):**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1,
    "title": "Essence Mascara Lash Princess",
    "description": "...",
    "images": ["https://..."],
    "warranty_information": "1 week warranty",
    "shipping_information": "Ships in 3-5 business days",
    "return_policy": "No return policy",
    "availability_status": "In Stock",
    "barcode": "5784719087687",
    "reviews": [
      {
        "rating": 3,
        "comment": "Would not recommend!",
        "date": "2025-04-30T09:41:02.053Z",
        "reviewer_name": "Eleanor Collins"
      }
    ]
  }
}
```

---

### Carrito (requiere token Bearer)

Incluir en todas las requests:
```
Authorization: Bearer {token}
```

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/cart` | Obtener carrito activo (lo crea si no existe) |
| POST | `/cart/items` | Agregar producto al carrito |
| PUT | `/cart/items/{productId}` | Actualizar cantidad de un producto |
| DELETE | `/cart/items/{productId}` | Eliminar producto del carrito |
| POST | `/cart/checkout` | Confirmar compra y cerrar carrito |

**Body POST `/cart/items`:**
```json
{
  "product_id": 1,
  "quantity": 2
}
```

**Body PUT `/cart/items/{productId}`:**
```json
{
  "quantity": 5
}
```

**Respuesta del carrito:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1,
    "user_id": 1,
    "estado": "activo",
    "total_compra": 199.98,
    "fecha_creacion": "2025-01-01T00:00:00.000000Z",
    "fecha_actualizacion": "2025-01-01T00:00:00.000000Z",
    "items": [
      {
        "id": 1,
        "external_product_id": 1,
        "sku": "BEA-ESS-ESS-001",
        "product_title": "Essence Mascara Lash Princess",
        "product_thumbnail": "https://...",
        "precio_unitario": 9.99,
        "cantidad": 2,
        "minimum_order_quantity": 48,
        "subtotal": 19.98
      }
    ]
  }
}
```

---

### Perfil de usuario (requiere token Bearer)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/profile` | Obtener dirección y tarjeta guardada |
| PUT | `/profile` | Guardar o actualizar dirección y tarjeta |

**Body PUT `/profile`:**
```json
{
  "direccion": "Av. Ejemplo 123",
  "ciudad": "Lima",
  "pais": "PE",
  "codigo_postal": "15001",
  "card_holder": "JUAN PEREZ",
  "card_last4": "4242",
  "card_brand": "visa",
  "card_expiry": "12/2027"
}
```

> Solo se almacenan los últimos 4 dígitos de la tarjeta. Nunca el número completo.

---

## Estructura de error estándar

Todos los errores siguen el mismo formato:

```json
{
  "success": false,
  "error": {
    "error": "INSUFFICIENT_STOCK",
    "message": "Stock insuficiente. Solicitado: 5, disponible: 2."
  }
}
```

| Código de error | HTTP | Situación |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Datos de entrada inválidos (incluye `details` con campos) |
| `UNAUTHENTICATED` | 401 | Token ausente, inválido o vencido |
| `PRODUCT_NOT_FOUND` | 404 | Producto no existe en la API externa |
| `CART_NOT_FOUND` | 404 | El usuario no tiene carrito activo |
| `USER_NOT_FOUND` | 404 | El usuario no existe |
| `CART_CLOSED` | 409 | El carrito ya fue confirmado |
| `INSUFFICIENT_STOCK` | 409 | No hay suficiente stock disponible |
| `EXTERNAL_API` | 502 | dummyjson.com no respondió correctamente |
| `INTERNAL_SERVER_ERROR` | 500 | Error interno (oculta detalles en producción) |

---

## Arquitectura

```
app/
├── DTOs/
│   └── ProductDTO.php          # Transforma raw de dummyjson; calcula originalPrice una sola vez
├── Exceptions/
│   ├── Handler.php             # Manejo centralizado → estructura estándar de error
│   └── Domain/                 # Excepciones tipadas por caso de negocio
├── Http/
│   ├── Controllers/            # Solo orquestan: delegan a Services, devuelven JSON
│   ├── Requests/               # Validación con FormRequest (rules + messages)
│   └── Resources/              # Transforman Eloquent models a JSON
├── Models/
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Product.php             # Tabla local para stock real
│   ├── User.php
│   └── UserProfile.php
├── Repositories/
│   ├── CartRepository.php      # Acceso a datos del carrito
│   └── Contracts/              # Interface para inyección de dependencias
└── Services/
    ├── AuthService.php
    ├── CartService.php         # Lógica de negocio del carrito
    ├── DummyJsonService.php    # Consumo de API externa con caché y manejo de errores
    └── ProductService.php      # Orquesta dummyjson + stock local
```

---

## Decisiones técnicas

### Stock local vs API externa

dummyjson.com es una API de solo lectura; no expone endpoints para actualizar stock. Por eso los productos se persisten en la tabla `products` local mediante `ProductSeeder`. El stock local es la fuente de verdad para todas las operaciones de carrito y checkout.

El listado de productos combina los datos de dummyjson (nombre, precio, imágenes, etc.) con el stock local en una sola query `whereIn`, evitando el problema N+1.

### Ordenamiento global

dummyjson no soporta `sortBy` en `/products` (solo en `/products/search`). Si se ordenara página a página, el resultado sería incorrecto porque cada página tiene su propio orden local. La solución fue traer **todos los productos** con `limit=0`, cachearlos 5 minutos con `Cache::remember()`, y aplicar ordenamiento y paginación en PHP. Así el orden `asc/desc` es consistente en todas las páginas.

### Caché de productos

El listado completo de dummyjson se cachea 5 minutos. Esto reduce las llamadas a la API externa y mejora el tiempo de respuesta. El caché se invalida automáticamente al expirar o manualmente con `php artisan cache:clear`.

### Concurrencia en checkout

El checkout ejecuta todo dentro de una transacción MySQL (`DB::transaction`). Se aplica `SELECT ... FOR UPDATE` (`lockForUpdate`) tanto en el carrito como en cada producto local antes de validar y descontar stock. Esto garantiza que si dos usuarios intentan comprar el mismo producto simultáneamente, el segundo request espera a que el primero termine y luego lee el stock real ya descontado. Si no hay stock suficiente, la transacción hace rollback completo y el carrito queda activo.

### Precio original

Se calcula una sola vez al construir el `ProductDTO`:
```
originalPrice = price / (1 - discountPercentage / 100)
```
No se recalcula en cada request.

### Cantidad mínima de orden

Si un producto tiene `minimumOrderQuantity > 1`, el sistema auto-ajusta la cantidad al mínimo al agregar el primer item. Al intentar reducir por debajo del mínimo, el item se elimina directamente del carrito.

---

## Variables de entorno relevantes

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

# Caché (file por defecto, puede cambiarse a redis)
CACHE_DRIVER=file
```

---

## Colección Postman

El archivo `postman_collection.json` en la raíz del proyecto contiene todos los endpoints listos para importar.

**Variable de entorno Postman:**
```
base_url = http://localhost:8000/api
```

Tras hacer login, copiar el token en la variable `token` de la colección.

---

## Usuarios de prueba (seed)

| Nombre | Email | Contraseña |
|---|---|---|
| Alice Garcia | alice@example.com | password123 |
| Bob Martinez | bob@example.com | password123 |
| Carlos Lopez | carlos@example.com | password123 |
