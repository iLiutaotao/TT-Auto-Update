<?php
/*
 * 设置脚本最大执行时间
 */
ini_set('max_execution_time', 600);

define('UPDATE_DIR_TEMP', dirname(__FILE__).'/temp/');
define('UPDATE_DIR_INSTALL', dirname(__FILE__).'/../');

class AutoUpdate {
	/*
	 * 启用日志
	 */
	private $_log = ture;
	
	/* 
	 * 日志
	 */
	public $logFile = '.updatelog';
	
	/*
	 * 最后的错误
	 */
	private $_lastError = null;
	
	/*
	 * 当前版本
	 */
	public $currentVersion = 0;
	
	/*
	 * 最新版本的名字
	 */
	public $latestVersionName = '';
	
	/*
	 * 最新版本
	 */
	public $latestVersion = null;
	
	/*
	 * 最新版本地址
	 */
	public $latestUpdate = null;
	
	/*
	 * 更新服务器地址
	 */
	public $updateUrl = 'http://api.liujiantao.me/update/';
	
	/*
	 * 服务器上的版本文件名称
	 */
	public $updateIni = 'update.ini';
	
	/*
	 * 临时下载目录
	 */
	public $tempDir = UPDATE_DIR_TEMP;
	
	/*
	 * 安装完成后删除临时目录
	 */
	public $removeTempDir = true;
	
	/*
	 * 安装目录
	 */
	public $installDir = UPDATE_DIR_INSTALL;
	
	/*
	 * 创建新文件夹权限
	 */
	public $dirPermissions = 0755;
	
	/*
	 * 更新脚本文件
	 */
	public $updateScriptName = '_upgrade.php';
	
	/*
	 * 创建新实例
	 *
	 * @param bool $log 默认: false
	 */
	public function __construct($log = false) {
		$this->_log = $log;
	}
	
	/* 
	 * 日志记录相关
	 *
	 * @param string $message 信息
	 *
	 * @return void
	 */
	public function log($message) {
		if ($this->_log) {
			$this->_lastError = $message;
			
			$log = fopen($this->logFile, 'a');
			
			if ($log) {
				$message = date('<Y-m-d H:i:s>').$message."\n";
				fputs($log, $message);
				fclose($log);
			}
			else {
				die('无法写入日志文件!');
			}
		}
	}
	
	/*
	 * 获取错误相关
	 *
	 * @return string 最后的错误
	 */
	public function getLastError() {
		if (!is_null($this->_lastError))
			return $this->_lastError;
		else
			return false;
	}
	
	private function _removeDir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") 
						$this->_removeDir($dir."/".$object); 
					else 
						unlink($dir."/".$object);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	
	/*
	 * 检查新版本
	 *
	 * @return string 最新版本
	 */
	public function checkUpdate() {
		$this->log('检查更新. . .');
		
		$updateFile = $this->updateUrl.'/update.ini';
		
		$update = @file_get_contents($updateFile);
		if ($update === false) {
			$this->log('无法获取更新文件 `'.$updateFile.'`!');
			return false;
		}
		else {
			$versions = parse_ini_string($update, true);
			if (is_array($versions)) {
				$keyOld = 0;
				$latest = 0;
				$update = '';
				
				foreach ($versions as $key => $version) {
					if ($key > $keyOld) {
						$keyOld = $key;
						$latest = $version['version'];
						$update = $version['url']; 
					}
				}
				
				$this->log('发现新版本 `'.$latest.'`.');
				$this->latestVersion = $keyOld;
				$this->latestVersionName = $latest;
				$this->latestUpdate = $update;
				
				return $keyOld;
			}
			else {
				$this->log('无法解压更新文件!');
				return false;
			}
		}
	}
	
	/*
	 * 下载更新
	 *
	 * @param string $updateUrl 更新文件URL
	 * @param string $updateFile 下载文件保存目录
	 */
	public function downloadUpdate($updateUrl, $updateFile) {
		$this->log('正在下载更新...');
		$update = @file_get_contents($updateUrl);
		
		if ($update === false) {
			$this->log('无法下载更新 `'.$updateUrl.'`!');
			return false;
		}
		
		$handle = fopen($updateFile, 'w');
		
		if (!$handle) {
			$this->log('无法保存更新文件 `'.$updateFile.'`!');
			return false;
		}
		
		if (!fwrite($handle, $update)) {
			$this->log('无法执行更新文件 `'.$updateFile.'`!');
			return false;
		}
		
		fclose($handle);
		
		return true;
	}
	
