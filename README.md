PHP自动更新系统
==
##一个简单的PHP更新系统
server这个文件夹是放置在服务器端的，用于打包需要更新的文件
update.ini这个文件是用于写更新文本的写法如下
···
[1]
version = 0.1
url = http://api.liujiantao.me/update/1.zip
[2]
version = 0.2
url = http://api.liujiantao.me/update/2.zip
···
第一行版本名称，第二行版本号，第三行下载地址（绝对路径）；
update文件夹里面就是客户端需要使用的，可以修改index.php里面相关配置
## 问题
- 不会出现删除客户端有而服务端没有的文件
- 目前尚无法做成差量更新系统
--
刘建涛：http://www.liujiantao.me/