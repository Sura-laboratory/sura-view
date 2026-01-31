<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesExtras
{
    protected function compileDump($expression): string
    {
        return $this->phpTagEcho . "\$this->dump$expression;?>";
    }

    protected function compileRelative($expression): string
    {
        return $this->phpTagEcho . "\$this->relative$expression;?>";
    }

    protected function compileMethod($expression): string
    {
        $v = $this->stripParentheses($expression);
        return "<input type='hidden' name='_method' value='{$this->phpTag}echo $v; " . "?>'/>";
    }

    protected function compilecsrf($expression = null): string
    {
        $expression = $expression ?? "'_token'";
        return "<input type='hidden' name='$this->phpTag echo $expression; ?>' value='{$this->phpTag}echo \$this->csrf_token; " . "?>'/>";
    }

    protected function compileDd($expression): string
    {
        return $this->phpTagEcho . "'<pre>'; var_dump$expression; echo '</pre>';?>";
    }

    /**
     * Execute the case tag.
     *
     * @param $expression
     * @return string
     */
    protected function compileCase($expression): string
    {
        if ($this->firstCaseInSwitch) {
            $this->firstCaseInSwitch = false;
            return 'case ' . $expression . ': ?>';
        }
        return $this->phpTag . "case $expression: ?>";
    }

    /**
     * Compile the while statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileWhile($expression): string
    {
        return $this->phpTag . "while$expression: ?>";
    }

    /**
     * default tag used for switch/case
     *
     * @return string
     */
    protected function compileDefault(): string
    {
        if ($this->firstCaseInSwitch) {
            return $this->showError('@default', '@switch without any @case', true);
        }
        return $this->phpTag . 'default: ?>';
    }

    protected function compileEndSwitch(): string
    {
        --$this->switchCount;
        if ($this->switchCount < 0) {
            return $this->showError('@endswitch', 'Missing @switch', true);
        }
        return $this->phpTag . '} // end switch ?>';
    }

    /**
     * Compile while statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileInject($expression): string
    {
        $ex = $this->stripParentheses($expression);
        $p0 = \strpos($ex, ',');
        if (!$p0) {
            $var = $this->stripQuotes($ex);
            $namespace = '';
        } else {
            $var = $this->stripQuotes(\substr($ex, 0, $p0));
            $namespace = $this->stripQuotes(\substr($ex, $p0 + 1));
        }
        return $this->phpTag . "\$$var = \$this->injectClass('$namespace', '$var'); ?>";
    }

    /**
     * Remove first and end quote from a quoted string of text
     *
     * @param mixed $text
     * @return null|string|string[]
     */
    public function stripQuotes($text)
    {
        if (!$text || strlen($text) < 2) {
            return $text;
        }
        $text = trim($text);
        $p0 = $text[0];
        $p1 = \substr($text, -1);
        if ($p0 === $p1 && ($p0 === '"' || $p0 === "'")) {
            return \substr($text, 1, -1);
        }
        return $text;
    }

    /**
     * Execute the user defined extensions.
     *
     * @param string $value
     * @return string
     */
    protected function compileExtensions($value): string
    {
        foreach ($this->extensions as $compiler) {
            $value = $compiler($value, $this);
        }
        return $value;
    }

    /**
     * Compile Blade comments into valid PHP.
     *
     * @param string $value
     * @return string
     */
    protected function compileComments($value): string
    {
        $pattern = "/" . $this->contentTags[0] . "--(.*?)--" . $this->contentTags[1] . "/s";
        switch ($this->commentMode) {
            case 0:
                return \preg_replace($pattern, $this->phpTag . '/*$1*/ ?>', $value);
            case 1:
                return \preg_replace($pattern, '<!-- $1 -->', $value);
            default:
                return \preg_replace($pattern, '', $value);
        }
    }

    /**
     * Compile Blade echos into valid PHP.
     *
     * @param string $value
     * @return string
     * @throws Exception
     */
    protected function compileEchos($value): string
    {
        foreach ($this->getEchoMethods() as $method => $length) {
            $value = $this->$method($value);
        }
        return $value;
    }

    /**
     * Get the echo methods in the proper order for compilation.
     *
     * @return array
     */
    protected function getEchoMethods(): array
    {
        $methods = [
            'compileRawEchos' => \strlen(\stripcslashes($this->rawTags[0])),
            'compileEscapedEchos' => \strlen(\stripcslashes($this->escapedTags[0])),
            'compileRegularEchos' => \strlen(\stripcslashes($this->contentTags[0])),
        ];
        \uksort($methods, static function($method1, $method2) use ($methods) {
            // Ensure the longest tags are processed first
            if ($methods[$method1] > $methods[$method2]) {
                return -1;
            }
            if ($methods[$method1] < $methods[$method2]) {
                return 1;
            }
            // Otherwise, give preference to raw tags (assuming they've overridden)
            if ($method1 === 'compileRawEchos') {
                return -1;
            }
            if ($method2 === 'compileRawEchos') {
                return 1;
            }
            if ($method1 === 'compileEscapedEchos') {
                return -1;
            }
            if ($method2 === 'compileEscapedEchos') {
                return 1;
            }
            throw new BadMethodCallException("Method [$method1] not defined");
        });
        return $methods;
    }

    /**
     * Compile Blade components that start with "x-".
     *
     * @param string $value
     *
     * @return array|string|string[]|null
     */
    protected function compileComponents($value)
    {
        /**
         * @param array $match
         *                    [0]=full expression with @ and parenthesis
         *                    [1]=Component name
         *                    [2]=parameters
         *                    [3]=...
         *                    [4]=content
         *
         * @return string
         */
        $callback = function($match) {
            if (isset($match[4]) && static::contains($match[0], 'x-')) {
                $match[4] = $this->compileComponents($match[4]);
            }
            $paramsCompiled = $this->parseParams($match[2]);
            $str = "('components." . $match[1] . "'," . $paramsCompiled . ")";
            return self::compileComponent($str) . ($match[4] ?? '') . self::compileEndComponent();
        };
        return preg_replace_callback('/<x-([a-z0-9.-]+)(\s[^>]*)?(>((?:(?!<\/x-\1>).)*)<\/x-\1>|\/>)/ms', $callback, $value);
    }

    protected function parseParams($params): string
    {
        preg_match_all('/([a-zA-Z0-9:-]*?)\s*?=\s*?(.+?)(\s|$)/ms', $params, $matches);
        $paramsCompiled = [];
        foreach ($matches[1] as $i => $key) {
            $value = str_replace('"', '', $matches[2][$i]);
            //its php code
            if (self::startsWith($key, ':')) {
                $key = substr($key, 1);
                $paramsCompiled[] = '"' . $key . '"' . '=>' . $value;
                continue;
            }
            $paramsCompiled[] = '"' . $key . '"' . '=>' . '"' . $value . '"';
        }
        return '[' . implode(',', $paramsCompiled) . ']';
    }

    /**
     * Compile Blade statements that start with "@".
     *
     * @param string $value
     *
     * @return array|string|string[]|null
     */
    protected function compileStatements($value)
    {
        /**
         * @param array $match
         *                    [0]=full expression with @ and parenthesis
         *                    [1]=expression without @ and argument
         *                    [2]=????
         *                    [3]=argument with parenthesis and without the first @
         *                    [4]=argument without parenthesis.
         *
         * @return mixed|string
         */
        $callback = function($match) {
            if (static::contains($match[1], '@')) {
                // @@escaped tag
                $match[0] = isset($match[3]) ? $match[1] . $match[3] : $match[1];
            } else {
                if (strpos($match[1], '::') !== false) {
                    // Someclass::method
                    return $this->compileStatementClass($match);
                }
                if (isset($this->customDirectivesRT[$match[1]])) {
                    if ($this->customDirectivesRT[$match[1]]) {
                        $match[0] = $this->compileStatementCustom($match);
                    } else {
                        $match[0] = \call_user_func(
                            $this->customDirectives[$match[1]],
                            $this->stripParentheses(static::get($match, 3))
                        );
                    }
                } else {
                    $nameMethod = 'compile' . \ucfirst($match[1]);
                    if (isset($this->methods[$nameMethod])) {
                        return $this->methods[$nameMethod](static::get($match, 3));
                    }
                    if (\method_exists($this, $nameMethod)) {
                        // it calls the function compile<name of the tag>
                        return $this->$nameMethod(static::get($match, 3));
                    }
                    $nameMethod = 'runtime' . \ucfirst($match[1]);
                    $m4 = $match[4] ?? '';
                    if (isset($this->methods[$nameMethod])) {
                        return $this->autoruntime($m4, $nameMethod);
                    }
                    if (\method_exists($this, $nameMethod)) {
                        return $this->autoruntime($m4, $nameMethod, true);
                    }
                    return $match[0];
                }
            }
            return isset($match[3]) ? $match[0] : $match[0] . $match[2];
        };
        /* return \preg_replace_callback('/\B@(@?\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value); */
        return preg_replace_callback('/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value);
    }

    /**
     * This function generates a php code to run a runtime method.
     * @param string|null $expression    the expression to add in the code.<br>
     *                                   For compile, it is of the type "($a2,"222")"
     *                                   For runtime, it is of the time "arg1=$a2 arg2="222""
     * @param string      $nameFunction  The name of the function.
     * @param bool        $compileMethod If the method is a compiled method, or it is a runtime method.
     * @return string
     */
    protected function autoruntime(?string $expression, string $nameFunction, $compileMethod = false): string
    {
        $args = $this->parseArgs($expression, ' ', '=', false);
        $argsV = '[';
        foreach ($args as $k => $v) {
            $argsV .= "'$k'=>$v,";
        }
        $argsV .= ']';
        if ($compileMethod) {
            return $this->wrapPHP("\$this->$nameFunction($argsV)", '', false);
        }
        return $this->wrapPHP("\$this->methods['$nameFunction']($argsV)", '', false);
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     * @return bool
     */
    public static function contains($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '') {
                if (\function_exists('mb_strpos')) {
                    if (\mb_strpos($haystack, $needle) !== false) {
                        return true;
                    }
                } elseif (\strpos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function compileStatementClass($match): string
    {
        if (isset($match[3])) {
            return $this->phpTagEcho . $this->fixNamespaceClass($match[1]) . $match[3] . '; ?>';
        }
        return $this->phpTagEcho . $this->fixNamespaceClass($match[1]) . '(); ?>';
    }

    /**
     * Util method to fix namespace of a class<br>
     * Example: "SomeClass::method()" -> "\namespace\SomeClass::method()"<br>
     *
     * @param string $text
     *
     * @return string
     * @see View
     */
    protected function fixNamespaceClass($text): string
    {
        if (strpos($text, '::') === false) {
            return $text;
        }
        $classPart = explode('::', $text, 2);
        if (isset($this->aliasClasses[$classPart[0]])) {
            $classPart[0] = $this->aliasClasses[$classPart[0]];
        }
        return $classPart[0] . '::' . $classPart[1];
    }

    /**
     * For compile custom directive at runtime.
     *
     * @param $match
     * @return string
     */
    protected function compileStatementCustom($match): string
    {
        $v = $this->stripParentheses(static::get($match, 3));
        $v = ($v == '') ? '' : ',' . $v;
        return $this->phpTag . 'call_user_func($this->customDirectives[\'' . $match[1] . '\']' . $v . '); ?>';
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param ArrayAccess|array $array
     * @param string            $key
     * @param mixed             $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        $accesible = \is_array($array) || $array instanceof ArrayAccess;
        if (!$accesible) {
            return static::value($default);
        }
        if (\is_null($key)) {
            return $array;
        }
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        foreach (\explode('.', $key) as $segment) {
            if (static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return static::value($default);
            }
        }
        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param ArrayAccess|array $array
     * @param string|int        $key
     * @return bool
     */
    public static function exists($array, $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return \array_key_exists($key, $array);
    }

    /**
     * This method removes the parenthesis of the expression and parse the arguments.
     * @param string $expression
     * @return array
     */
    protected function getArgs($expression): array
    {
        return $this->parseArgs($this->stripParentheses($expression), ' ');
    }

    /**
     * It separates a string using a separator and an identifier<br>
     * It excludes quotes,double quotes and the "¬" symbol.<br>
     * **Example**<br>
     * ```
     * $this->parseArgs('a=2,b='a,b,c',d'); // ['a'=>'2','b'=>'a,b,c','d'=>null]
     * $this->parseArgs('a=2,b=c,d'); // ['a'=>'2','b'=>'c','d'=>null]
     * $this->parseArgs('a=2 b=c',' '); // ['a'=>'2','b'=>'c']
     * $this->parseArgs('a:2 b:c',' ',':'); // ['a'=>'2','b'=>'c']
     * ```
     * Note: parseArgs('a = 2 b = c',' '); with return 4 values instead of 2.
     *
     * @param string $text      the text to separate
     * @param string $separator the separator of arguments
     * @param string $assigment the character used to assign a new value
     * @param bool   $emptyKey  if the argument is without value, we return it as key (true) or value (false) ?
     * @return array
     */
    public function parseArgs($text, $separator = ',', $assigment = '=', $emptyKey = true): array
    {
        if ($text === null || $text === '') {
            return []; //nothing to convert.
        }
        $chars = $text; // str_split($text);
        $parts = [];
        $nextpart = '';
        $strL = strlen($chars);
        $stringArr = '"\'¬';
        $parenthesis = '([{';
        $parenthesisClose = ')]}';
        $insidePar = false;
        for ($i = 0; $i < $strL; $i++) {
            $char = $chars[$i];
            // we check if the character is a parenthesis.
            $pp = strpos($parenthesis, $char);
            if ($pp !== false) {
                // is a parenthesis, so we mark as inside a parenthesis.
                $insidePar = $parenthesisClose[$pp];
            }
            if ($char === $insidePar) {
                // we close the parenthesis.
                $insidePar = false;
            }
            if (strpos($stringArr, $char) !== false) { // if ($char === '"' || $char === "'" || $char === "¬") {
                // we found a string initializer
                $inext = strpos($text, $char, $i + 1);
                $inext = $inext === false ? $strL : $inext;
                $nextpart .= substr($text, $i, $inext - $i + 1);
                $i = $inext;
            } else {
                $nextpart .= $char;
            }
            if ($char === $separator && !$insidePar) {
                $parts[] = substr($nextpart, 0, -1);
                $nextpart = '';
            }
        }
        if ($nextpart !== '') {
            $parts[] = $nextpart;
        }
        $result = [];
        // duct taping for key= argument (it has a space). however, it doesn't work with key =argument
        /*
        foreach ($parts as $k=>$part) {
            if(substr($part,-1)===$assigment && isset($parts[$k+1])) {
                var_dump('ok');
                $parts[$k].=$parts[$k+1];
                unset($parts[$k+1]);
            }
        }
        */
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part) {
                $char = $part[0];
                if (strpos($stringArr, $char) !== false) { // if ($char === '"' || $char === "'" || $char === "¬") {
                    if ($emptyKey) {
                        $result[$part] = null;
                    } else {
                        $result[] = $part;
                    }
                } else {
                    $r = explode($assigment, $part, 2);
                    if (count($r) === 2) {
                        // key=value.
                        $result[trim($r[0])] = trim($r[1]);
                    } elseif ($emptyKey) {
                        $result[trim($r[0])] = null;
                    } else {
                        $result[] = trim($r[0]);
                    }
                }
            }
        }
        return $result;
    }

    public function parseArgsOld($text, $separator = ','): array
    {
        if ($text === null || $text === '') {
            return []; //nothing to convert.
        }
        $chars = str_split($text);
        $parts = [];
        $nextpart = '';
        $strL = count($chars);
        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i < $strL; $i++) {
            $char = $chars[$i];
            if ($char === '"' || $char === "'") {
                $inext = strpos($text, $char, $i + 1);
                $inext = $inext === false ? $strL : $inext;
                $nextpart .= substr($text, $i, $inext - $i + 1);
                $i = $inext;
            } else {
                $nextpart .= $char;
            }
            if ($char === $separator) {
                $parts[] = substr($nextpart, 0, -1);
                $nextpart = '';
            }
        }
        if ($nextpart !== '') {
            $parts[] = $nextpart;
        }
        $result = [];
        foreach ($parts as $part) {
            $r = explode('=', $part, 2);
            $result[trim($r[0])] = count($r) === 2 ? trim($r[1]) : null;
        }
        return $result;
    }

    /**
     * Compile the "raw" echo statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileRawEchos($value): string
    {
        $pattern = \sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->rawTags[0], $this->rawTags[1]);
        $callback = function($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
            return $matches[1] ? \substr(
                $matches[0],
                1
            ) : $this->phpTagEcho . $this->compileEchoDefaults($matches[2]) . '; ?>' . $whitespace;
        };
        return \preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the default values for the echo statement.
     * Example:
     * {{ $test or 'test2' }} compiles to {{ isset($test) ? $test : 'test2' }}
     *
     * @param string $value
     * @return string
     */
    protected function compileEchoDefaults($value): string
    {
        // Source: https://www.php.net/manual/en/language.variables.basics.php
        $patternPHPVariableName = '\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
        $result = \preg_replace('/^(' . $patternPHPVariableName . ')\s+or\s+(.+?)$/s', 'isset($1) ? $1 : $2', $value);
        if (!$this->pipeEnable) {
            return $this->fixNamespaceClass($result);
        }
        return $this->pipeDream($this->fixNamespaceClass($result));
    }

    /**
     * It converts a string separated by pipes | into a filtered expression.<br>
     * If the method exists (as directive), then it is used<br>
     * If the method exists (in this class) then it is used<br>
     * Otherwise, it uses a global function.<br>
     * If you want to escape the "|", then you could use "/|"<br>
     * **Note:** It only works if $this->pipeEnable=true and by default it is false<br>
     * **Example:**<br>
     * ```
     * $this->pipeDream('$name | strtolower | substr:0,4'); // strtolower(substr($name ,0,4)
     * $this->pipeDream('$name| getMode') // $this->getMode($name)
     * ```
     *
     * @param string $result
     * @return string
     * @\eftec\bladeone\View::$pipeEnable
     */
    protected function pipeDream($result): string
    {
        $array = preg_split('~\\\\.(*SKIP)(*FAIL)|\|~s', $result);
        $c = count($array) - 1; // base zero.
        if ($c === 0) {
            return $result;
        }
        $prev = '';
        for ($i = 1; $i <= $c; $i++) {
            $r = @explode(':', $array[$i], 2);
            $fnName = trim($r[0]);
            $fnNameF = $fnName[0]; // first character
            if ($fnNameF === '"' || $fnNameF === '\'' || $fnNameF === '$' || is_numeric($fnNameF)) {
                $fnName = '!isset(' . $array[0] . ') ? ' . $fnName . ' : ';
            } elseif (isset($this->customDirectives[$fnName])) {
                $fnName = '$this->customDirectives[\'' . $fnName . '\']';
            } elseif (method_exists($this, $fnName)) {
                $fnName = '$this->' . $fnName;
            }
            $hasArgument = count($r) === 2;
            if ($i === 1) {
                $prev = $fnName . '(' . $array[0];
                if ($hasArgument) {
                    $prev .= ',' . $r[1];
                }
                $prev .= ')';
            } else {
                $prev = $fnName . '(' . $prev;
                if ($hasArgument) {
                    $prev .= ',' . $r[1] . ')';
                } else {
                    $prev .= ')';
                }
            }
        }
        return $prev;
    }

    /**
     * Compile the "regular" echo statements. {{ }}
     *
     * @param string $value
     * @return string
     */
    protected function compileRegularEchos($value): string
    {
        $pattern = \sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->contentTags[0], $this->contentTags[1]);
        $callback = function($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
            $wrapped = \sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2]));
            return $matches[1] ? \substr($matches[0], 1) : $this->phpTagEcho . $wrapped . '; ?>' . $whitespace;
        };
        return \preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the escaped echo statements. {!! !!}
     *
     * @param string $value
     * @return string
     */
    protected function compileEscapedEchos($value): string
    {
        $pattern = \sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->escapedTags[0], $this->escapedTags[1]);
        $callback = function($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
            return $matches[1] ? $matches[0] : $this->phpTag
                . \sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2])) . '; ?>'
                . $whitespace;
            //return $matches[1] ? $matches[0] : $this->phpTag
            // . 'echo static::e(' . $this->compileEchoDefaults($matches[2]) . '); ? >' . $whitespace;
        };
        return \preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the "@each" tag into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileEach($expression): string
    {
        return $this->phpTagEcho . "\$this->renderEach$expression; ?>";
    }

    protected function compileSet($expression): string
    {
        //$segments = \explode('=', \preg_replace("/[()\\\']/", '', $expression));
        $segments = \explode('=', $this->stripParentheses($expression));
        $value = (\count($segments) >= 2) ? '=@' . implode('=', array_slice($segments, 1)) : '++';
        return $this->phpTag . \trim($segments[0]) . $value . ';?>';
    }

    /**
     * Compile the yield statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileYield($expression): string
    {
        return $this->phpTagEcho . "\$this->yieldContent$expression; ?>";
    }

    /**
     * Compile the show statements into valid PHP.
     *
     * @return string
     */
    protected function compileShow(): string
    {
        return $this->phpTagEcho . '$this->yieldSection(); ?>';
    }

    /**
     * Compile the section statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSection($expression): string
    {
        return $this->phpTag . "\$this->startSection$expression; ?>";
    }

    /**
     * Compile the append statements into valid PHP.
     *
     * @return string
     */
    protected function compileAppend(): string
    {
        return $this->phpTag . '$this->appendSection(); ?>';
    }

    /**
     * Compile the auth statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileAuth($expression = ''): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'if(isset($this->currentUser)): ?>';
        }
        return $this->phpTag . "if(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }

    /**
     * Compile the elseauth statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileElseAuth($expression = ''): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'else: ?>';
        }
        return $this->phpTag . "elseif(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }

    /**
     * Compile the end-auth statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndAuth(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    protected function compileCan($expression): string
    {
        $v = $this->stripParentheses($expression);
        return $this->phpTag . 'if (call_user_func($this->authCallBack,' . $v . ')): ?>';
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileElseCan($expression = ''): string
    {
        $v = $this->stripParentheses($expression);
        if ($v) {
            return $this->phpTag . 'elseif (call_user_func($this->authCallBack,' . $v . ')): ?>';
        }
        return $this->phpTag . 'else: ?>';
    }
}