<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesLanguage
{
    protected function compileIsset($expression): string
    {
        return $this->phpTag . "if(isset$expression): ?>";
    }

    protected function compileEndIsset(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    protected function compileEndEmpty(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Компилирует директиву @svg в PHP-код.
     *
     * @param string $expression Выражение, переданное в @svg
     * @return string Скомпилированный PHP-код
     */
    protected function compile_svg($expression): string
    {
        return $this->phpTag . "echo \$this->inlineSvg{$expression}; ?>";
    }
}