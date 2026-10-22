# Flameware

Framework PHP y generador de proyectos de [Flameware Technologies](https://flamewarecr.com).

Nació como una herramienta interna: lo construí para los proyectos de mi negocio
de desarrollo de software, con las convenciones y decisiones que a nosotros nos
funcionan — separación estricta entre el objeto de dominio y el modelo del ORM,
inyección de dependencias en todas las capas, y una nomenclatura de base de
datos concreta. No pretende ser un framework de propósito general ni competir
con Laravel o Symfony: resuelve *nuestra* forma de trabajar.

Aun así decidí abrirlo al público. Si te sirve, tómalo: **clónalo, hazle fork,
adáptalo a tus convenciones o quédate solo con las ideas que te parezcan
útiles.** No hace falta pedir permiso ni avisar.

Las sugerencias de mejora son bienvenidas por issues o pull requests. Ten en
cuenta que el rumbo del proyecto lo marcan las necesidades de los proyectos
donde lo usamos, así que puede que una propuesta no encaje aunque sea buena —
en ese caso, un fork es el camino natural y no me molesta en absoluto.

---

Un comando crea un API PHP lista para trabajar: estructura de directorios,
dependencias instaladas, routing por atributos, Eloquent configurado y el caché
de rutas construido. Con `--web`, el mismo proyecto sirve además páginas HTML
renderizadas en el servidor, con su SEO declarado desde el controlador.

## Instalación

```bash
git clone https://github.com/AdamAG124/flameware-framework.git
cd flameware-framework
./install.sh
```

El instalador comprueba los requisitos y enlaza `bin/flameware` en
`~/.local/bin`. Si esa ruta no está en el `PATH`, te dice exactamente qué línea
agregar según tu shell.

Como es un symlink al repositorio, **actualizar es solo `git pull`**: no hay que
reinstalar, y el skeleton y los generadores quedan al día para todos los
proyectos que se creen después.

### Requisitos

| | |
|---|---|
| PHP | **8.5 o superior** — obligatorio |
| Composer | Necesario salvo que uses `--no-install` |
| git | Necesario salvo que uses `--no-git` |
| Driver PDO | Solo para `make:resource --table` |

La versión de PHP no es negociable: el framework usa `new Foo()->bar()` y otras
construcciones recientes. El comando aborta con un mensaje claro si detecta una
versión anterior, y el instalador lo comprueba antes de enlazar nada.

Si no hay drivers PDO activos, todo funciona salvo `make:resource --table`, que
necesita leer el esquema. En Arch se habilita descomentando
`extension=pdo_mysql` en `/etc/php/php.ini`. El modo `--fields` no lo requiere.

## Uso

```bash
flameware new mi-api
```

| Opción | Descripción | Por defecto |
|---|---|---|
| `--path=<dir>` | Directorio padre donde crear el proyecto | el actual |
| `--base-path=<ruta>` | Prefijo de las rutas del API | `/<nombre>/api/` |
| `--rewrite-base=<ruta>` | `RewriteBase` del `.htaccess` | derivado de `--base-path` |
| `--db=<nombre>` | Base de datos de Eloquent | vacío (sin base de datos) |
| `--vendor=<nombre>` | Vendor del `composer.json` | `app` |
| `--web` | Agrega la capa de vistas: `View`, `Seo`, `Views/` y `Public/` | |
| `--app-name=<nombre>` | Nombre del sitio para el `<title>` (con `--web`) | derivado del nombre |
| `--no-install` | No ejecutar `composer install` | |
| `--no-git` | No inicializar el repositorio git | |

```bash
# API con Eloquent apuntando a la base «tienda»
flameware new tienda-api --db=tienda

# API servido desde la raíz del dominio en lugar de un subdirectorio
flameware new checkout --base-path=/ --rewrite-base=/

# Sitio con vistas: páginas en /mi-sitio/ y API en /mi-sitio/api/
flameware new mi-sitio --web --app-name="Mi Sitio"

# Sitio con vistas en la raíz del dominio: páginas en / y API en /api/
flameware new tienda --web --base-path=/api/ --rewrite-base=/
```

## Qué genera

```
mi-api/
├── index.php                  Front controller: CORS, config, contenedor, router
├── bootstrap.php              Arranque de Eloquent (se omite si no hay DB_DATABASE)
├── .env.local                 Variables de entorno (chmod 600, fuera de git)
├── .env.example               Copia versionable de referencia
├── .htaccess                  Rewrite al front controller + bloqueo de archivos sensibles
├── .gitignore
├── composer.json              Dependencias + script «composer routes»
├── Bin/BuildRouteCache.php    Escanea #[Route] y #[Page] y escribe el caché
├── Config/
│   ├── Config.php             Carga .env.local y expone cada clave como constante
│   └── Dependencies.php       Mapa interfaz => implementación para PHP-DI
├── Routing/                   Router, Route, Page, Http, ControllerDiscovery
├── Controllers/               HealthController de ejemplo
├── DTOs/Request|Response/     Contratos de entrada y salida del API
├── Mappers/                   Conversión DTO <-> Model (los usa el Service)
├── Models/                    Objetos de dominio (POPO, sin Eloquent)
├── Repository/                Interfaces de persistencia y sus implementaciones
├── EloquentModels/            Modelos de Eloquent y conversión Model <-> EloquentModel
├── Services/                  Lógica de negocio
├── Support/Json.php           Cuerpo de la petición → DTO, y DTO → respuesta JSON
└── Storage/
    ├── route-cache.php        Generado, fuera de git
    └── logs/app.log
```

Tras generar, `curl http://localhost/mi-api/api/health` devuelve:

```json
{"success":true,"message":"API operativa.","php":"8.5.7"}
```

Con `--web` el proyecto recibe además la capa de vistas:

```
mi-sitio/
├── Controllers/HomeController.php   Portada de ejemplo con #[Page] y Seo
├── Support/
│   ├── View.php                     View::render(), View::fetch(), View::error()
│   ├── Seo.php                      Metadatos SEO de una página
│   ├── Template.php                 El $this dentro de las vistas
│   └── Url.php                      Rutas de página, archivos de Public/ y URLs absolutas
├── Views/
│   ├── layouts/main.php             Documento HTML: <head> con el SEO y content()
│   ├── pages/home.php               Vistas de página
│   ├── components/                  navbar.php, card.php
│   └── errors/                      404.php y 500.php, los usa el router
└── Public/css/app.css               Archivos estáticos, servidos directamente por Apache
```

Y `http://localhost/mi-sitio/` muestra la portada en HTML.

## Trabajar en un proyecto generado

Un endpoint nuevo son tres pasos:

```php
// Controllers/ProductController.php
#[Route(Http::GET, 'products/{id}')]
public function show(array $params): Response { /* ... */ }
```

```bash
composer routes    # reconstruye Storage/route-cache.php
```

El caché se reconstruye solo tras cada `composer install` y `composer update`.
Hay que ejecutarlo a mano únicamente al agregar, quitar o mover un `#[Route]` o
un `#[Page]`.

Las clases concretas se resuelven por autowiring: PHP-DI lee los type hints del
constructor. Solo hay que registrar en `Config/Dependencies.php` las interfaces:

```php
\App\Services\IProductService::class => autowire(\App\Services\ProductService::class),
```

### Generar un recurso completo

```bash
flameware make:resource Product --table=FLCM_PRODUCT
```

Escribe las diez clases del recurso, registra las dos interfaces en
`Config/Dependencies.php` y reconstruye el caché de rutas:

```
Models/Product.php                       Repository/IProductRepository.php
EloquentModels/ProductEloquentModel.php  Repository/ProductRepository.php
Mappers/ProductMapper.php                Services/IProductService.php
DTOs/Request/ProductRequestDto.php       Services/ProductService.php
DTOs/Response/ProductResponseDto.php     Controllers/ProductController.php
```

El Controller queda con el CRUD enrutado: `GET /products`, `GET /products/{id}`,
`POST /products`, `PUT /products/{id}` y `DELETE /products/{id}`.

Esto existe porque **cada atributo aparece seis veces** en el patrón: propiedad
privada, getter, setter, línea en `toModel()`, línea en `toEloquentModel()` y
línea en cada dirección del mapper. Un recurso de diez campos son sesenta líneas
mecánicas donde un typo en el nombre de columna no lo detecta el linter — sale
como `null` en runtime.

#### De dónde salen los campos

**Del esquema real** (`--table`): se conecta con las credenciales del
`.env.local`, hace `SHOW COLUMNS` y deriva propiedades, tipos, nullables y PK.
Los nombres de columna no los escribe nadie, se leen. Requiere el driver PDO
activo.

**De una especificación** (`--fields`): sin tocar la base de datos.

```bash
flameware make:resource Product --fields="productId:int,name:string,price:float,createdAt:?datetime"
```

Tipos válidos: `string`, `text`, `int`, `float`, `decimal`, `bool`, `date`,
`datetime`. El prefijo `?` marca nullable. Los nombres de columna se proponen
por convención y se corrigen en el `EloquentModel`, que es el único archivo
donde aparecen.

Se generan **exactamente** los campos declarados, ninguno más: el generador no
añade una PK por su cuenta, así que tú la nombras como la nombras en la tabla.

**El primer campo es la clave primaria**, y por eso tiene que nombrar al
recurso: en `Product` valen `id`, `productId` o `idProduct`. El generador falla
con un mensaje explícito si el primero es otra cosa.

Solo el primero opta a PK. Un marcador `ID` más abajo es una foreign key
aunque nombre al propio recurso:

```bash
--fields="productId:int,name:string,categoryId:int,idSupplier:int"
#          └─ PK, ID_PRODUCT          └─ FK, ID_CATEGORY
#                                                    └─ FK, ID_SUPPLIER
```

La PK no aparece en el `RequestDto` —el cliente no la envía— pero sí en el
`ResponseDto`, y es la que usan `findOrFail()` en el Repository y el `setter`
del `update()` en el Controller.

#### Referencias a otros recursos

El tipo de un campo puede ser el nombre de un Model ya generado. La propiedad
queda tipada con esa clase en vez de con un entero suelto:

```bash
flameware make:resource Client --fields="clientId:int,code:string,personId:Person"
```

```php
private Person $personId;          // Models/Client.php

public function __construct()
{
    $this->personId = new Person(); // un objeto no tiene neutro escalar
}
```

El Model referenciado **tiene que existir ya**: no se puede tipar una propiedad
con una clase que nadie ha generado, así que el comando falla y te dice cuál
generar primero.

Hacia fuera nada cambia: el JSON del API sigue siendo plano y la referencia
viaja como la clave que la identifica, así que en los DTOs `personId` es un
`int`. La conversión ocurre en dos sitios, y en ambos el objeto se arma **solo
con su clave**:

```php
// ClientMapper::toModel() — al entrar
$personId = new Person();
$personId->setPersonId($dto->personId);
$model->setPersonId($personId);

// ClientMapper::toDto() — al salir
$dto->personId = $model->getPersonId()->getPersonId();
```

Cargar el `Person` completo es trabajo del Service. El `EloquentModel` no puede
hacerlo —depender de un Repository desde ahí rompería las capas— y el Mapper no
debe: solo traduce.

**Los ciclos se rechazan al generar.** Si `Client` tiene un `Person` y `Person`
tiene un `Client`, `new Client()` recursaría hasta desbordar la pila. El
generador recorre las referencias antes de escribir nada y aborta con la ruta
del ciclo; se rompe dejando uno de los dos lados como `:int`.

**Sin ninguno de los dos**: genera las diez clases con solo la PK —nombrada
`<recurso>Id`— para completar a mano.

#### Convención de columnas

| Marcador | Uso | Ejemplo | Tipo PHP |
|---|---|---|---|
| `ID` | Primary keys y foreign keys | `ID_EMPLOYEE` | `int` |
| `DSC` | Descripciones: nombres, textos, códigos | `DSC_ADDRESS` | `string` |
| `TYPE` | Indicadores de tipo | `TYPE_DOCUMENT` | lo decide la columna |
| `STATUS` | Campos de estado | `STATUS` | lo decide la columna |
| `AMOUNT` | Montos monetarios | `TOTAL_AMOUNT` | `float` |
| `NUM` | Valores numéricos | `NUM_QUANTITY` | `int` o `float` |
| `FEC` | Fechas | `FEC_CREATION` | `DateTimeImmutable` + cast |

Al nombre se le quita el marcador y el token de la entidad, y lo que queda pasa
a camelCase:

```
ID_EMPLOYEE             → employeeId    (PK de la tabla EMPLOYEE)
ID_CLIENT               → clientId      (FK en esa misma tabla)
DSC_ADDRESS             → address
NUM_QUANTITY            → quantity
FEC_CREATION            → creation
STATUS                  → status
TOTAL_AMOUNT            → totalAmount
```

Tres reglas que no son obvias:

**El tipo de la columna manda sobre el marcador.** `STATUS` suele ser booleano,
pero si la columna es `varchar` el estado se genera como `string`.

**`AMOUNT` va pospuesto, no antepuesto.** A diferencia del resto, en
`TOTAL_AMOUNT` el marcador forma parte del nombre y no se descarta: la propiedad
queda como `totalAmount`, no como `total`.

**El nombre no distingue PK de FK.** Toda columna `ID_*` produce la misma forma
—lo que acompaña al marcador, en camelCase, con `Id` al final—, sea la clave de
la tabla o apunte a otra. En `INVOICE`, `ID_INVOICE` produce `invoiceId` y
`ID_CLIENT` produce `clientId`. Cuál es la PK no se adivina del nombre: con
`--table` lo dice el esquema (`Key = PRI`) y con `--fields` lo dice la posición.

Ninguna propiedad se llama `id` a secas, y esa es la intención: en el Model
convive la clave propia con las ajenas, y `personId` junto a `countryId` se lee
sin ambigüedad donde `id` junto a `countryId` no.

Límites conocidos, todos corregibles a mano en el archivo generado:

- `DSC_PERSON_LASTNAME_1` produce `lastname1`: sin separadores no hay forma de
  saber dónde parte la palabra.
- `TYPE_DOCUMENT` en una tabla que no sea `DOCUMENT` produce `document`, no
  `documentType`. El marcador se descarta como cualquier otro.
- Los campos de fecha se omiten del RequestDto, asumiendo que los gestiona la
  base de datos. Si una fecha es entrada del usuario, se agrega a mano.

### Arquitectura por capas

El framework separa el objeto de dominio del modelo de Eloquent. Un `Model` es
un objeto PHP plano, sin herencia ni dependencias del ORM; el `EloquentModel` es
la única clase que conoce los nombres de las columnas. Cada capa habla con la
siguiente en un solo lenguaje, y ninguna capa por encima del `Repository` sabe
que Eloquent existe.

```
Controller  →  RequestDto  →  Service        (contrato: solo DTOs)
                                  ↓
                               Mapper  →  Model
                                  ↓
                             Repository       (interfaz: solo Model)
                                  ↓
                            EloquentModel     (única capa con columnas)
                                  ↓
                                 BD
```

De regreso sube por el mismo camino: el `Repository` devuelve `Model`, el
`Mapper` lo convierte en `ResponseDto` y el `Service` lo entrega ya traducido.

**El Service es la frontera de traducción.** Hacia arriba habla DTOs y hacia
abajo Models, así que el Mapper se inyecta ahí y no en el Controller. Eso deja
cada extremo hablando un solo idioma: el Controller no llega a ver el dominio y
el Repository no llega a ver el contrato del API.

Un `EloquentModel` nunca sale de `Repository/`, y un `Model` nunca sale de
`Services/`.

#### Models — objetos de dominio

Atributos privados, tipados y en camelCase. Constructor sin parámetros que los
inicializa a valores neutros, y getters/setters tipados. Sin anotaciones, sin
herencia, sin ORM.

```php
<?php

namespace App\Models;

use DateTimeImmutable;

class Product
{
    private int $id;
    private string $name;
    private float $price;
    private int $stock;
    private ?DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = 0;
        $this->name      = '';
        $this->price     = 0.0;
        $this->stock     = 0;
        $this->createdAt = null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
```

#### EloquentModels — la frontera con la base de datos

Declaran tabla, primary key y casts con los nombres reales de las columnas, y
son los únicos que traducen entre esos nombres y el dominio.

```php
<?php

namespace App\EloquentModels;

use App\Models\Product;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class ProductEloquentModel extends EloquentModel
{
    protected $table = 'FLCM_PRODUCT';
    protected $primaryKey = 'ID_PRODUCT';
    public $timestamps = false;

    protected $casts = [
        'FEC_PRODUCT_CREATED_AT' => 'datetime',
    ];

    public function toModel(): Product
    {
        $product = new Product();

        $product->setId((int) $this->ID_PRODUCT);
        $product->setName((string) $this->DSC_PRODUCT_NAME);
        $product->setPrice((float) $this->NUM_PRODUCT_PRICE);
        $product->setStock((int) $this->NUM_PRODUCT_STOCK);
        $product->setCreatedAt(
            $this->FEC_PRODUCT_CREATED_AT !== null
                ? DateTimeImmutable::createFromInterface($this->FEC_PRODUCT_CREATED_AT)
                : null
        );

        return $product;
    }

    public function toEloquentModel(Product $product): self
    {
        $this->DSC_PRODUCT_NAME  = $product->getName();
        $this->NUM_PRODUCT_PRICE = $product->getPrice();
        $this->NUM_PRODUCT_STOCK = $product->getStock();

        // Solo asignamos la PK en actualizaciones; en un insert la genera la BD.
        if ($product->getId() > 0) {
            $this->ID_PRODUCT = $product->getId();
        }

        return $this;
    }
}
```

`toModel()` no recibe parámetros porque el método vive en el propio
`EloquentModel`: `$this` ya es la instancia a convertir. `toEloquentModel()`
vuelca el dominio sobre `$this` y lo devuelve, lo que permite encadenar
`(new ProductEloquentModel())->toEloquentModel($product)->save()`.

#### Repository — interfaz e implementación

La interfaz solo menciona tipos de dominio. Ese es el contrato que hace posible
cambiar el ORM sin tocar nada por encima.

```php
<?php

namespace App\Repository;

use App\Models\Product;

interface IProductRepository
{
    public function findById(int $id): ?Product;

    /** @return list<Product> */
    public function findAll(): array;

    public function save(Product $product): Product;

    public function update(Product $product): void;

    public function deleteById(int $id): bool;
}
```

```php
<?php

namespace App\Repository;

use App\EloquentModels\ProductEloquentModel;
use App\Models\Product;

class ProductRepository implements IProductRepository
{
    public function findById(int $id): ?Product
    {
        return ProductEloquentModel::query()->find($id)?->toModel();
    }

    public function findAll(): array
    {
        return ProductEloquentModel::query()
            ->get()
            ->map(static fn (ProductEloquentModel $record): Product => $record->toModel())
            ->all();
    }

    public function save(Product $product): Product
    {
        $record = (new ProductEloquentModel())->toEloquentModel($product);
        $record->save();

        // Devolvemos el dominio reconstruido para que el ID generado por la BD
        // y cualquier valor por defecto lleguen de vuelta al llamador.
        return $record->toModel();
    }

    public function update(Product $product): void
    {
        ProductEloquentModel::query()
            ->findOrFail($product->getId())
            ->toEloquentModel($product)
            ->save();
    }

    public function deleteById(int $id): bool
    {
        return ProductEloquentModel::query()->whereKey($id)->delete() > 0;
    }
}
```

#### Mappers — DTO y dominio

Un mapper por recurso. Convierte el `Model` hacia el DTO de Response y el DTO de
Request hacia el `Model`, siempre a través de getters y setters. Sin lógica de
negocio ni validación: eso vive en el Service y en los `#[Assert]` del DTO.

```php
<?php

namespace App\Mappers;

use App\DTOs\Request\ProductRequestDto;
use App\DTOs\Response\ProductResponseDto;
use App\Models\Product;

class ProductMapper
{
    public function toDto(Product $model): ProductResponseDto
    {
        $dto = new ProductResponseDto();

        $dto->id        = $model->getId();
        $dto->name      = $model->getName();
        $dto->price     = $model->getPrice();
        $dto->stock     = $model->getStock();
        $dto->createdAt = $model->getCreatedAt()?->format(DATE_ATOM);

        return $dto;
    }

    public function toModel(ProductRequestDto $dto): Product
    {
        $model = new Product();

        $model->setName($dto->name);
        $model->setPrice($dto->price);
        $model->setStock($dto->stock);

        return $model;
    }
}
```

Los mappers no comparten una interfaz común porque PHP no admite estrechar tipos
de parámetro en una implementación (contravarianza): un
`IMapper::toModel(object $dto)` haría imposible declarar
`toModel(ProductRequestDto $dto)` en el hijo sin error fatal. Cada mapper es una
clase concreta con tipos exactos. Los Repository sí llevan interfaz porque sus
firmas ya son concretas.

#### Service y Controller

El Service recibe la interfaz del Repository —nunca la implementación— y el
Mapper. Su contrato está escrito en DTOs por los dos lados:

```php
<?php

namespace App\Services;

use App\DTOs\Request\ProductRequestDto;
use App\DTOs\Response\ProductResponseDto;
use App\Mappers\ProductMapper;
use App\Repository\IProductRepository;

class ProductService implements IProductService
{
    public function __construct(
        private readonly IProductRepository $repository,
        private readonly ProductMapper $mapper,
    ) {}

    public function create(ProductRequestDto $dto): ProductResponseDto
    {
        return $this->mapper->toDto(
            $this->repository->save($this->mapper->toModel($dto)),
        );
    }
}
```

El Controller no conoce el Mapper ni el dominio: entra un `RequestDto`, sale un
`ResponseDto`, y serializar es trabajo de `App\Support\Json`. Cada acción cabe
en una expresión:

```php
#[Route(Http::POST, 'products')]
public function store(): Response
{
    return Json::response(
        $this->service->create(Json::body(ProductRequestDto::class)),
        Response::HTTP_CREATED,
    );
}
```

Por eso el constructor del Controller solo pide el Service:

```php
public function __construct(
    private readonly IProductService $service,
) {}
```

#### App\Support\Json

Cuatro métodos estáticos, las dos direcciones del JSON del API:

| | |
|---|---|
| `Json::body(ProductRequestDto::class)` | cuerpo de la petición → DTO de entrada |
| `Json::response($dto)` | DTO → respuesta serializada por JMS *(200 por defecto)* |
| `Json::noContent()` | 204 sin cuerpo, la respuesta de un DELETE correcto |
| `Json::error('No encontrado.', 404)` | error con el mismo formato que usa el router |

Es una clase, no un `BaseController` ni un trait: los controladores no heredan
nada y estos métodos se pueden llamar desde cualquier parte. El serializador de
JMS vive ahí dentro, estático y perezoso — construirlo compila el grafo de
metadatos, así que se hace como mucho una vez por petición en lugar de una por
controlador instanciado.

`make:resource` copia el archivo al proyecto si no está, y nunca lo sobrescribe:
si lo editas, tus cambios se quedan.

#### Registro en el contenedor

Las interfaces se registran en `Config/Dependencies.php`; las clases concretas
(mappers incluidos) las resuelve PHP-DI por autowiring sin declararlas:

```php
public function interfaceMap(): array
{
    return [
        \App\Repository\IProductRepository::class => autowire(\App\Repository\ProductRepository::class),
        \App\Services\IProductService::class      => autowire(\App\Services\ProductService::class),
    ];
}
```

### Vistas y SEO

En un proyecto creado con `--web`, el mismo código sirve el API y un sitio con
páginas renderizadas en el servidor. El HTML sale completo desde PHP, así que
los buscadores lo indexan sin ejecutar JavaScript. No hay motor de plantillas
ni dependencias nuevas: las vistas son PHP plano.

Las capas no cambian. El controlador pide los datos al Service como siempre;
lo único distinto es que en vez de `Json::response()` devuelve una vista.

#### Rutas: `#[Page]` y `#[Route]`

`#[Page]` funciona igual que `#[Route]`, pero el router la registra bajo
`WEB_BASE_PATH` en lugar de `BASE_PATH`:

```php
#[Route(Http::GET, 'products')]      // → /mi-sitio/api/products   (JSON)
#[Page(Http::GET, '')]               // → /mi-sitio/               (HTML)
#[Page(Http::GET, 'productos/{id}')] // → /mi-sitio/productos/42   (HTML)
```

Así las URLs que indexan los buscadores quedan limpias sin tocar las del API.
Dos reglas del router:

- **Las rutas del API se registran primero**, y una página nunca atiende una
  URL que caiga dentro de `BASE_PATH`. Un comodín como `#[Page(Http::GET, '{slug}')]`
  no le puede ganar a un endpoint.
- **Los errores siguen el tipo de URL.** Dentro de `BASE_PATH`, los 404 y 500
  son JSON como siempre. Fuera de él, un 404 muestra `Views/errors/404.php`, y
  una página que lanza una excepción muestra `Views/errors/500.php` (el detalle
  va al log, nunca al HTML).

Por eso, con `--web`, `BASE_PATH` tiene que ser un prefijo propio del API: no
puede ser `/` ni coincidir con `WEB_BASE_PATH`. El generador lo comprueba.

`#[Page]` está en todos los proyectos, pero solo tiene sentido con la capa de
vistas: sin ella, los errores de cualquier URL siguen siendo JSON.

#### Renderizar desde el controlador

```php
use App\Routing\{Http, Page};
use App\Support\{Seo, View};
use Symfony\Component\HttpFoundation\Response;

#[Page(Http::GET, 'productos')]
public function index(): Response
{
    return View::render('pages.products.index', [
        'products' => $this->productService->findAll(),
    ], new Seo(
        title:       'Productos',
        description: 'Catálogo completo de productos.',
        keywords:    ['catálogo', 'productos'],
        image:       'img/og-productos.jpg',
    ));
}
```

| | |
|---|---|
| `View::render($vista, $datos, $seo, layout: 'main', status: 200)` | Renderiza y envía la página |
| `View::fetch($vista, $datos)` | Devuelve el HTML como cadena, sin layout: cuerpo de un correo, fragmento AJAX |
| `View::error(403)` | Envía `Views/errors/403.php` con ese código y `noindex` |

Las vistas se nombran con puntos: `'pages.products.index'` es
`Views/pages/products/index.php`. Un nombre no puede salir de `Views/`: solo
admite letras, números, guion y guion bajo por segmento.

La vista se renderiza entera en memoria antes de enviar nada. Si falla a mitad,
el navegador recibe la página 500, no medio documento.

#### `App\Support\Seo`

Todos los parámetros son opcionales y se pasan por nombre:

| Parámetro | Etiquetas | Si no se indica |
|---|---|---|
| `title` | `<title>`, `og:title`, `twitter:title` — como «Título \| APP_NAME» | solo `APP_NAME` |
| `description` | `description`, `og:description`, `twitter:description` | `SEO_DEFAULT_DESCRIPTION` |
| `keywords` | `keywords` — lista o cadena | se omite |
| `canonical` | `<link rel="canonical">`, `og:url` | la URL actual sin query string |
| `robots` | `robots` | `index, follow` |
| `image` | `og:image`, `twitter:image` — ruta dentro de `Public/` o URL absoluta | `SEO_DEFAULT_IMAGE` |
| `type` | `og:type` | `website` |
| `locale` | `og:locale`, `<html lang>` | `APP_LOCALE` |
| `jsonLd` | `<script type="application/ld+json">` — un schema o una lista | se omite |
| `meta` | Meta tags extra: `['author' => 'Flameware']` | |

Todo se escapa. Las etiquetas sin valor no se imprimen vacías, el canonical y
las imágenes salen como URL absoluta (Open Graph lo exige), y una página con
`noindex` no declara canonical.

#### Dentro de una vista

Las vistas son PHP plano y `$this` es un `App\Support\Template`. Cada clave del
array de datos llega como variable:

```php
<?php /** @var \App\Support\Template $this */ ?>
<h1><?= $this->e($title) ?></h1>

<?php foreach ($products as $product): ?>
    <?= $this->component('product-card', ['product' => $product]) ?>
<?php endforeach ?>

<a href="<?= $this->url('contacto') ?>">Contáctanos</a>
```

| | |
|---|---|
| `$this->e($valor)` | Escapa un valor para HTML. **Todo dato propio se imprime con él** |
| `$this->component('card', $props)` | Renderiza `Views/components/card.php` |
| `$this->url('contacto')` | `/mi-sitio/contacto`, escapada para un atributo |
| `$this->asset('css/app.css')` | `/mi-sitio/Public/css/app.css?v=…`, escapada y versionada |
| `$this->start('scripts')` … `$this->end()` | Envía lo impreso entre ambos a una sección |
| `$this->section('scripts')` | Imprime una sección en el layout |
| `$this->layout('admin')` | Cambia el layout desde la propia vista |
| `$this->content()` | En el layout: el HTML de la vista |
| `$this->seo()` | En el layout: todas las etiquetas SEO del `<head>` |
| `$this->seo->lang()` | En el layout: idioma para `<html lang>` |

Lo que devuelven `component()`, `section()`, `content()` y `seo()` ya es HTML, y
lo de `url()` y `asset()` ya viene escapado, así que se imprime tal cual. Solo
`e()` es responsabilidad de quien escribe la vista.

**Componentes.** Viven en `Views/components/` y tienen su propio ámbito: solo
ven las props que reciben, nunca las variables de la vista que los llama. Así
un componente se puede usar en cualquier página sin depender de cómo se llamen
sus variables.

**Secciones.** Sirven para que una página agregue CSS o JS propio al layout.
Si varias vistas o componentes escriben en la misma sección, el contenido se
acumula. Una sección abierta sin `end()` es un error, y se reporta con su nombre
y el de la vista.

```php
<?php $this->start('scripts') ?>
<script src="<?= $this->asset('js/galeria.js') ?>"></script>
<?php $this->end() ?>
```

**`asset()` versiona los archivos.** Agrega `?v=` con la fecha de modificación,
así el navegador los puede cachear indefinidamente y aun así recibe la versión
nueva tras cada cambio.

#### Generar una vista

```bash
flameware make:view pages.contacto
```

El primer segmento del nombre elige la plantilla:

| Nombre | Archivo | Plantilla |
|---|---|---|
| `pages.contacto` | `Views/pages/contacto.php` | Página, e imprime el método del controlador listo para pegar |
| `pages.blog.index` | `Views/pages/blog/index.php` | Página en la ruta `blog` |
| `components.product-card` | `Views/components/product-card.php` | Componente con sus props |
| `layouts.admin` | `Views/layouts/admin.php` | Documento HTML completo |
| `errors.403` | `Views/errors/403.php` | Página para `View::error(403)` |
| `emails.bienvenida` | `Views/emails/bienvenida.php` | Fragmento para `View::fetch()` |

`--force` sobrescribe y `--project=<dir>` apunta a otro proyecto. El comando
falla si el proyecto no tiene la capa de vistas.

#### Variables de la capa de vistas

`--web` agrega a `.env.local`:

| Variable | Uso |
|---|---|
| `WEB_BASE_PATH` | Prefijo de las rutas `#[Page]` y de `Public/`. Por defecto, el `RewriteBase` |
| `APP_NAME` | Sufijo del `<title>` y `og:site_name` |
| `APP_URL` | Esquema y dominio públicos, sin ruta: `https://midominio.com` |
| `APP_LOCALE` | `<html lang>` y `og:locale`, por ejemplo `es_CR` |
| `SEO_DEFAULT_DESCRIPTION` | Descripción de las páginas que no declaren la suya |
| `SEO_DEFAULT_IMAGE` | Imagen Open Graph de las páginas que no declaren la suya |

**En producción, define `APP_URL`.** Si está vacía, el canonical y las URLs de
Open Graph se construyen con el `Host` de la petición, que lo controla el
cliente. Eso sirve en desarrollo, pero un canonical no debe depender de él.

#### Seguridad

Las plantillas de `Views/` son PHP ejecutable. Si se pudieran pedir por URL,
Apache las ejecutaría fuera del framework. El `.htaccess` de un proyecto `--web`
las bloquea con `RedirectMatch 404 /Views/`, igual que `Storage/`, y
`composer.json` las excluye del classmap junto con `Public/`.

### Variables de entorno

Cada clave de `.env.local` queda disponible como constante global — agregar una
variable no requiere tocar `Config/Config.php`:

```
STRIPE_KEY=sk_test_123
```

```php
$client = new StripeClient(STRIPE_KEY);
```

`BASE_PATH` y `ERROR_LOGS_DIR` son obligatorias; el arranque falla si faltan.

## Mantener el generador

El skeleton es un directorio de archivos reales, no plantillas embebidas: para
cambiar lo que reciben los proyectos nuevos, edita `skeleton/` directamente.

`skeleton-web/` es la capa de vistas. Con `--web` se copia encima de
`skeleton/`, así que sus archivos se suman a los del skeleton y nunca deben
repetir uno de ellos. Lo que tienen que tener todos los proyectos, como
`Routing/Page.php` o el soporte de errores HTML del router, va en `skeleton/`.

Los archivos `*.stub` son los que llevan placeholders (`{{PROJECT_NAME}}`,
`{{BASE_PATH}}`, `{{REWRITE_BASE}}`, `{{DB_NAME}}`, `{{VENDOR}}`,
`{{WEB_BASE_PATH}}`, `{{APP_NAME}}`) y se renombran al generar. Los de
`skeleton-web/` (`env.web.stub` y `htaccess.web.stub`) no se renombran: se
agregan al final de `.env.local` y `.htaccess`. El mapa de ambos está en
`Cli::materializeStubs()`.

Las plantillas de `make:view` están en `lib/ViewGenerator.php`.

## Requisitos

Ver [Requisitos](#requisitos) al inicio.
