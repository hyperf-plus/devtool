<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HPlus\DevTool\Command;

use HPlus\UI\Form\Model;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;
use Hyperf\Utils\CodeGen\Project;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

/**
 * @Command
 */
#[Command]
class AdminCommand extends GeneratorCommand
{

    public function __construct()
    {
        parent::__construct('gen:admin');
        $this->setDescription('Create a new admin controller class');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['desc', 'd', InputOption::VALUE_OPTIONAL, 'desc', ''],
            ['force', 'f', InputOption::VALUE_NONE, 'Whether force to rewrite.'],
            ['namespace', 'N', InputOption::VALUE_OPTIONAL, 'The namespace for class.', null],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $class_name = $this->getNameInput();

        $class_name = ucfirst($class_name);
        $descInput = $this->input->getOption('desc');
        $namespaceInput = $this->input->getOption('namespace');
        $model = $this->getModel($class_name);
        $name = $this->qualifyClass($class_name);
        try {
            $path = $this->getPath($name);
        } catch (\RuntimeException $exception) {
            $output->writeln(sprintf('<fg=red>%s</>', $name . ' 命名空间不存在!'));
            return 0;
        }

        #验证是否已经存在了。存在就不能在搞了。不然会覆盖，如果想重新生成加force可以强制覆盖
        if (($input->getOption('force') === false) && $this->alreadyExists($this->getNameInput())) {
            $output->writeln(sprintf('<fg=red>%s</>', $class_name . ' already exists!'));
            return 0;
        }
        #对应模型存在才会创建
        if (!$model) {
            $output->writeln(sprintf('<fg=red>%s</>', $class_name . '模型不存在，无法生成控制器，如果表存在请先使用 “gen:model表名” 生成模型，然后在执行此方法'));
            return 0;
        }
        $table = $model->getTable();
        $namespacePath = $namespaceInput ?? $this->getAdminNamespace();
        $namespace = new PhpNamespace($namespacePath);
        $class = $namespace->addClass($class_name)->addComment('@AdminController(prefix="' . strtolower($class_name) . '", tag="' . $descInput . '", ignore=true))');
        $namespace->addUse('HPlus\Admin\Controller\AbstractAdminController');
        $namespace->addUse('HPlus\Route\Annotation\AdminController');
        $namespace->addUse('HPlus\UI\Grid');
        $namespace->addUse('HPlus\UI\Form');
        $namespace->addUse(get_class($model));

        $class->addExtend(\HPlus\Admin\Controller\AbstractAdminController::class);

        #表字段信息 [ 字段  类型  描述]
        $tableSchema = $this->getTableSchema($table);

        #开始生成grid表格
        $grid = new Method('grid');
        $grid->setProtected();
        $gridCode = "
\$grid = new Grid(new Model$class_name);
//\$grid->hidePage(); 隐藏分页
//\$grid->hideActions(); 隐藏操作
\$grid->className('m-15');\n";
        foreach ($tableSchema as $item) {
            $gridCode .= '$grid->column(\'' . $item->COLUMN_NAME . '\', \'' . $item->COLUMN_COMMENT . '\');' . PHP_EOL;
        }
        $grid->setBody($gridCode . "\nreturn \$grid;");
        $class->addMember($grid);
        #grid表格生成完成

        $form = new Method('form');
        $form->setProtected()->addParameter('isEdit', false);

        #开始生成form表格
        $code = "
\$form = new Form(new Model$class_name);
\$form->className('m-15');
\$form->setEdit(\$isEdit);\n";
        foreach ($tableSchema as $item) {
            $code .= "\$form->item('" . $item->COLUMN_NAME . "', '" . $item->COLUMN_COMMENT . "');" . PHP_EOL;

        }
        $form->setBody($code . "\nreturn \$form;");
        $class->addMember($form);
        #grid表格生成完成
        $namespace->add($class);

        $this->makeDirectory($path);
        file_put_contents($path, "<?php \n\ndeclare(strict_types=1);\n
/**
 * This file is part of Hyperf.plus
 *
 * @link     https://www.hyperf.plus
 * @document https://doc.hyperf.plus
 * @contact  4213509@qq.com
 * @license  https://github.com/hyperf-plus/admin/blob/master/LICENSE
 */\n\n" . $namespace);
        $output->writeln(sprintf('<info>%s</info>', $name . ' created successfully.'));
        return 1;
    }

    private function getTableSchema($table)
    {
        return Db::connection()
            ->select('select `COLUMN_NAME`, `DATA_TYPE`, `COLUMN_COMMENT` from information_schema.COLUMNS where `TABLE_SCHEMA` = ? and `TABLE_NAME` = ? order by ORDINAL_POSITION', [
                config('databases.default.database'),
                $table,
            ]);
    }

    protected function getServiceName()
    {
        return str_replace($this->getNamespace($this->getName()) . '\\', '', $this->getName());
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);
        $stub = str_replace('%CLASS%', $class, $stub);
        $stub = str_replace('%TITLE%', $this->input->getArgument('t') ?? $class, $stub);
        $stub = str_replace('%DESC%', $this->input->getArgument('d'), $stub);
        return $stub;
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/controller.stub';
    }

    protected function getAdminNamespace()
    {
        return $this->getConfig()['admin_namespace'] ?? 'App\\Controller\\Admin';
    }

    protected function getDefaultNamespace(): string
    {
        return $this->getConfig()['namespace'] ?? 'App\\Controller\\Admin';
    }

    /**
     * @param $table
     * @return \HPlus\Admin\Model\Model
     */
    protected function getModel($table)
    {
        $class = $this->getConfig()['model_namespace'] ?? 'App\\Model\\' . $table;
        if (!class_exists($class)) {
            return false;
        }
        return new $class;
    }


    /**
     * Get the custom config for generator.
     */
    protected function getConfig(): array
    {
        $class = Arr::last(explode('\\', static::class));
        $class = Str::replaceLast('Command', '', $class);
        $key = 'admindev.generator.controller';
        return $this->getContainer()->get(ConfigInterface::class)->get($key) ?? [];
    }

    protected function getContainer(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }
}