# Refactoring Guide: From Legacy PHP to a Modern Application

This guide provides a structured, step-by-step process to refactor your existing PHP application. The goal is to address architectural issues like "God Files", inconsistent configuration, and the lack of modern development practices. Following these steps will make your codebase more secure, maintainable, scalable, and easier to work on.

## Phase 0: Setup & Version Control

Before making any changes, it is crucial to put your project under version control. This will allow you to track changes, revert to previous states if something goes wrong, and collaborate with others more easily.

**1. Initialize a Git Repository:**
If you don't have Git installed, download it from [git-scm.com](https://git-scm.com/).
Open a terminal or command prompt in your project root (`C:\xampp\htdocs\ems`) and run:
```bash
git init
```

**2. Create a `.gitignore` file:**
This file tells Git to ignore files that shouldn't be committed to the repository, like vendor directories, configuration files with sensitive data, and temporary files. Create a file named `.gitignore` in the project root and add the following:
```
/vendor/
/uploads/
*.log
*~
config.php
```
**IMPORTANT**: We are ignoring `config.php` because it contains sensitive database credentials. Instead, you will have a `config.sample.php` that serves as a template.

**3. Create a `config.sample.php`:**
Make a copy of `config.php` and name it `config.sample.php`. Remove the actual credential values from `config.sample.php` and replace them with placeholders.

`config.sample.php`:
```php
<?php
// Database credentials - PLEASE UPDATE
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_db_name');

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
```

**4. Make your first commit:**
Add all your existing files to the repository and commit them. This will be your starting point.
```bash
git add .
git commit -m "Initial commit of the legacy project"
```

## Phase 1: Centralize Configuration & Database Connection

Your system has hardcoded database credentials in multiple files. This phase will fix that.

**1. Create a central database connection:**
Modify your `config.php` to establish a single, reusable database connection. It's also a good practice to switch from `mysqli_*` procedural functions to the **PDO (PHP Data Objects)** extension, which is more modern, secure (helps prevent SQL injection with prepared statements), and flexible.

`config.php`:
```php
<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your actual username
define('DB_PASSWORD', ''); // Your actual password
define('DB_NAME', 'ems'); // Your actual database name

// PDO Connection
try {
    $pdo = new PDO("mysql:host = " . DB_SERVER . ";dbname = " . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
}
```

**2. Update all files to use the central connection:**
Search your entire project for `mysqli_connect` or `new mysqli`. Replace all instances with a single include of your configuration file.

For every file that needs a database connection (e.g., `employee.php`, `login.php`, `payslip.php`), make sure the **first line** is:
```php
<?php
require_once 'config.php';
// ... rest of the file's code
```
Now, you can use the `$pdo` object from `config.php` for all your database queries. You will need to refactor all your `mysqli_*` queries to PDO queries.

**Example `mysqli` to `PDO` conversion:**
*Old `mysqli` query:*
```php
$sql = "SELECT * FROM employees WHERE id = " . $_GET['id'];
$result = mysqli_query($link, $sql);
$employee = mysqli_fetch_assoc($result);
```

*New `PDO` query (using prepared statements to prevent SQL injection):*
```php
$sql = "SELECT * FROM employees WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_GET['id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
```

## Phase 2: Introduce Composer for Dependency Management

**Composer** is the standard dependency manager for PHP. You will use it for autoloading your classes and to install packages like a testing framework.

**1. Install Composer:**
If you don't have it, follow the instructions at [getcomposer.org](https://getcomposer.org/download/).

**2. Create `composer.json`:**
In your project root, create a file named `composer.json`. This file will define your project's dependencies and autoloading rules.
```json
{
    "name": "your-vendor/ems",
    "description": "Employee Management System",
    "type": "project",
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "require": {}
}
```
This configuration tells Composer that your application's classes will be under the `App` namespace and located in a `src/` directory (which you will create next).

**3. Install and create `src` directory:**
Run `composer install` in your terminal. This will generate a `vendor` directory and an `autoload.php` file.
Now, create the `src` directory. Your project structure will look like this:
```
/src/
/vendor/
composer.json
... (rest of your files)
```

## Phase 3: Refactor to a Model-View-Controller (MVC) like pattern

This is the most significant part of the refactoring. The goal is to separate concerns:
*   **Models:** Handle data and business logic. They interact with the database.
*   **Views:** Display data. They are your HTML templates.
*   **Controllers:** Handle user input, interact with Models, and decide which View to show.

Let's use `employee.php` as our refactoring example.

**1. Create a Model:**
Create a file for your `Employee` model at `src/Models/Employee.php`. This class will be responsible for all database operations related to employees.

