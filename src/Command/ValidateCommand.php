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
use HPlus\Validate\Validate;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Schema\Schema;
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
class ValidateCommand extends GeneratorCommand
{

    public function __construct()
    {
        parent::__construct('gen:validation');
        $this->setDescription('Create a new validate class');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['name', 'd', InputOption::VALUE_OPTIONAL, 'desc', ''],
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
        $model_name = ucfirst($class_name);

        $name = $this->qualifyClass($class_name);
        try {
            $path = $this->getPath($name);
        } catch (\RuntimeException $exception) {
            $output->writeln(sprintf('<fg=red>%s</>', $name . ' 命名空间不存在!'));
            return 0;
        }

        $table = Schema::getColumnTypeListing($this->getNameInput());
        #验证是否已经存在了。存在就不能在搞了。不然会覆盖，如果想重新生成加force可以强制覆盖
        if (($input->getOption('force') === false) && $this->alreadyExists($this->getNameInput())) {
            $output->writeln(sprintf('<fg=red>%s</>', $class_name . ' already exists!'));
            return 0;
        }
        #对应模型存在才会创建
        if (!$table) {
            $output->writeln(sprintf('<fg=red>%s</>', $class_name . '数据表不存在，请确认表名称是否正确！'));
            return 0;
        }
        $rule = [
            'limit' => 'integer',
            'page' => 'integer'
        ];
        $field = [
            'limit' => '条数限制',
            'page' => '分页ID'
        ];
        $scene = [];
        $sceneTmp = [];
        foreach ($table as $item) {
            $rule[$item['column_name']] = $this->getRule($item);
            $field[$item['column_name']] = $item['column_comment'];
            $sceneTmp[] = $item['column_name'];
        }

        $scene['create'] = $sceneTmp;
        $scene['update'] = $sceneTmp;
        $scene['delete'] = 'id';
        $scene['list'] = ['limit', 'page'];
        $class_name = $class_name . 'Validate';
        $namespacePath = $namespaceInput ?? $this->getValidateNamespace();
        $namespace = new PhpNamespace($namespacePath);
        $namespace->addUse('HPlus\Validate\Validate');
        $class = new \Nette\PhpGenerator\ClassType();
        $class->setName($class_name);
        $class->setComment('验证器类');
        $class->addProperty('rule')
            ->setProtected()
            ->setValue($rule)
            ->setInitialized();

        $class->addProperty('field')
            ->setProtected()
            ->setValue($field)
            ->setInitialized();

        $class->addProperty('scene')
            ->setProtected()
            ->setValue($scene)
            ->setInitialized();

        $class->addExtend(Validate::class);
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

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     *
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

    protected function getRule($data)
    {
        $ext = '';
        if ($data['data_type'] === 'varchar' && $data['column_type']) {
            $tmp = str_replace('varchar(', '', $data['column_type']);
            $tmp = str_replace('char(', '', $tmp);
            $tmp = trim($tmp, ')');
            $ext = '|length:0,' . $tmp;
        }

        switch ($data['data_type'] ?? '') {
            case 'datetime':
            case 'timestamp':
            case 'date':
                return 'dateFormat';
            case 'time':
                return 'time';
            case 'tinyint':
                return 'integer';
            case 'varchar':
                return 'string' . $ext;
            default:

                break;
        }
        return '';
    }

    /**
     * @param $table
     *
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
     * @param $table
     *
     * @return \HPlus\Admin\Model\Model
     */
    protected function getValidateNamespace()
    {
        return $this->getConfig()['model_namespace'] ?? 'App\\Validate';
    }


    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param string $name
     *
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $namespace = $this->input->getOption('namespace');
        if (empty($namespace)) {
            $namespace = $this->getValidateNamespace();
        }

        return $namespace . '\\' . $name . 'Validate';
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