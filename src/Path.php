<?php
/**
 * JBZoo Path
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package   Path
 * @license   MIT
 * @copyright Copyright (C) JBZoo.com,  All rights reserved.
 * @link      https://github.com/JBZoo/Path
 * @author    Sergey Kalistratov <kalistratov.s.m@gmail.com>
 */

namespace JBZoo\Path;

use JBZoo\Utils\FS;
use JBZoo\Utils\Url;

/**
 * Class Path
 * @package JBZoo\Path
 */
class Path
{

    /**
     * Default alias name.
     *
     * @var string
     */
    const DEFAULT_ALIAS = 'default';

    /**
     * Minimal alias name length.
     *
     * @var string
     */
    const MIN_ALIAS_LENGTH = 3;

    /**
     * Mod prepend rule add paths.
     *
     * @var string
     */
    const MOD_PREPEND = 'prepend';

    /**
     * Mod append rule add paths.
     *
     * @var string
     */
    const MOD_APPEND = 'append';

    /**
     * Reset all registered paths.
     *
     * @var bool
     */
    const MOD_RESET = true;

    /**
     * Holds object instance.
     *
     * @var array
     */
    protected static $_objects = array();

    /**
     * Holds paths list.
     *
     * @var array
     */
    protected $_paths = array();

    /**
     * Hold root dir.
     *
     * @var string
     */
    protected $_root;

    /**
     * Register alias locations in file system.
     *
     * @param string|array $paths
     *
     * (Example:
     * "default:file.txt" - if added at least one path and
     * "C:\server\test.dev\fy-folder" or "C:\server\test.dev\fy-folder\..\..\")
     *
     * @param string $alias
     * @param string|bool $mode
     * @throws Exception
     */
    public function add($paths, $alias = Path::DEFAULT_ALIAS, $mode = Path::MOD_PREPEND)
    {
        $paths = (array) $paths;

        if (strlen($alias) < Path::MIN_ALIAS_LENGTH) {
            throw new Exception(sprintf('The minimum number of characters is %s', Path::MIN_ALIAS_LENGTH));
        }

        if ($this->_reset($paths, $alias, $mode)) {
            return;
        }

        foreach ($paths as $path) {
            if (!isset($this->_paths[$alias])) {
                $this->_paths[$alias] = array();
            }

            $path = FS::clean($path, '/');
            if (!in_array($path, $this->_paths[$alias], true)) {
                $this->_add($path, $alias, $mode);
            }
        }
    }

