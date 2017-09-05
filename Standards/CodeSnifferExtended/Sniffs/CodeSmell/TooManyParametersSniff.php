<?php
/**
 * This sniff prohibits the use of too many parameters declared in functions and methods.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeSmell
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\CodeSmell;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff prohibits the use of too many parameters declared in functions and methods.
 *
 * Based on @link http://ricardogeek.com/docs/clean_code.pdf page 288, Code smell #F1:
 * "Functions should have a small number of arguments. No argument is best, followed by one, two, and three. More than
 * three is very questionable and should be avoided with prejudice."
 * A more precise idea of this point is detailled in page 40 of this book:
 * "The ideal number of arguments for a function is zero (niladic). Next comes one (monadic), followed closely by two
 * (dyadic). Three arguments (triadic) should be avoided where possible. More than three (polyadic) requires very
 * special justification — and then shouldn't be used anyway."
 *
 * Beside this explaination, there is also a technical performance leak when dealing with more than 3 arguments for
 * dynamic methods and more than 4 arguments for functions or static methods.
 * The memory stack of the arguments list when compiling a function is set to 4 blocks only, and new memory blocks must
 * be allocated when necessary. When compiling a dynamic method, the object ($this, in the PHP case) is automatically
 * taken the first memory block, allowing only 3 more blocks if we do not want waste time and more memory to allocate.
 *
 * For both of these reasons, the maximum number of arguments to be declared in static methods and functions is 4.
 * Then, the maximum number of arguments to be declared in dynamic methods is 3 (because $this already take one).
 * Be aware than constructors are allowed to take 4 arguments as they are implicit static methods.
 * Those values are defined by default but may be overwritten.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeSmell
 */
class TooManyParametersSniff implements Sniff
{
    /**
     * Flag that defines if the sniff must ignore the constructor.
     * If not ignored, maximum number of arguments for the constructor is defined by the maximum number of arguments
     * allowed for static methods.
     *
     * Default to false.
     *
     * @var bool
     */
    public $ignoreConstructors = false;

    /**
     * Define the maximum number of arguments a dynamic method must handle, taking $this away.
     * Set this value to a negative number to ignore sniffing dynamic methods.
     *
     * Default to 3.
     *
     * @var int
     */
    public $maxArgsMethods = 3;

    /**
     * Define the maximum number of arguments a static method must handle.
     * Set this value to a negative number to ignore sniffing static methods.
     *
     * Default to 4.
     *
     * @var int
     */
    public $maxArgsStatics = 4;

