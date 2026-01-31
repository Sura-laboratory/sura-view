<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesCli
{
    public static function isCli(): bool
    {
        return !http_response_code();
    }

    /**
     * @param           $key
     * @param string    $default  is the defalut value is the parameter is set
     *                            without value.
     * @param bool      $set      it is the value returned when the argument is set but there is no value assigned
     * @return string
     */
    public static function getParameterCli($key, $default = '', $set = true)
    {
        global $argv;
        $p = array_search('-' . $key, $argv, true);
        if ($p === false) {
            return $default;
        }
        if (isset($argv[$p + 1])) {
            return self::removeTrailSlash($argv[$p + 1]);
        }
        return $set;
    }

    protected static function removeTrailSlash($txt): string
    {
        return rtrim($txt, '/\\');
    }

    /**
     * @param string $str
     * @param string $type =['i','e','s','w'][$i]
     * @return string
     */
    public static function colorLog($str, $type = 'i'): string
    {
        return match ($type) {
            'e' => "\033[31m$str\033[0m",
            's' => "\033[32m$str\033[0m",
            'w' => "\033[33m$str\033[0m",
            'i' => "\033[36m$str\033[0m",
            'b' => "\e[01m$str\e[22m",
            default => $str,
        };
    }

    public function checkHealthPath(): bool
    {
        echo self::colorLog("Checking Health\n");
        $status = true;
        if (is_dir($this->compiledPath)) {
            echo "Compile-path [$this->compiledPath] is a folder " . self::colorLog("OK") . "\n";
        } else {
            $status = false;
            echo "Compile-path [$this->compiledPath] is not a folder " . self::colorLog("ERROR", 'e') . "\n";
        }
        foreach ($this->templatePath as $t) {
            if (is_dir($t)) {
                echo "Template-path (view) [$t] is a folder " . self::colorLog("OK") . "\n";
            } else {
                $status = false;
                echo "Template-path (view) [$t] is not a folder " . self::colorLog("ERROR", 'e') . "\n";
            }
        }
        $error = self::colorLog('OK');
        try {
            /** @noinspection RandomApiMigrationInspection */
            $rnd = $this->compiledPath . '/dummy' . rand(10000, 900009);
            $f = @file_put_contents($rnd, 'dummy');
            if ($f === false) {
                $status = false;
                $error = self::colorLog("Unable to create file [" . $this->compiledPath . '/dummy]', 'e');
            }
            @unlink($rnd);
        } catch (\Throwable $ex) {
            $status = false;
            $error = self::colorLog($ex->getMessage(), 'e');
        }
        echo "Testing write in the compile folder [$rnd] $error\n";
        $files = @glob($this->templatePath[0] . '/*');
        echo "Testing reading in the view folder [" . $this->templatePath[0] . "].\n";
        echo "View(s) found :" . count($files) . "\n";
        return $status;
    }

    public function createFolders(): void
    {
        echo self::colorLog("Creating Folder\n");
        echo "Creating compile folder[" . self::colorLog($this->compiledPath, 'b') . "] ";
        if (!\is_dir($this->compiledPath)) {
            $ok = @\mkdir($this->compiledPath, 0770, true);
            if ($ok === false) {
                echo self::colorLog("Error: Unable to create folder, check the permissions\n", 'e');
            } else {
                echo self::colorLog("OK\n");
            }
        } else {
            echo self::colorLog("Note: folder already exist.\n", 'w');
        }
        foreach ($this->templatePath as $t) {
            echo "Creating template folder [" . self::colorLog($t, 'b') . "] ";
            if (!\is_dir($t)) {
                $ok = @\mkdir($t, 0770, true);
                if ($ok === false) {
                    echo self::colorLog("Error: Unable to create folder, check the permissions\n", 'e');
                } else {
                    echo self::colorLog("OK\n");
                }
            } else {
                echo self::colorLog("Note: folder already exist.\n", 'w');
            }
        }
    }

    public function clearcompile(): int
    {
        echo self::colorLog("Clearing Compile Folder\n");
        $files = glob($this->compiledPath . '/*'); // get all file names
        $count = 0;
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                $count++;
                echo "deleting [$file] ";
                $r = @unlink($file); // delete file
                if ($r) {
                    echo self::colorLog("OK\n");
                } else {
                    echo self::colorLog("ERROR\n", 'e');
                }
            }
        }
        echo "Files deleted $count\n";
        return $count;
    }

    public function cliEngine(): void
    {
        $clearcompile = self::getParameterCli('clearcompile');
        $createfolder = self::getParameterCli('createfolder');
        $check = self::getParameterCli('check');
        echo '  ___      ___ __    ___      ___            ' . "\n";
        echo '  \  \    /  /|__|   \  \    /  /            ' . "\n";
        echo '   \  \  /  /  __  ___\  \  /  /             ' . "\n";
        echo '    \  \/  /  |  |/ _ \\  \/  /              ' . "\n";
        echo '     \    /   |  |  __/ \    /               ' . "\n";
        echo '      \__/    |__|\___|  \__/' . " V." . self::VERSION . "\n\n";
        echo "\n";
        $done = false;
        if ($check) {
            $done = true;
            $this->checkHealthPath();
        }
        if ($clearcompile) {
            $done = true;
            $this->clearcompile();
        }
        if ($createfolder) {
            $done = true;
            $this->createFolders();
        }
        if (!$done) {
            echo " Syntax:\n";
            echo " " . self::colorLog("-templatepath", "b") . " <templatepath> (optional) the template-path (view path).\n";
            echo "    Default value: 'views'\n";
            echo "    Example: 'php /vendor/bin/suravievcli /folder/views' (absolute)\n";
            echo "    Example: 'php /vendor/bin/suravievcli folder/view1' (relative)\n";
            echo " " . self::colorLog("-compilepath", "b") . " <compilepath>  (optional) the compile-path.\n";
            echo "    Default value: 'compiles'\n";
            echo "    Example: 'php /vendor/bin/suravievcli /folder/compiles' (absolute)\n";
            echo "    Example: 'php /vendor/bin/suravievcli compiles' (relative)\n";
            echo " " . self::colorLog("-createfolder", "b") . " it creates the folders if they don't exist.\n";
            echo "    Example: php ./vendor/bin/suravievcli -createfolder\n";
            echo " " . self::colorLog("-clearcompile", "b") . " It deletes the content of the compile path\n";
            echo " " . self::colorLog("-check", "b") . " It checks the folders and permissions\n";
        }
    }

    public static function isAbsolutePath($path): bool
    {
        if (!$path) {
            return true;
        }
        if (DIRECTORY_SEPARATOR === '/') {
            // linux and macos
            return $path[0] === '/';
        }
        return $path[1] === ':';
    }
}