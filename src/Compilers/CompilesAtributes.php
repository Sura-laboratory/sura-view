<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesAtributes
{
    /**
     * Compile the checked statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileChecked($expression): string
    {
        return $this->phpTag . "if$expression echo 'checked'; ?>";
    }

    protected function compileStyle($expression): string
    {
        return $this->phpTag . "echo 'class=\"'.\$this->runtimeStyle($expression).'\"' ?>";
    }

    protected function compileClass($expression): string
    {
        return $this->phpTag . "echo 'class=\"'.\$this->runtimeStyle($expression).'\"'; ?>";
    }

    protected function runtimeStyle($expression = null, $separator = ' '): string
    {
        if ($expression === null) {
            return '';
        }
        if (!is_array($expression)) {
            $expression = [$expression];
        }
        $result = '';
        foreach ($expression as $k => $v) {
            if (is_numeric($k)) {
                $result .= $v . $separator;
            } elseif ($v) {
                $result .= $k . $separator;
            }
        }
        return trim($result);
    }

    /**
     * Compile the selected statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSelected($expression): string
    {
        return $this->phpTag . "if$expression echo 'selected'; ?>";
    }

    /**
     * Compile the disabled statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileDisabled($expression): string
    {
        return $this->phpTag . "if$expression echo 'disabled'; ?>";
    }

    /**
     * Compile the readonly statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileReadonly($expression): string
    {
        return $this->phpTag . "if$expression echo 'readonly'; ?>";
    }

    /**
     * Compile the required statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileRequired($expression): string
    {
        return $this->phpTag . "if$expression echo 'required'; ?>";
    }
}