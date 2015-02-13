<?php 
require 'update.php';
$update = new AutoUpdate(true);
$update->currentVersion = 0; //版本号，整数
$update->updateUrl = 'http://api.liujiantao.me/update'; //更新服务器URL
$latest = $update->checkUpdate();
if ($latest !== false) {
	if ($latest > $update->currentVersion) {
		echo "新版本: ".$update->latestVersionName."<br>";
		echo "升级中...<br>";
		if ($update->update()) {
			echo "更新成功";
		}
		else {
			echo "更新失败，请打开GitHub进行更新!";
		}
	}
	else {
		echo "当前是最新版本";
	}
}
else {
	echo $update->getLastError();
}
?>