`src/Models/Employee.php`:
```php
<?php
namespace App\Models;

use PDO;

class Employee
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM employees ORDER BY lastname ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $sql = "SELECT * FROM employees WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ... Add other methods for creating, updating, deleting employees
}
```

**2. Create a View:**
Create a new directory `views`. Inside it, create a file `views/employee_list.php`. This file will only contain the HTML to display the list of employees. The original `employee.php` contains this logic already, so you will move it from there.

`views/employee_list.php`:
```php
<!DOCTYPE html>
<html>
<head>
    <title>Employee List</title>
    <!-- Your CSS links -->
</head>
<body>
    <h1>Employees</h1>
    <table>
        <thead>
            <tr>
                <th>Last Name</th>
                <th>First Name</th>
                <th>Position</th>
                <!-- etc. -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $employee):
            ?>
            <tr>
                <td><?= htmlspecialchars($employee['lastname']) ?></td>
                <td><?= htmlspecialchars($employee['firstname']) ?></td>
                <td><?= htmlspecialchars($employee['position']) ?></td>
                <!-- etc. -->
            </tr>
            <?php endforeach;
            ?>
        </tbody>
    </table>
</body>
</html>

```

**3. Create a Controller:**
Your original `employee.php` file will become the controller. It will connect the Model and the View.

`employee.php` (The new Controller):
```php
<?php
// 1. Load Composer's autoloader and the config file
require_once 'vendor/autoload.php';
require_once 'config.php';

use App\Models\Employee;

// 2. Logic
$employeeModel = new Employee($pdo);
$employees = $employeeModel->getAll(); // Get data from the model

// 3. Load the View
// The view file will now have access to the $employees variable
require 'views/employee_list.php';

```

By following this pattern, you have successfully separated the database logic (Model), the presentation (View), and the request handling (Controller). You can now apply this pattern to `payslip.php`, `leave.php`, and all your other files.

## Phase 4: Introduce an API Layer

To prepare for mobile apps or other clients, you can create API endpoints that return data in a universal format like JSON.

**1. Create an `api` directory:**
Create a new directory named `api` in your project root.

**2. Create an API endpoint:**
Let's create an endpoint to get all employees. Create `api/employees.php`:

`api/employees.php`:
```php
<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../vendor/autoload.php';
require_once '../config.php';

use App\Models\Employee;

$employeeModel = new Employee($pdo);
$employees = $employeeModel->getAll();

echo json_encode($employees);
```
When you navigate to `http://localhost/ems/api/employees.php`, you will get a JSON response containing all employees, which any client application can easily consume.

## Phase 5: Automated Testing

Automated tests ensure that your application works as expected and prevent you from accidentally breaking functionality when you make changes.

**1. Install PHPUnit:**
Use composer to add PHPUnit as a development dependency.
```bash
composer require --dev phpunit/phpunit
```

**2. Create a test:**
Create a `tests` directory. Inside it, let's write a simple test for our `Employee` model. Create `tests/EmployeeTest.php`.

`tests/EmployeeTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;
use App\Models\Employee;

class EmployeeTest extends TestCase
{
    protected static $pdo;

    public static function setUpBeforeClass(): void
    {
        // Use an in-memory database or a separate test database for tests
        self::$pdo = new PDO('sqlite::memory:');
        
        // Create a dummy employees table and insert data
        self::$pdo->exec("CREATE TABLE employees (
            id INTEGER PRIMARY KEY,
            firstname TEXT,
            lastname TEXT,
            position TEXT
        )");
        self::$pdo->exec("INSERT INTO employees (firstname, lastname, position) VALUES ('John', 'Doe', 'Developer')");
    }

    public function testGetAllEmployees()
    {
        $employeeModel = new Employee(self::$pdo);
        $employees = $employeeModel->getAll();

        $this->assertCount(1, $employees);
        $this->assertEquals('Doe', $employees[0]['lastname']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo = null;
    }
}
```

**3. Run tests:**
You can run your tests from the project root by executing:
```bash
./vendor/bin/phpunit tests
```

## Phase 6: Modernize Deployment

Stop manually copying files via FTP or XAMPP.
*   **Use Git for Deployment:** Many modern web hosts (like DigitalOcean, Heroku, etc.) allow you to deploy your application simply by pushing your Git repository to them.
*   **Deployment Scripts:** For a simple VPS, you can write a shell script that pulls the latest changes from your repository and runs composer install.

This refactoring process is an investment that will pay off significantly in the long run. Your code will be more professional, easier to manage, and ready for future growth.
