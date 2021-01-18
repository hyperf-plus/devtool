# devtool
hyperf 开发工具

建好表后，需要先执行 php bin/hyperf.php gen:model 表名 创建模型，
然后执行  php bin/hyperf.php gen:admin 模型名 
          -d 介绍 
          -N 命名空间 默认 \App\Controller\Admin
          -f 如果文件存在则会强制覆盖
例如 ：
 数据库表为  users
 执行 “php bin/hyperf.php gen:model users” 模型生成在 App\model\User
 执行 “php bin/hyperf.php gen:admin User -d 用户管理” 
 
 即可生成 User.php控制器文件、grid、form会把所有字段都列出来，自行根据需求进行修改
