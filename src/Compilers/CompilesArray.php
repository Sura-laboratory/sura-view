<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesArray
{
    /**
     * @error('key')
     *
     * @param $expression
     * @return string
     */
    protected function compileError($expression): string
    {
        $key = $this->stripParentheses($expression);
        return $this->phpTag . '$message = call_user_func($this->errorCallBack,' . $key . '); if ($message): ?>';
    }

    /**
     * Compile the end-error statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndError(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @return string
     */
    protected function compileElse(): string
    {
        return $this->phpTag . 'else: ?>';
    }

    /**
     * Compile the for statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileFor($expression): string
    {
        return $this->phpTag . "for$expression: ?>";
    }
}