<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesSetGet
{
    /**
     * Compile end-php statement into valid PHP.
     *
     * @return string
     */
    protected function compileEndphp(): string
    {
        return ' ?>';
    }

    /**
     * Compile the unset statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileUnset($expression): string
    {
        return $this->phpTag . "unset$expression; ?>";
    }

    /**
     * Compile the extends statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileExtends($expression): string
    {
        $expression = $this->stripParentheses($expression);
        // $_shouldextend avoids to runchild if it's not evaluated.
        // For example @if(something) @extends('aaa.bb') @endif()
        // If something is false then it's not rendered at the end (footer) of the script.
        $this->uidCounter++;
        $data = $this->phpTag . 'if (isset($_shouldextend[' . $this->uidCounter . '])) { echo $this->runChild(' . $expression . '); } ?>';
        $this->footer[] = $data;
        return $this->phpTag . '$_shouldextend[' . $this->uidCounter . ']=1; ?>';
    }

    /**
     * Execute the @parent command. This operation works in tandem with extendSection
     *
     * @return string
     * @see extendSection
     */
    protected function compileParent(): string
    {
        return $this->PARENTKEY;
    }

    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileInclude($expression): string
    {
        $expression = $this->stripParentheses($expression);
        return $this->phpTagEcho . '$this->runChild(' . $expression . '); ?>';
    }

    /**
     * It loads a compiled template and paste inside the code.<br>
     * It uses more disk space, but it decreases the number of includes<br>
     *
     * @param $expression
     * @return string
     * @throws Exception
     */
    protected function compileIncludeFast($expression): string
    {
        $expression = $this->stripParentheses($expression);
        $ex = $this->stripParentheses($expression);
        $exp = \explode(',', $ex);
        $file = $this->stripQuotes($exp[0] ?? null);
        $fileC = $this->getCompiledFile($file);
        if (!@\is_file($fileC)) {
            // if the file doesn't exist then it's created
            $this->compile($file, true);
        }
        return $this->getFile($fileC);
    }

    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileIncludeIf($expression): string
    {
        return $this->phpTag . 'if ($this->templateExist' . $expression . ') echo $this->runChild' . $expression . '; ?>';
    }

    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileIncludeWhen($expression): string
    {
        $expression = $this->stripParentheses($expression);
        return $this->phpTagEcho . '$this->includeWhen(' . $expression . '); ?>';
    }

    /**
     * Compile the includefirst statement
     *
     * @param string $expression
     * @return string
     */
    protected function compileIncludeFirst($expression): string
    {
        $expression = $this->stripParentheses($expression);
        return $this->phpTagEcho . '$this->includeFirst(' . $expression . '); ?>';
    }

    /**
     * Compile the {@}compilestamp statement.
     *
     * @param string $expression
     *
     * @return false|string
     */
    protected function compileCompileStamp($expression)
    {
        $expression = $this->stripQuotes($this->stripParentheses($expression));
        $expression = ($expression === '') ? 'Y-m-d H:i:s' : $expression;
        return date($expression);
    }

    /**
     * compile the {@}viewname statement<br>
     * {@}viewname('compiled') returns the full compiled path
     * {@}viewname('template') returns the full template path
     * {@}viewname('') returns the view name.
     *
     * @param mixed $expression
     *
     * @return string
     */
    protected function compileViewName($expression): string
    {
        $expression = $this->stripQuotes($this->stripParentheses($expression));
        switch ($expression) {
            case 'compiled':
                return $this->getCompiledFile($this->fileName);
            case 'template':
                return $this->getTemplateFile($this->fileName);
            default:
                return $this->fileName;
        }
    }

    /**
     * Compile the stack statements into the content.
     *
     * @param string $expression
     * @return string
     * @see View::yieldPushContent
     */
    protected function compileStack($expression): string
    {
        return $this->phpTagEcho . " \$this->CompileStackFinal$expression; ?>";
    }

    public function CompileStackFinal($a = null, $b = null): string
    {
        return $this->escapeStack0 . $a . ',' . $b . $this->escapeStack1;
    }

    /**
     * Compile the endpush statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpush(): string
    {
        return $this->phpTag . '$this->stopPush(); ?>';
    }

    /**
     * Compile the endpushonce statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpushOnce(): string
    {
        return $this->phpTag . '$this->stopPush(); endif; ?>';
    }

    /**
     * Compile the endpush statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndPrepend(): string
    {
        return $this->phpTag . '$this->stopPrepend(); ?>';
    }

    /**
     * Compile the component statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileComponent($expression): string
    {
        return $this->phpTag . " \$this->startComponent$expression; ?>";
    }

    /**
     * Compile the end-component statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndComponent(): string
    {
        return $this->phpTagEcho . '$this->renderComponent(); ?>';
    }

    /**
     * Compile the slot statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSlot($expression): string
    {
        return $this->phpTag . " \$this->slot$expression; ?>";
    }

    /**
     * Compile the end-slot statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndSlot(): string
    {
        return $this->phpTag . ' $this->endSlot(); ?>';
    }

    protected function compileAsset($expression): string
    {
        return $this->phpTagEcho . "(isset(\$this->assetDict[$expression]))?\$this->assetDict[$expression]:\$this->baseUrl.'/'.$expression; ?>";
    }

    protected function compileAssetCDN($expression): string
    {
        return $this->phpTagEcho . "(isset(\$this->assetDictCDN[$expression]))?\$this->assetDictCDN[$expression]:\$this->cdnUrl.'/'.$expression; ?>";
    }

    protected function compileJSon($expression): string
    {
        $parts = \explode(',', $this->stripParentheses($expression));
        $options = isset($parts[1]) ? \trim($parts[1]) : JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $depth = isset($parts[2]) ? \trim($parts[2]) : 512;
        return $this->phpTagEcho . "json_encode($parts[0], $options, $depth); ?>";
    }
}