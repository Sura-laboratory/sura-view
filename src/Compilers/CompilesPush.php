<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesPush
{
    /**
     * Get the entire loop stack.
     *
     * @return array
     */
    public function getLoopStack(): array
    {
        return $this->loopsStack;
    }

    /**
     * It adds a string inside a quoted string<br>
     * **example:**<br>
     * ```
     * $this->addInsideQuote("'hello'"," world"); // 'hello world'
     * $this->addInsideQuote("hello"," world"); // hello world
     * ```
     *
     * @param $quoted
     * @param $newFragment
     * @return string
     */
    public function addInsideQuote($quoted, $newFragment): string
    {
        if ($this->isQuoted($quoted)) {
            return substr($quoted, 0, -1) . $newFragment . substr($quoted, -1);
        }
        return $quoted . $newFragment;
    }

    /**
     * Return true if the string is a php variable (it starts with $)
     *
     * @param string|null $text
     * @return bool
     */
    public function isVariablePHP($text): bool
    {
        if (!$text || strlen($text) < 2) {
            return false;
        }
        return $text[0] === '$';
    }

    /**
     * It's the same as "@_e", however it parses the text (using sprintf).
     * If the operation fails then, it returns the original expression without translation.
     *
     * @param $phrase
     *
     * @return string
     */
    public function _ef($phrase): string
    {
        $argv = \func_get_args();
        $r = $this->_e($phrase);
        $argv[0] = $r; // replace the first argument with the translation.
        $result = @sprintf(...$argv);
        return !$result ? $r : $result;
    }

    /**
     * Tries to translate the word if it's in the array defined by ViewLang::$dictionary
     * If the operation fails then, it returns the original expression without translation.
     *
     * @param $phrase
     *
     * @return string
     */
    public function _e($phrase): string
    {
        if ((!\array_key_exists($phrase, static::$dictionary))) {
            $this->missingTranslation($phrase);
            return $phrase;
        }
        return static::$dictionary[$phrase];
    }

    /**
     * Log a missing translation into the file $this->missingLog.<br>
     * If the file is not defined, then it doesn't write the log.
     *
     * @param string $txt Message to write on.
     */
    protected function missingTranslation($txt): void
    {
        if (!$this->missingLog) {
            return; // if there is not a file assigned then it skips saving.
        }
        $fz = @\filesize($this->missingLog);
        if (\is_object($txt) || \is_array($txt)) {
            $txt = \print_r($txt, true);
        }
        // Rewrite file if more than 100000 bytes
        $mode = ($fz > 100000) ? 'w' : 'a';
        $fp = \fopen($this->missingLog, $mode);
        \fwrite($fp, $txt . "\n");
        \fclose($fp);
    }

    /**
     * if num is more than one then it returns the phrase in plural, otherwise the phrase in singular.
     * Note: the translation should be as follows: $msg['Person']='Person' $msg=['Person']['p']='People'
     *
     * @param string $phrase
     * @param string $phrases
     * @param int    $num
     *
     * @return string
     */
    public function _n($phrase, $phrases, $num = 0): string
    {
        if ((!\array_key_exists($phrase, static::$dictionary))) {
            $this->missingTranslation($phrase);
            return ($num <= 1) ? $phrase : $phrases;
        }
        return ($num <= 1) ? $this->_e($phrase) : $this->_e($phrases);
    }

    /**
     * @param $expression
     * @return string
     * @see View::getCanonicalUrl
     */
    public function compileCanonical($expression = null): string
    {
        return '<link rel="canonical" href="' . $this->phpTag
            . ' echo $this->getCanonicalUrl();?>" />';
    }

    /**
     * @param $expression
     * @return string
     * @see View::getBaseUrl
     */
    public function compileBase($expression = null): string
    {
        return '<base rel="canonical" href="' . $this->phpTag
            . ' echo $this->getBaseUrl() ;?>" />';
    }

    protected function compileUse($expression): string
    {
        return $this->phpTag . 'use ' . $this->stripParentheses($expression) . '; ?>';
    }

    protected function compileSwitch($expression): string
    {
        $this->switchCount++;
        $this->firstCaseInSwitch = true;
        return $this->phpTag . "switch $expression {";
    }
}