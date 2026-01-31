<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesFile
{
    protected function compileCannot($expression): string
    {
        $v = $this->stripParentheses($expression);
        return $this->phpTag . 'if (!call_user_func($this->authCallBack,' . $v . ')): ?>';
    }

    /**
     * Compile the elsecannot statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileElseCannot($expression = ''): string
    {
        $v = $this->stripParentheses($expression);
        if ($v) {
            return $this->phpTag . 'elseif (!call_user_func($this->authCallBack,' . $v . ')): ?>';
        }
        return $this->phpTag . 'else: ?>';
    }

    /**
     * Compile the canany statements into valid PHP.
     * canany(['edit','write'])
     *
     * @param $expression
     * @return string
     */
    protected function compileCanAny($expression): string
    {
        $role = $this->stripParentheses($expression);
        return $this->phpTag . 'if (call_user_func($this->authAnyCallBack,' . $role . ')): ?>';
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @param $expression
     * @return string
     */
    protected function compileElseCanAny($expression): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'else: ?>';
        }
        return $this->phpTag . 'elseif (call_user_func($this->authAnyCallBack,' . $role . ')): ?>';
    }

    /**
     * Compile the guest statements into valid PHP.
     *
     * @param null $expression
     * @return string
     */
    protected function compileGuest($expression = null): string
    {
        if ($expression === null) {
            return $this->phpTag . 'if(!isset($this->currentUser)): ?>';
        }
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'if(!isset($this->currentUser)): ?>';
        }
        return $this->phpTag . "if(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @param $expression
     * @return string
     */
    protected function compileElseGuest($expression): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'else: ?>';
        }
        return $this->phpTag . "elseif(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * /**
     * Compile the end-auth statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndGuest(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the end-section statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndsection(): string
    {
        return $this->phpTag . '$this->stopSection(); ?>';
    }

    /**
     * Compile the stop statements into valid PHP.
     *
     * @return string
     */
    protected function compileStop(): string
    {
        return $this->phpTag . '$this->stopSection(); ?>';
    }

    /**
     * Compile the overwrite statements into valid PHP.
     *
     * @return string
     */
    protected function compileOverwrite(): string
    {
        return $this->phpTag . '$this->stopSection(true); ?>';
    }

    /**
     * Compile the unless statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileUnless($expression): string
    {
        return $this->phpTag . "if ( ! $expression): ?>";
    }

    /**
     * Compile the User statements into valid PHP.
     *
     * @return string
     */
    protected function compileUser(): string
    {
        return $this->phpTagEcho . "'" . $this->currentUser . "'; ?>";
    }

    /**
     * Compile the endunless statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndunless(): string
    {
        return $this->phpTag . 'endif; ?>';
    }
}