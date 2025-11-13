[![My Skills](https://skillicons.dev/icons?i=php,bash&theme=light)](https://skillicons.dev)

# PHP Pulse - A Lightweight PHP API Framework

PHP Pulse is a Laravel-inspired PHP framework designed for building modern APIs. It features PSR-4 autoloading, a CLI tool for generating controllers and creating database, tables dynamically using the symfony console, Middleware Authentication like JWT-based and Cookies Session, environment-based configuration, and a flexible routing system—all without the overhead of a full framework.

## 🔑 Features

- **PSR-4 Autoloading**: No manual `require_once`—Composer handles it all.
- **CLI Tool**: Generate controllers with `php console create:controller`.
- **CLI Tool**: Generate database with `php console create:database`.
- **CLI Tool**: Generate tables with `php console create:table`.
- **CLI Tool**: Migrating tables with `php console migrate:table`.
- **Routing**: Simple, method-specific routes with parameter support (e.g., `/users/{id}`).
- **Database**: Multi-database connections via `.env`.
- **Authentication**: JWT middleware for secure endpoints.
- **Error Handling**: Consistent JSON error responses.
- **Extensible**: Add libraries via Composer (e.g., `firebase/php-jwt`, `symfony/console`).

## 📦 Requirements

- PHP 7.4 or higher
- Composer
- Web Server with `mod_rewrite` enabled (optional: Apache, Nginx, etc.)
- MySQL/MariaDB
- Redis
- Git (optional, for cloning)

## 📥 Installation

Follow these steps to get PHP Pulse running locally.

### 1. Clone or Download

```bash
    git clone <repository-url> PHP Pulse
    cd PHP Pulse
```

### 2. Run Composer to install dependecies

```bash
    composer install
```

# :pushpin: PHP Pulse - How to Create a Controller via CLI

This guide explains how to use the PHP Pulse CLI tool to create a new controller. It assumes you’ve already set up PHP Pulse (installed dependencies with Composer, configured your environment, and have the CLI tool ready).

## Prerequisites

- PHP Pulse project installed (`composer install` has been run).
- Located in the `PHP Pulse/` root directory.

## Creating a Controller via CLI

PHP Pulse provides a command-line interface (CLI) tool called `console` to streamline development. You can use it to generate new controllers quickly.

### Step 1: Run the Command

To create a new controller, use the `create:controller` command followed by the desired controller name (without "Controller" suffix).

- **Command**:
  ```bash
  php console create:controller <ControllerName>
  ```
- **Example:**
  ```bash
    php pulse create:controller User
    Created Controller: UserController
  ```
  This generates a file at `app/Controllers/UserController.php`

### Step 2: Verify the Generated Controller

The CLI creates a basic controller extending the Rest class. Here’s what the generated file looks like:

- **File: `app/Controllers/UserController.php`**

```php
    <?php
    namespace App\Controllers;

    use Includes\Rest;

    class UserController extends Rest
    {
        public function __construct()
        {
            parent::__construct();
        }

        public function index()
        {
            $this->response(['message' => 'UserController index'], 200);
        }
    }
```

# :pushpin: PHP Pulse - How to Create a Route and Use a Controller in Routes

This guide explains how to define a route in PHP Pulse and connect it to a controller method using the routing system in `public/index.php`. It assumes you’ve already set up PHP Pulse and have a controller ready (e.g., created via `php pulse create:controller`).

## Prerequisites

- PHP Pulse project installed and running.
- A controller exists in `app/Controllers/` (e.g., `UserController.php`).
- Located in the `PHP Pulse/` root directory.

## Creating a Route and Using a Controller

Routes in PHP Pulse are defined in `public/index.php` as an array of route definitions. Each route specifies the HTTP method, path, controller method, and optional middleware.

### Step 1: Open the Routing File

- Navigate to `public/index.php`, the central entry point where all routes are configured.

### Step 2: Define a Route

- Routes are stored in the `$routes` array in the format: `[method, path, controller@method, middleware[]]`.
- Add your new route to this array, specifying:

  - **Method**: HTTP method (e.g., `GET`, `POST`).
  - **Path**: URL path (e.g., `user`, `user/{id}`).
  - **Controller@Method**: The controller class and method to call (e.g., `UserController@index`).
  - **Middleware**: An array of middleware instances (e.g., `[new \App\Middleware\AuthMiddleware()]` or `[]` for none).

- **Example**:
  Add this to the `$routes` array:
  ```php
     ['GET', 'user', 'UserController@index', []],
  ```
- **Explanation:**
  - `GET`: Responds to GET requests.
  - `user`: Matches `/user` path.
  - `UserController@index`: Calls the index method in `App\Controllers\UserController`.
  - `[]`: No middleware applied.

### Step 3: Ensure the Controller Exists

- The controller must be in `app/Controllers/` and match the namespace `App\Controllers`.
- If you created it via CLI (`php pulse create:controller User`), it’s already set up:

```php
    <?php
    namespace App\Controllers;

    use Includes\Rest;

    class UserController extends Rest
    {
        public function __construct()
        {
            parent::__construct();
        }

        public function index()
        {
            $this->response(['message' => 'UserController index'], 200);
        }
    }
```

- If the method doesn’t exist, add it (e.g., for a `show` method):

```php
    public function show($params)
    {
        $id = $params['id'] ?? 'unknown';
        $this->response(['message' => "Showing user $id"], 200);
    }
```

### Step 4: Add a Route with Parameters (Optional)

- To use route parameters (e.g., `/user/123`), include `{param}` in the path:

```php
    ['GET', 'user/{id}', 'UserController@show', []],
```

- The `$params` array in the controller method will contain the value (e.g., `$params['id'] = '123'`).

### Step 5: Add Middleware (Optional)

- To protect a route with authentication:

```php
    ['GET', 'user/{id}', 'UserController@show', [new AuthMiddleware()]]
```

- This requires a valid JWT token in the `X-Auth-Token` header.

### Step 6: Test the Route

- Use `curl` or a browser to test your route.
- **Examples**:

```bash
    curl http://localhost/PHP Pulse/user
```

- Expected: `{"message":"UserController index"}`

```bash
    curl http://localhost/PHP Pulse/user/123
```

- Expected: `{"message":"Showing user 123"}`

```bash
    curl http://localhost/PHP Pulse/user/123 -H "X-Auth-Token: <your-token>"
```

- Expected (with middleware): `{"message":"Showing user 123"}` if token is valid.

### :pushpin: NOTES:

- Token is generated through process called `authentication` with the secret key assigned in your env file.

```php
<?php

    namespace App\Controllers;

    use Includes\Rest;
    use App\Database\Database;
    use App\Requests\RequestValidator;
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    class ApiController extends Rest
    {
        private $db;
        private $jwtSecret;

        public function __construct()
        {
            date_default_timezone_set('Asia/Manila');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
            header("Access-Control-Allow-Credentials: true");

            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                http_response_code(200);
                exit();
            }

            parent::__construct();
            $this->jwtSecret = $_ENV['JWT_SECRET'] ? $_ENV['JWT_SECRET'] : 'default-secret'; // Fallback for testing
            $this->db = new Database();
        }

        public function login()
        {
            if ($this->get_request_method() != "POST") {
                $this->response('', 405);
            }
            $input = $this->inputs();
            $rules = [
                'email' => 'required|email',
                'password' => 'required|min:6',
            ];
            $errors = RequestValidator::validate($input, $rules);
            if (!empty($errors)) {
                $this->response(['errors' => $errors], 400);
            }


            $payload = [
                'iat' => time(),
                'exp' => time() + 3600,
                'email' => $rules['email'],
            ];
            $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

            $this->response(['token' => $token], 200);
        }
    }
```

# :pushpin: PHP Pulse - How to Connect Database

- Your database connection is manage in `app/Database/Database.php` All you need is copy the `.env.example` to `.env` file and edit the database section `# DATABASE CONNECTION`
- **Example**:

```env
    DB_HOST=localhost
    DB_USER=root
    DB_PASSWORD=
    DB_CONNECTIONS=mydatabase
    JWT_SECRET=your-super-secret-key-12345
```

- You can have multiple database in `DB_CONNECTIONS` seperated by `comma` with no `spaces`

```env
    DB_CONNECTIONS=mydatabase,db2,db3
```

### How to access the database in your controller

- calling the database instances in your `controller`, check this example:

```php
    <?php

    namespace App\Controllers;

    use Includes\Rest;
    use App\Database\Database;
    use App\Requests\RequestValidator;
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    class UserController extends Rest
    {
        private $db;
        private $jwtSecret;

        public function __construct()
        {
            parent::__construct();
            $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret';
            $this->db = new Database();
        }

            public function users()
        {
            $db = $this->db->getConnection('mydatabase');
            if ($db === null) {
                $this->response(['error' => 'Database connection failed'], 500);
            }
            $result = $db->query("SELECT * FROM users");
            if ($result === false) {
                $this->response(['error' => 'Database error: ' . $db->error], 500);
            }
            $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $this->response($data, 200);
        }

    }
```
