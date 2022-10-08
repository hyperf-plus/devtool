<?php

declare(strict_types=1);

namespace HPlus\DevTool\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


/**
 * @Command
 * 使用文档
 */
class EntityCommand extends HyperfCommand
{
    /**
     * 执行的命令行
     *
     * @var string
     */
    protected $name = 'gen:entity_class';

    public function configure()
    {
        parent::configure();
        $this->addOption('class', 'c', InputOption::VALUE_REQUIRED, '类名称', '');
        $this->addOption('namespace', 's', InputOption::VALUE_REQUIRED, '命名空间', '');
        $this->addOption('path', 'p', InputOption::VALUE_REQUIRED, '生成路径', '');
        $this->addOption('data', 'd', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, '数据', []);

    }

    public function handle()
    {
        $className = $this->input->getOption('class') ?? '';
        $namespace = $this->input->getOption('namespace') ?? '';
        $path = $this->input->getOption('path') ?? '';
        $data = $this->input->getOption('data') ?? '';


        $namespace = new PhpNamespace($namespace);

        $class = $namespace->addClass($className.'Entity');

        if(!empty($data)){
            foreach ($data as $value){

                $property = strtolower(substr($value,0,1)).substr($this->getUcwords($value),1);
                $class->addProperty($property);

                $grid = new Method('set'.$this->getUcwords($value));
                $grid->setPublic()->setBody("\n \$this->$property = \$$value;\n")->addParameter($value);
                $class->addMember($grid);


                $grid = new Method('get'.$this->getUcwords($value));
                $grid->setPublic()->setBody("\n return \$this->$property;\n");
                $class->addMember($grid);

            }
        }

        $this->makeDirectory(BASE_PATH.$path);
        file_put_contents(BASE_PATH.$path.$className.'Entity.php', "<?php \n\ndeclare(strict_types=1);\n
/**
 * This file is part of Hyperf.plus
 *
 * @link     https://www.hyperf.plus
 * @document https://doc.hyperf.plus
 * @contact  4213509@qq.com
 * @license  https://github.com/hyperf-plus/admin/blob/master/LICENSE
 */\n\n" . $namespace);

    }

    /**
     * 生成目录
     * @param $path
     * @return mixed
     */
    protected function makeDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }



    public function getUcwords($value){
        $value = ucwords(str_replace(['_', '-'], ' ', $value));
        return str_replace(' ', '', $value);
    }
}
