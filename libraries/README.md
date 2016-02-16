Xunsearch SDK for PHP: 自述文件
==============================
$Id$

这是采用 PHP 语言编写的 xunsearch 开发包，在此基础上开发您自己的全文检索。

在此简要介绍以下几个文件：

    - lib/XS.php             入口文件，所有搜索功能必须包含此文件
    - util/RequireCheck.php  命令行运行，用于检测您的 PHP 环境是否符合运行条件
    - util/IniWizzaard.php   命令行运行，用于帮助您编写 xunsearch 项目配置文件
    - util/Quest.php         命令行运行，搜索测试工具
    - util/Indexer.php       命令行运行，索引管理工具
    - util/SearchSkel.php    命令行运行，根据配置文件生成搜索骨架代码
    - app/user.ini           项目的配置文件，需要每个项目配置不同的内容
    

在开始编写您的代码前强烈建议执行 util/RequireCheck.php 以检查环境。

###创建和数据库关联的索引

sudo util/Indexer.php --rebuild --source=mysql://root:124224high@127.0.0.1/dx --sql="select entities.guid,entities.time_created,core_entities.main,user_groups.group_name,users.username,users.email from entities inner join core_entities on core_entities.guid=entities.guid inner join users on users.guid=entities.guid inner join user_groups on user_groups.id=users.group_id" --project=/Users/xy/htdocs/dongxi/application/third_party/xunsearch/libraries/app/user.ini





具体各项文档内容请参阅子目录： doc/ 
强烈推荐在线阅读我们的文档：<http://www.xunsearch.com/doc/>