    /**
     * Define the maximum number of arguments a function must handle.
     * Set this value to a negative number to ignore sniffing functions.
     *
     * Default to 4.
     *
     * @var int
     */
    public $maxArgsFunctions = 4;

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_FUNCTION];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        //Determine which kind of T_FUNCTION we are dealing with.
        //If the conditions are empty, there is no scope opened than the main one. So, we are dealing with a function.
        if (empty($tokens[$stackPtr]['conditions'])) {
            $this->checkParametersOnFunction($phpcsFile, $stackPtr);
            return;
        }

        //Rewind from T_FUNCTION token until encoutering something else than:
        // - T_STATIC (what we are looking for)
        // - T_WHITESPACE (allowed)
        // - T_PUBLIC, T_PROTECTED or T_PRIVATE (not mandatory but we may find it)
        // - T_ABSTRACT (for abstract methods)
        // - T_FINAL (for final methods)

        $isStatic = false;
        $allowedTokens = [
            \T_STATIC,
            \T_WHITESPACE,
            \T_PUBLIC,
            \T_PROTECTED,
            \T_PRIVATE,
            \T_ABSTRACT,
            \T_FINAL,
        ];
        for ($ptr = $stackPtr - 1; !$isStatic; --$ptr) {
            if (!\in_array($tokens[$ptr]['code'], $allowedTokens, true)) {
                break;
            }
            $isStatic = (\T_STATIC === $tokens[$ptr]['code']);
        }

        //Call the right checker using the flag $isStatic
        if (true === $isStatic) {
            $this->checkParametersOnStaticMethod($phpcsFile, $stackPtr);
            return;
        }

        //If the method is the constructor, some special rules happen.
        $tokenMethodName = $phpcsFile->findNext(\T_STRING, $stackPtr);
        $methodName = $tokens[$tokenMethodName]['content'];

        if ('__construct' !== \strtolower($methodName)) {
            $this->checkParametersOnDynamicMethod($phpcsFile, $stackPtr);
            return;
        }
        $this->checkParametersOnConstructor($phpcsFile, $stackPtr);
    }

    /**
     * Check the maximum number of arguments in a function.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    private function checkParametersOnFunction(File $phpcsFile, int $stackPtr): void
    {
        $phpcsFile->recordMetric($stackPtr, 'Use of function', 'function');

        //If maximum number of arguments is negative, ignore sniffing functions.
        if ($this->maxArgsFunctions < 0) {
            return;
        }

        if (($nbArgs = $this->getNumberOfArguments($phpcsFile, $stackPtr)) > $this->maxArgsFunctions) {
            $error = 'Too many parameters in function. Found %d while maximum allowed is %d.';
            $phpcsFile->addWarning($error, $stackPtr, 'TooHigh', [$nbArgs, $this->maxArgsFunctions]);
        }
    }

    /**
     * Check the maximum number of arguments in a static method.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    private function checkParametersOnStaticMethod(File $phpcsFile, int $stackPtr): void
    {
        $phpcsFile->recordMetric($stackPtr, 'Use of method', 'method');

        //If maximum number of arguments is negative, ignore sniffing functions.
        if ($this->maxArgsStatics< 0) {
            return;
        }

        if (($nbArgs = $this->getNumberOfArguments($phpcsFile, $stackPtr)) > $this->maxArgsStatics) {
            $error = 'Too many parameters in static method. Found %d while maximum allowed is %d.';
            $phpcsFile->addWarning($error, $stackPtr, 'TooHigh', [$nbArgs, $this->maxArgsStatics]);
        }
    }

    /**
     * Check the maximum number of arguments in a dynamic method.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    private function checkParametersOnDynamicMethod(File $phpcsFile, int $stackPtr): void
    {
        $phpcsFile->recordMetric($stackPtr, 'Use of method', 'method');

        //If maximum number of arguments is negative, ignore sniffing functions.
        if ($this->maxArgsMethods < 0) {
            return;
        }

        if (($nbArgs = $this->getNumberOfArguments($phpcsFile, $stackPtr)) > $this->maxArgsMethods) {
            $error = 'Too many parameters in dynamic method. Found %d while maximum allowed is %d.';
            $phpcsFile->addWarning($error, $stackPtr, 'TooHigh', [$nbArgs, $this->maxArgsMethods]);
        }
    }

    /**
     * Check the maximum number of arguments in a constructor.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    private function checkParametersOnConstructor(File $phpcsFile, int $stackPtr): void
    {
        $phpcsFile->recordMetric($stackPtr, 'Use of method', 'method');

        //If maximum number of arguments is negative, ignore sniffing functions.
        if (true === $this->ignoreConstructors || $this->maxArgsStatics < 0) {
            return;
        }

        if (($nbArgs = $this->getNumberOfArguments($phpcsFile, $stackPtr)) > $this->maxArgsStatics) {
            $error = 'Too many parameters in constructor. Found %d while maximum allowed is %d.';
            $phpcsFile->addWarning($error, $stackPtr, 'TooHigh', [$nbArgs, $this->maxArgsStatics]);
        }
    }

    /**
     * Find the number of argument of a function or method.
     *
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return int
     */
    private function getNumberOfArguments(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        //Get the token of opening parenthesis and closing parenthesis for finding the number of arguments inside.
        $open = $tokens[$stackPtr]['parenthesis_opener'];
        $close = $tokens[$stackPtr]['parenthesis_closer'];

        //Counting the number of T_VARIABLE tokens between opening and closing parenthesis. It will be the number of
        //arguments.
        $nbArgs = 0;
        for ($ptr = $open + 1; $ptr < $close; ++$ptr) {
            $nbArgs += (int)(\T_VARIABLE === $tokens[$ptr]['code']);
        }

        return $nbArgs;
    }
}
