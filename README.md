# Zkteco-Doorunlock

这是一个Zkteco系列考勤机的非官方软件库。采用纯php编写，支持设置通信密码，你可以使用它实现通过Website方式轻松的控制你的zkteco系列考勤机，而不需要安装官方SDK。

此代码由 [Forgot Fish](https://www.doffish.com) 改编自国外一大神用Python写的 [pyzk](https://github.com/fananimi/pyzk)。此代码并未没有实现全部的功能，因为我只是用来实现Zkteco-F18门禁机的远程开门，并且没有实现TCP连接，仅实现了UPD连接，需要的可以自行扩展。

# Demonstration

```php
//引入门禁库
include("zkteco.php");

//初始化门禁
//$zkteco = new zkteco("ipaddress","port", "password");
$zkteco = new zkteco("192.168.100.201","4370", "0");

//连接门禁
$conn = $zkteco->connect();

print("<pre>");
print("\nconnect:  ".$conn["text"]);

if (!$conn['status']) return;

print("\n\nopen door:  ".$zkteco->unlock()["text"]);
print("\n\nplay voice: ".$zkteco->playVoice()["text"]);
print("\n\ndisconnect: ".$zkteco->disconnect()["text"]);

//$zkteco->unlock($time);      //default $time = 3
//$zkteco->playVoice($index);  //default $index = 0
```
