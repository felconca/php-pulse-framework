<?php

namespace Core\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeTableCommand extends Command
{
    protected static $defaultName = 'make:table';

    protected function configure()
    {
        $this
            ->setDescription('Create a new table class (simple or definition-based)')
            ->setHelp('This command allows you to create a table definition class using simple properties or an advanced static definition.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the table class')
            ->addOption(
                'with-definition',
                'd',
                InputOption::VALUE_NONE,
                'Generate the table using a static definition() method instead of simple public properties'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = ucfirst($input->getArgument('name'));
        $namespace = 'App\\Tables';
        $filePath = __DIR__ . '/../../Tables/' . $name . '.php';

        if (file_exists($filePath)) {
            $output->writeln("<error>Table class '$name' already exists!</error>");
            return Command::FAILURE;
        }

        $withDefinition = $input->getOption('with-definition');

        $simpleTemplate = <<<EOT
<?php

namespace $namespace;

class $name
{
    public int \$id = 0;
    public string \$name = '';

    /**
     * @type DATETIME
     */
    public \$created_at;

    /**
     * @type DATETIME
     * @on_update CURRENT_TIMESTAMP
     */
    public \$updated_at;
}
EOT;

        // --- Template for static definition() style ---
        $definitionTemplate = <<<EOT
<?php

namespace $namespace;

class $name
{
    public static function definition()
    {
        return [
            'columns' => [
                'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                'name' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => false],
                'created_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP'],
                'updated_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
            ],
            'foreign_keys' => [
                // ['column' => 'role_id', 'references' => 'roles(id)', 'on_delete' => 'CASCADE']
            ]
        ];
    }
}
EOT;

        // Select template based on flag
        $template = $withDefinition ? $definitionTemplate : $simpleTemplate;

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }

        file_put_contents($filePath, $template);
        $output->writeln("<info>Created table class: $name</info>");
        $output->writeln("<comment>Type: " . ($withDefinition ? 'Static definition' : 'Simple properties') . "</comment>");

        return Command::SUCCESS;
    }
}
