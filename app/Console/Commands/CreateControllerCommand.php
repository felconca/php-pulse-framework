<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateControllerCommand extends Command
{
    protected static $defaultName = 'create:controller';

    protected function configure()
    {
        $this
            ->setDescription('Create a new controller class')
            ->setHelp('This command allows you to create a new controller class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $controllerName = ucfirst($name) . 'Controller'; // e.g., User → UserController
        $namespace = 'App\\Controllers';
        $filePath = __DIR__ . '/../../Controllers/' . $controllerName . '.php';

        if (file_exists($filePath)) {
            $output->writeln("<error>Controller $controllerName already exists!</error>");
            return Command::FAILURE;
        }

        $template = <<<EOT
<?php
namespace $namespace;

use Includes\\Rest;
use App\\Database\\Database;
use App\\Requests\\RequestValidator;
use Firebase\J\WT\\JWT;
use Firebase\\JWT\\Key;

class $controllerName extends Rest
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Manila');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
        header("Access-Control-Allow-Credentials: true");

        if (\$_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        parent::__construct();
        
        \$this->jwtSecret = \$_ENV['JWT_SECRET'] ?? 'default-secret';
        \$this->db = new Database();
    }

    public function index()
    {
        \$this->response(['message' => '$controllerName index'], 200);
    }
}
EOT;

        file_put_contents($filePath, $template);
        $output->writeln("<info>Created Controller: $controllerName</info>");
        return Command::SUCCESS;
    }
}
