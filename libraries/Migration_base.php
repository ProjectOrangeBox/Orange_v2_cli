<?php

class Migration_base {
	protected $dbforge;
	protected $_error_string = '';
	protected $console;

	public function __construct() {
		/* we will probbly need this */
		ci()->load->helper('file');

		/* and this */
		$this->dbforge = &ci()->dbforge;

		require_once __DIR__.'/Console.php';

		$this->console = new console;
	}

	/**
	 * Error string
	 *
	 * @return	string	Error message returned as a string
	 */
	public function error_string() {
		return $this->_error_string;
	}

	/* wrapper */
	public function up() {
		return true;
	}

	/* wrapper */
	public function down() {
		return true;
	}

	protected function e($output) {
		if (is_cli()) {
			echo $output.chr(10);
		}
	}

	protected function _get_package() {
		$child = debug_backtrace(null,3);
		
		return strip_rp(dirname(dirname(dirname($child[1]['file']))));
	}

	protected function _copy_config($filename) {
		$filename = trim($filename,'/');

		$package_folder = $this->_get_package();
		$package_config = $package_folder.'/'.$filename;
		$config_file = 'config/'.basename($filename);

		$success = true;

		if (file_exists(ROOTPATH.$package_config)) {
			if (is_writable(APPPATH.'config')) {
				$success = copy(ROOTPATH.$package_config,APPPATH.$config_file);
			} else {
				show_error('Can not write to "'.APPPATH.'config"');
			}
		} else {
			show_error('Could not locate the specified config file "'.$package_config.'"');
		}

		return $success;
	}

	protected function _unlink_config($filename) {
		$name = basename($filename,'.php');
		$config_file = 'config/'.$name.'.php';

		$success     = true;

		if (file_exists(APPPATH.$config_file)) {
			$success = unlink(APPPATH.$config_file);
		}

		return $success;
	}

	protected function _link_public($path) {
		$package_folder = ROOTPATH.$this->_get_package().'/public/'.ltrim($path,'/');
		$public_folder = WWW.'/'.ltrim($path,'/');

		/* let's make the public path if it's not there */
		$drop_folder = dirname($public_folder);

		if (!is_dir($drop_folder)) {
			mkdir($drop_folder, 0777, true);
		}

		/* remove the link/file if it's there */
		@unlink($public_folder);

		if (!$this->_relative_symlink($package_folder, $public_folder)) {
			show_error('Could not symlink package folder to public location.');
		}

		return true;
	}

	protected function _unlink_public($path) {
		$public_folder = WWW.'/'.ltrim($path,'/');

		return unlink($public_folder);
	}
	
	/* these are only added to the var folder */
	protected function _add_rw_folder($path) {
		$var_folder = dirname(path('{rootpath}{uploads}'));

		return (is_writable($var_folder)) ? mkdir($var_folder.'/'.rtrim($path,'/'),0777,true) : false;
	}

	/* these are only removed from the var folder */
	protected function _remove_rw_folder($path) {
		$var_folder = dirname(path('{rootpath}{uploads}'));
	
		return $this->_rmdirr($var_folder.'/'.rtrim($path,'/'));
	}

	protected function _rmdirr($directory) {
		/* checks */
		if (!is_string($directory) || empty($directory)) {
			return $false;
		}
		if (!is_dir($directory) || is_link($directory)) {
			return unlink($directory);
		}

		foreach (scandir($directory) as $file) {
			if ($file == '.' || $file == '..') {
				continue;
			}

			if (!$this->_rmdirr($directory . '/' . $file)) {
				chmod($directory . '/' . $file, 0777);

				if (!$this->_rmdirr($directory . '/' . $file)) {
					return false;
				}
			};
		}

		/* finally the folder */
		return rmdir($directory);
	}

	protected function _describe_table($tablename, $database_config = 'default') {
		$db = ci()->load->database($database_config, true);

		$fields = false;

		if ($db->table_exists($tablename)) {
			$fields = (array)$db->list_fields($tablename);
		}

		return $fields;
	}

	protected function _db_has_column($column, $tablename, $database_config = 'default') {
		$columns = $this->_describe_table($tablename, $database_config);

		return (is_array($columns)) ? in_array($column,$columns) : false;
	}

	protected function _find_n_replace($file_path, $find, $replace) {
		$success = false;

		if (file_exists($file_path)) {
			$contents = file_get_contents($file_path);

			$contents = str_replace($find, $replace, $contents);

			$success = file_put_contents($file_path, $contents);
		}

		return $success;
	}

	/* target = file target / link = name */
	protected function _relative_symlink($target,$link) {
		/* remove the link that might be there */

		$link = strip_rp($link);
		$target = strip_rp($target);

		/* remove it if it's already there */
		@unlink(ROOTPATH.$link);

		/* create it */
		return symlink(ROOTPATH.$target,ROOTPATH.$link);
	}

	protected function _getRelativePath($from_path, $to_path) {
		/* some compatibility fixes for Windows paths */
		$from_path = is_dir($from_path) ? rtrim($from_path, '\/') . '/' : $from_path;
		$to_path   = is_dir($to_path) ? rtrim($to_path, '\/') . '/' : $to_path;

		$from_path = str_replace('\\', '/', $from_path);
		$to_path   = str_replace('\\', '/', $to_path);

		$from_path    = explode('/', $from_path);
		$to_path      = explode('/', $to_path);

		$relPath = $to_path;

		foreach ($from_path as $depth => $dir) {
			// find first non-matching dir
			if ($dir === $to_path[$depth]) {
				// ignore this directory
				array_shift($relPath);
			} else {
				// get number of remaining dirs to $from_path
				$remaining = count($from_path) - $depth;

				if ($remaining > 1) {
					// add traversals up to first matching dir
					$padLength = (count($relPath) + $remaining - 1) * -1;
					$relPath   = array_pad($relPath, $padLength, '..');
					break;
				} else {
					$relPath[0] = './' . $relPath[0];
				}
			}
		}

		return implode('/', $relPath);
	}

} /*end class */