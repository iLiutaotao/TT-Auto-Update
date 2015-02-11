<?php

/*
 * Set the script max execution time
 */
ini_set('max_execution_time', 60);

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
	 * @param bool $log Default: false
	 */
	public function __construct($log = false) {
		$this->_log = $log;
	}
	
	/* 
	 * 日志记录相关
	 *
	 * @param string $message The message
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
				die('Could not write log file!');
			}
		}
	}
	
	/*
	 * 获取错误相关
	 *
	 * @return string Last error
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
	 * @return string The latest version
	 */
	public function checkUpdate() {
		$this->log('Checking for a new update. . .');
		
		$updateFile = $this->updateUrl.'/update.ini';
		
		$update = @file_get_contents($updateFile);
		if ($update === false) {
			$this->log('Could not retrieve update file `'.$updateFile.'`!');
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
				
				$this->log('New version found `'.$latest.'`.');
				$this->latestVersion = $keyOld;
				$this->latestVersionName = $latest;
				$this->latestUpdate = $update;
				
				return $keyOld;
			}
			else {
				$this->log('Unable to parse update file!');
				return false;
			}
		}
	}
	
	/*
	 * 下载更新
	 *
	 * @param string $updateUrl Url where to download from
	 * @param string $updateFile Path where to save the download
	 */
	public function downloadUpdate($updateUrl, $updateFile) {
		$this->log('Downloading update...');
		$update = @file_get_contents($updateUrl);
		
		if ($update === false) {
			$this->log('Could not download update `'.$updateUrl.'`!');
			return false;
		}
		
		$handle = fopen($updateFile, 'w');
		
		if (!$handle) {
			$this->log('Could not save update file `'.$updateFile.'`!');
			return false;
		}
		
		if (!fwrite($handle, $update)) {
			$this->log('Could not write to update file `'.$updateFile.'`!');
			return false;
		}
		
		fclose($handle);
		
		return true;
	}
	
	/*
	 * 安装更新
	 *
	 * @param string $updateFile Path to the update file
	 */
	public function install($updateFile) {
		$zip = zip_open($updateFile);
			
		while ($file = zip_read($zip)) {				
			$filename = zip_entry_name($file);
			$foldername = $this->installDir.dirname($filename);
			
			$this->log('Updating `'.$filename.'`!');
			
			if (!is_dir($foldername)) {
				if (!mkdir($foldername, $this->dirPermissions, true)) {
					$this->log('Could not create folder `'.$foldername.'`!');
				}
			}
			
			$contents = zip_entry_read($file, zip_entry_filesize($file));
			
			//Skip if entry is a directory
			if (substr($filename, -1, 1) == '/')
				continue;
			
			//Write to file
			if (file_exists($this->installDir.$filename)) {
				if (!is_writable($this->installDir.$filename)) {
					$this->log('Could not update `'.$this->installDir.$filename.'`, not writable!');
					return false;
				}
			} else {
				$this->log('The file `'.$this->installDir.$filename.'`, does not exist!');			
				$new_file = fopen($this->installDir.$filename, "w") or $this->log('The file `'.$this->installDir.$filename.'`, could not be created!');
				fclose($new_file);
				$this->log('The file `'.$this->installDir.$filename.'`, was succesfully created.');
			}
			
			$updateHandle = @fopen($this->installDir.$filename, 'w');
			
			if (!$updateHandle) {
				$this->log('Could not update file `'.$this->installDir.$filename.'`!');
				return false;
			}
			
			if (!fwrite($updateHandle, $contents)) {
				$this->log('Could not write to file `'.$this->installDir.$filename.'`!');
				return false;
			}
			
			fclose($updateHandle);
			
			//If file is a update script, include
			if ($filename == $this->updateScriptName) {
				$this->log('Try to include update script `'.$this->installDir.$filename.'`.');
				require($this->installDir.$filename);
				$this->log('Update script `'.$this->installDir.$filename.'` included!');
				unlink($this->installDir.$filename);
			}
		}
		
		zip_close($zip);
		
		if ($this->removeTempDir) {
			$this->log('Temporary directory `'.$this->tempDir.'` deleted.');
			$this->_removeDir($this->tempDir);
		}
		
		$this->log('Update `'.$this->latestVersion.'` installed.');
		
		return true;
	}
	
	
	/*
	 * 更新最新版本
	 */
	public function update() {
		//Check for latest version
		if ((is_null($this->latestVersion)) or (is_null($this->latestUpdate))) {
			$this->checkUpdate();
		}
		
		if ((is_null($this->latestVersion)) or (is_null($this->latestUpdate))) {
			return false;
		}
		
		//Update
		if ($this->latestVersion > $this->currentVersion) {
			$this->log('Updating...');
			
			//Add slash at the end of the path
			if ($this->tempDir[strlen($this->tempDir)-1] != '/');
				$this->tempDir = $this->tempDir.'/';
			
			if ((!is_dir($this->tempDir)) and (!mkdir($this->tempDir, 0777, true))) {
				$this->log('Temporary directory `'.$this->tempDir.'` does not exist and could not be created!');
				return false;
			}
			
			if (!is_writable($this->tempDir)) {
				$this->log('Temporary directory `'.$this->tempDir.'` is not writeable!');
				return false;
			}
			
			$updateFile = $this->tempDir.'/'.$this->latestVersion.'.zip';
			$updateUrl = $this->updateUrl.'/'.$this->latestVersion.'.zip';
			
			//Download update
			if (!is_file($updateFile)) {
				if (!$this->downloadUpdate($updateUrl, $updateFile)) {
					$this->log('无法下载更新!');
					return false;
				}
				
				$this->log('Latest update downloaded to `'.$updateFile.'`.');
			}
			else {
				$this->log('Latest update already downloaded to `'.$updateFile.'`.');
			}
			
			//Unzip
			return $this->install($updateFile);
		}
		else {
			$this->log('没有可用更新');
			return false;
		}
	}
}