	/*
	 * 安装更新
	 *
	 * @param string $updateFile 更新文件路径
	 */
	public function install($updateFile) {
		$zip = zip_open($updateFile);
			
		while ($file = zip_read($zip)) {				
			$filename = zip_entry_name($file);
			$foldername = $this->installDir.dirname($filename);
			
			$this->log('更新中 `'.$filename.'`!');
			
			if (!is_dir($foldername)) {
				if (!mkdir($foldername, $this->dirPermissions, true)) {
					$this->log('无法创建目录 `'.$foldername.'`!');
				}
			}
			
			$contents = zip_entry_read($file, zip_entry_filesize($file));
			
			//跳过目录
			if (substr($filename, -1, 1) == '/')
				continue;
			
			//写入文件
			if (file_exists($this->installDir.$filename)) {
				if (!is_writable($this->installDir.$filename)) {
					$this->log('无法更新 `'.$this->installDir.$filename.'`, 不可写入!');
					return false;
				}
			} else {
				$this->log('文件 `'.$this->installDir.$filename.'`, 不存在!');			
				$new_file = fopen($this->installDir.$filename, "w") or $this->log('文件 `'.$this->installDir.$filename.'`, 不能创建!');
				fclose($new_file);
				$this->log('文件 `'.$this->installDir.$filename.'`, 创建成功.');
			}
			
			$updateHandle = @fopen($this->installDir.$filename, 'w');
			
			if (!$updateHandle) {
				$this->log('无法更新文件 `'.$this->installDir.$filename.'`!');
				return false;
			}
			
			if (!fwrite($updateHandle, $contents)) {
				$this->log('无法写入文件 `'.$this->installDir.$filename.'`!');
				return false;
			}
			
			fclose($updateHandle);
			
			//如果文件是更新脚本
			if ($filename == $this->updateScriptName) {
				$this->log('尝试更新 `'.$this->installDir.$filename.'`.');
				require($this->installDir.$filename);
				$this->log('更新脚本 `'.$this->installDir.$filename.'` 包含!');
				unlink($this->installDir.$filename);
			}
		}
		
		zip_close($zip);
		
		if ($this->removeTempDir) {
			$this->log('临时目录 `'.$this->tempDir.'` 被删除.');
			$this->_removeDir($this->tempDir);
		}
		
		$this->log('更新 `'.$this->latestVersion.'` 安装完成.');
		
		return true;
	}
	
	
	/*
	 * 更新最新版本
	 */
	public function update() {
		//检查最新版本
		if ((is_null($this->latestVersion)) or (is_null($this->latestUpdate))) {
			$this->checkUpdate();
		}
		
		if ((is_null($this->latestVersion)) or (is_null($this->latestUpdate))) {
			return false;
		}
		
		//更新
		if ($this->latestVersion > $this->currentVersion) {
			$this->log('Updating...');
			
			//排除文件
			if ($this->tempDir[strlen($this->tempDir)-1] != '/');
				$this->tempDir = $this->tempDir.'/';
			
			if ((!is_dir($this->tempDir)) and (!mkdir($this->tempDir, 0777, true))) {
				$this->log('临时目录 `'.$this->tempDir.'` 不存在并且无法创建!');
				return false;
			}
			
			if (!is_writable($this->tempDir)) {
				$this->log('临时目录 `'.$this->tempDir.'` 不可写入!');
				return false;
			}
			
			$updateFile = $this->tempDir.'/'.$this->latestVersion.'.zip';
			$updateUrl = $this->updateUrl.'/'.$this->latestVersion.'.zip';
			
			//下载更新
			if (!is_file($updateFile)) {
				if (!$this->downloadUpdate($updateUrl, $updateFile)) {
					$this->log('无法下载更新!');
					return false;
				}
				
				$this->log('最新更新下载 `'.$updateFile.'`.');
			}
			else {
				$this->log('最新更新下载到 `'.$updateFile.'`.');
			}
			
			//解压
			return $this->install($updateFile);
		}
		else {
			$this->log('没有可用更新');
			return false;
		}
	}
}