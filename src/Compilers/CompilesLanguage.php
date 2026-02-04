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
    /**
     * Компилирует @isset(...)
     */
    protected function compileIsset($expression): string
    {
        return $this->phpTag . "if(isset$expression): ?>";
    }

    /**
     * Завершает блок @isset
     */
    protected function compileEndIsset(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Завершает блок @empty
     */
    protected function compileEndEmpty(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Компилирует @assets(...)
     *
     * Примеры:
     *   @assets('css/app.css')
     *   @assets(['css/app.css', 'js/app.js'])
     *   @assets($assetList)
     *
     * Генерирует: echo assets('css/app.css');
     * или: echo assets(['css/app.css', 'js/app.js']);
     *
     * @param string $expression
     * @return string
     */
    protected function compileAssets($expression): string
    {
        return $this->phpTag . "echo assets$expression; ?>";
    }    
}