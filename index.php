<?php

//引入门禁库
include("zkteco.php");

//初始化门禁
$zkteco = new zkteco("192.168.100.201","4370", "0");

//连接门禁
$conn = $zkteco->connect();

print("<pre>");
print("\nconnect:  ".$conn["text"]);

if (!$conn['status']) return;

print("\n\nopen door:  ".$zkteco->unlock()["text"]);
print("\n\nplay voice: ".$zkteco->playVoice()["text"]);
print("\n\ndisconnect: ".$zkteco->disconnect()["text"]);
?>
