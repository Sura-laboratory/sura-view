<?php


namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesCommon
{
    /**
     * Show an error in the web.
     *
     * @param string $id          Title of the error
     * @param string $text        Message of the error
     * @param bool   $critic      if true then the compilation is ended, otherwise it continues
     * @param bool   $alwaysThrow if true then it always throws a runtime exception.
     * @return string
     * @throws \RuntimeException
     */
    public function showError($id, $text, $critic = false, $alwaysThrow = false): string
    {
        \ob_get_clean();
        if ($this->throwOnError || $alwaysThrow || $critic === true) {
            throw new \RuntimeException("View Error [$id] $text");
        }
        $msg = "<div style='background-color: red; color: black; padding: 3px; border: solid 1px black;'>";
        $msg .= "View Error [$id]:<br>";
        $msg .= "<span style='color:white'>$text</span><br></div>\n";
        echo $msg;
        if ($critic) {
            die(1);
        }
        return $msg;
    }

    /**
     * Escape HTML entities in a string.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function e($value): string
    {
        // Prevent "Deprecated: htmlentities(): Passing null to parameter #1 ($string) of type string is deprecated" message
        if (\is_null($value)) {
            return '';
        }
        if (\is_array($value) || \is_object($value)) {
            return \htmlentities(\print_r($value, true), ENT_QUOTES, 'UTF-8', false);
        }
        if (\is_numeric($value)) {
            $value = (string)$value;
        }
        return \htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    protected static function convertArgCallBack($k, $v): string
    {
        return $k . "='$v' ";
    }

    /**
     * @param mixed|\DateTime $variable
     * @param string|null     $format
     * @return string
     */
    public function format($variable, $format = null): string
    {
        if ($variable instanceof \DateTime) {
            $format = $format ?? 'Y/m/d';
            return $variable->format($format);
        }
        $format = $format ?? '%s';
        return sprintf($format, $variable);
    }

    /**
     * It converts a text into a php code with echo<br>
     * **Example:**<br>
     * ```
     * $this->wrapPHP('$hello'); // "< ?php echo $this->e($hello); ? >"
     * $this->wrapPHP('$hello',''); // < ?php echo $this->e($hello); ? >
     * $this->wrapPHP('$hello','',false); // < ?php echo $hello; ? >
     * $this->wrapPHP('"hello"'); // "< ?php echo $this->e("hello"); ? >"
     * $this->wrapPHP('hello()'); // "< ?php echo $this->e(hello()); ? >"
     * ```
     *
     * @param ?string $input The input value
     * @param string  $quote The quote used (to quote the result)
     * @param bool    $parse If the result will be parsed or not. If false then it's returned without $this->e
     * @return string
     */
    public function wrapPHP($input, $quote = '"', $parse = true): string
    {
        if ($input === null) {
            return 'null';
        }
        if (strpos($input, '(') !== false && !$this->isQuoted($input)) {
            if ($parse) {
                return $quote . $this->phpTagEcho . '$this->e(' . $input . ');?>' . $quote;
            }
            return $quote . $this->phpTagEcho . $input . ';?>' . $quote;
        }
        if (strpos($input, '$') === false) {
            if ($parse) {
                return self::enq($input);
            }
            return $input;
        }
        if ($parse) {
            return $quote . $this->phpTagEcho . '$this->e(' . $input . ');?>' . $quote;
        }
        return $quote . $this->phpTagEcho . $input . ';?>' . $quote;
    }

    /**
     * Returns true if the text is surrounded by quotes (double or single quote)
     *
     * @param string|null $text
     * @return bool
     */
    public function isQuoted($text): bool
    {
        if (!$text || strlen($text) < 2) {
            return false;
        }
        if ($text[0] === '"' && substr($text, -1) === '"') {
            return true;
        }
        return ($text[0] === "'" && substr($text, -1) === "'");
    }

    /**
     * Escape HTML entities in a string.
     *
     * @param string $value
     * @return string
     */
    public static function enq($value): string
    {
        if (\is_array($value) || \is_object($value)) {
            return \htmlentities(\print_r($value, true), ENT_NOQUOTES, 'UTF-8', false);
        }
        return \htmlentities($value ?? '', ENT_NOQUOTES, 'UTF-8', false);
    }

    /**
     * @param string      $view  example "folder.template"
     * @param string|null $alias example "mynewop". If null then it uses the name of the template.
     */
    public function addInclude($view, $alias = null): void
    {
        if (!isset($alias)) {
            $alias = \explode('.', $view);
            $alias = \end($alias);
        }
        $this->directive($alias, function($expression) use ($view) {
            $expression = $this->stripParentheses($expression) ?: '[]';
            return "$this->phpTag echo \$this->runChild('$view', $expression); ?>";
        });
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string   $name
     * @param callable $handler
     * @return void
     */
    public function directive($name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->customDirectivesRT[$name] = false;
    }

    /**
     * Strip the parentheses from the given expression.
     *
     * @param string|null $expression
     * @return string
     */
    public function stripParentheses($expression): string
    {
        if (\is_null($expression)) {
            return '';
        }
        if (static::startsWith($expression, '(')) {
            $expression = \substr($expression, 1, -1);
        }
        return $expression;
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '') {
                if (\function_exists('mb_strpos')) {
                    if ($haystack !== null && \mb_strpos($haystack, $needle) === 0) {
                        return true;
                    }
                } elseif ($haystack !== null && \strpos($haystack, $needle) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * If false then the file is not compiled, and it is executed directly from the memory.<br>
     * By default the value is true<br>
     * It also sets the mode to MODE_SLOW
     *
     * @param bool $bool
     * @return View
     * @see View::setMode
     */
    public function setIsCompiled($bool = false): View
    {
        $this->isCompiled = $bool;
        if (!$bool) {
            $this->setMode(self::MODE_SLOW);
        }
        return $this;
    }

    /**
     * It sets the template and compile path (without trailing slash).
     * <p>Example:setPath("somefolder","otherfolder");
     *
     * @param null|string|string[] $templatePath If null then it uses the current path /views folder
     * @param null|string          $compiledPath If null then it uses the current path /views folder
     */
    public function setPath($templatePath, $compiledPath): void
    {
        if ($templatePath === null) {
            $templatePath = \getcwd() . '/views';
        }
        if ($compiledPath === null) {
            $compiledPath = \getcwd() . '/compiles';
        }
        $this->templatePath = (is_array($templatePath)) ? $templatePath : [$templatePath];
        $this->compiledPath = $compiledPath;
    }

    /**
     * @return array
     */
    public function getAliasClasses(): array
    {
        return $this->aliasClasses;
    }

    /**
     * @param array $aliasClasses
     */
    public function setAliasClasses($aliasClasses): void
    {
        $this->aliasClasses = $aliasClasses;
    }

    /**
     * @param string $aliasName
     * @param string $classWithNS
     */
    public function addAliasClasses($aliasName, $classWithNS): void
    {
        $this->aliasClasses[$aliasName] = $classWithNS;
    }
}