    /**
     * Normalize and clean path.
     *
     * @param string $path ("C:\server\test.dev\file.txt")
     * @return string
     */
    public function clean($path)
    {
        $tokens = array();
        $path   = FS::clean($path, '/');
        $prefix = $this->prefix($path);
        $path   = substr($path, strlen($prefix));
        $parts  = array_filter(explode('/', $path), 'strlen');

        foreach ($parts as $part) {
            if ('..' === $part) {
                array_pop($tokens);
            } elseif ('.' !== $part) {
                array_push($tokens, $part);
            }
        }

        return $prefix . implode('/', $tokens);
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return null|string
     */
    public function get($source)
    {
        list(, $paths, $path) = $this->_parse($source);
        return $this->_find($paths, $path);
    }

    /**
     * Get path instance.
     *
     * @param string $key
     * @return \JBZoo\Path\Path
     */
    public static function getInstance($key = 'default')
    {
        if (!isset(self::$_objects[$key])) {
            self::$_objects[$key] = new self($key);
        }

        return self::$_objects[$key];
    }

    /**
     * Get instance keys.
     *
     * @return array
     */
    public function getInstanceKeys()
    {
        return array_keys(self::$_objects);
    }

    /**
     * Get all absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return mixed
     */
    public function getPaths($source)
    {
        list(, $paths) = $this->_parse($source);
        return $paths;
    }

    /**
     * Get root directory.
     *
     * @return mixed
     * @throws Exception
     */
    public function getRoot()
    {
        $this->_checkRoot();
        return $this->_root;
    }

    /**
     * Check virtual or real path.
     *
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return bool
     */
    public function isVirtual($path)
    {
        $parts = explode(':', $path, 2);

        list($alias) = $parts;
        if ($this->prefix($path) !== null && !array_key_exists($alias, $this->_paths)) {
            return false;
        }

        return (count($parts) == 2) ? true : false;
    }

    /**
     * Get path prefix.
     *
     * @param string $path (example: "C:\server\test.dev\file.txt")
     * @return null
     */
    public function prefix($path)
    {
        $path = FS::clean($path, '/');
        return preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }

    /**
     * Remove path from registered paths.
     *
     * @param $source (example: "default:file.txt")
     * @param string|array $key
     * @return bool
     */
    public function remove($source, $key)
    {
        $keys = (array) $key;
        list($alias) = $this->_parse($source);

        $return = false;
        if ($this->_isDeleted($alias, $keys)) {
            foreach ($keys as $key) {
                $key = (int) $key;
                if (array_key_exists($key, $this->_paths[$alias])) {
                    unset($this->_paths[$alias][$key]);
                    $return = true;
                }
            }
        }

        return $return;
    }

    /**
     * Setup root directory.
     *
     * @param string $dir
     * @throws Exception
     */
    public function setRoot($dir)
    {
        if (!is_dir($dir)) {
            throw new Exception(sprintf('Not found directory: %s', $dir));
        }

        if (!isset($this->_root)) {
            $this->_root = FS::clean($dir, '/');
        }
    }

    /**
     * Get uri to a file.
     *
     * @param string $source (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return null|string
     */
    public function uri($source)
    {
        $details = explode('?', $source);
        $path    = $details[0];
        $path    = $this->_getAddPath($path, '/');
        $path    = $this->urn($path, true);

        if (!empty($path)) {
            if (isset($details[1])) {
                $path .= '?' . $details[1];
            }

            return Url::current() . $path;
        }

        return null;
    }

    /**
     * Get urn path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param bool $exitsFile
     * @return string
     * @throws Exception
     */
    public function urn($path, $exitsFile = false)
    {
        $this->_checkRoot();

        $root    = preg_quote($this->_root, '/');
        $path    = $this->_getAddPath($path, '/');
        $subject = $path;
        $pattern = '/^' . $root . '/i';

        if ($exitsFile && !$this->isVirtual($path) && !file_exists($path)) {
            $subject = null;
        }

        return ltrim(preg_replace($pattern, '', $subject), '/');
    }

    /**
     * Path constructor.
     *
     * @param string $key
     * @throws Exception
     */
    protected function __construct($key = 'default')
    {
        if (empty($key)) {
            throw new Exception('Invalid object key');
        }

        static::$_objects[$key] = $key;
    }

    /**
     * Add path to hold.
     *
     * @param string|array $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $alias
     * @param string|bool $mode
     * @return void
     */
    protected function _add($path, $alias, $mode)
    {
        $path = $this->_getAddPath($path, '/');
        if ($path !== null) {
            if ($mode == self::MOD_PREPEND) {
                array_unshift($this->_paths[$alias], $path);
            }

            if ($mode == self::MOD_APPEND) {
                array_push($this->_paths[$alias], $path);
            }
        }
    }

    /**
     * Check root directory.
     *
     * @throws Exception
     */
    protected function _checkRoot()
    {
        if ($this->_root === null) {
            throw new Exception(sprintf('Please, set the root directory'));
        }
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param string|array $paths
     * @param string $file
     * @return null|string
     */
    protected function _find($paths, $file)
    {
        $paths = (array) $paths;
        $file  = ltrim($file, "\\/");

        foreach ($paths as $path) {
            $fullPath = $this->clean($path . '/' . $file);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Get add path.
     *
     * @param $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param        $path
     * @param string $dirSep
     * @return null|string
     */
    protected function _getAddPath($path, $dirSep = DIRECTORY_SEPARATOR)
    {
        if ($this->isVirtual($path)) {
            return $this->get($path);
        }

        if ($this->_hasCDBack($path)) {
            return (realpath($path)) ? realpath(FS::clean($path, '/')) : null;
        }

        return FS::clean($path, $dirSep);
    }

    /**
     * Check has back current.
     *
     * @param $path
     * @return int
     */
    protected function _hasCDBack($path)
    {
        $path = FS::clean($path, '/');
        return preg_match('(/\.\.$|/\.\./$)', $path);
    }

    /**
     * Checking the possibility of removing the path.
     *
     * @param string $alias
     * @param array $keys
     * @return bool
     */
    protected function _isDeleted($alias, $keys)
    {
        if (isset($this->_paths[$alias]) && is_array($this->_paths[$alias]) && !empty($keys)) {
            return true;
        }

        return false;
    }

    /**
     * Parse source string.
     *
     * @param string $source (example: "default:file.txt")
     * @param string $alias
     * @return array
     */
    protected function _parse($source, $alias = Path::DEFAULT_ALIAS)
    {
        $path  = null;
        $parts = explode(':', $source, 2);
        $count = count($parts);

        if ($count == 1) {
            list($path) = $parts;
        } elseif ($count == 2) {
            list($alias, $path) = $parts;
        }

        $path  = ltrim($path, "\\/");
        $paths = isset($this->_paths[$alias]) ? $this->_paths[$alias] : array();

        return array($alias, $paths, $path);
    }

    /**
     * Reset added paths.
     *
     * @param array $paths (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param $alias
     * @param $mode
     * @return bool
     */
    protected function _reset($paths, $alias, $mode)
    {
        if ($mode === self::MOD_RESET) {
            $this->_paths[$alias] = array();
            foreach ($paths as $path) {
                $this->_paths[$alias][] = FS::clean($path, '/');
            }

            return true;
        }

        return false;
    }
}
