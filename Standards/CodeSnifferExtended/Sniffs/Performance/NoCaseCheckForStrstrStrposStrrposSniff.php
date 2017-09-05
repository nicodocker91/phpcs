<?php
/**
 * This sniff reports usages of some case checks functions while it is not required.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Performance
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\Performance;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff reports usages of some case checks functions while it is not required.
 * Following functions are concerned:
 * - stristr
 * - stripos
 * - strripos
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Performance
 */
class NoCaseCheckForStrstrStrposStrrposSniff implements Sniff
{
    public static $misusedFunction = [
        'stristr',
        'stripos',
        'strripos'
    ];

    public static $replacementMisusedFunction = [
        'stristr' => 'strstr',
        'stripos' => 'strpos',
        'strripos' => 'strrpos'
    ];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_STRING];
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

        $functionName = $tokens[$stackPtr]['content'];

        //If the T_STRING token content is not one of the element to parse, get out.
        if (!\in_array(\strtolower($functionName), self::$misusedFunction, true)) {
            return;
        }

        //Keep only functions, no method allowed.

        // Next token (except T_WHITESPACE) must be an opening parenthesis.
        $nextToken = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true, null, true);
        if (false === $nextToken || \T_OPEN_PARENTHESIS !== $tokens[$nextToken]['code']) {
            return;
        }

        // Previous token (except T_WHITESPACE) must not be an object operator, static operator, or the "function"
        // keyword.
        $operatorTokens = [\T_OBJECT_OPERATOR, \T_PAAMAYIM_NEKUDOTAYIM, \T_FUNCTION];
        $previousToken = $phpcsFile->findPrevious(\T_WHITESPACE, $stackPtr - 1, null, true, null, true);
        if (false === $previousToken || \in_array($tokens[$previousToken]['code'], $operatorTokens, true)) {
            return;
        }

        //Now the function is found, get the 2nd parameter value.
        if (false === ($secondParamValue = $this->getArgumentValue($phpcsFile, $stackPtr))) {
            return;
        }

        //If the second parameter is a string, it must contains [a-zA-Z], otherwise we need to sniff.
        if (\preg_match('#[a-z]#i', $secondParamValue)) {
            return;
        }

        $error = 'Function "%s()" misused because case sensitive check is not necessary. Replace it with "%s()".';
        $fix = $phpcsFile->addFixableError(
            $error,
            $stackPtr,
            'ReplacementMissing',
            [$functionName, self::$replacementMisusedFunction[\strtolower($functionName)]]
        );

        if (false !== $fix) {
            return;
        }

        //Start fixing.
        $phpcsFile->fixer->beginChangeset();

        //Replace the function call T_STRING token by the appropriate string.
        $phpcsFile->fixer->replaceToken($stackPtr, self::$replacementMisusedFunction[\strtolower($functionName)]);

        //End fixing. Apply all changes now.
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Get the value of the second argument of the function call, only if it is a string.
     *
     * @param File $phpcsFile
     * @param $stackPtr
     * @return bool|string
     */
    private function getArgumentValue(File $phpcsFile, $stackPtr)
    {
        if (false === ($openingParenthesis = $phpcsFile->findNext(\T_OPEN_PARENTHESIS, $stackPtr + 1))) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $limitPtr = $tokens[$openingParenthesis]['parenthesis_closer'];

        //Find the 1st comma because 2nd parameter is between the 1st comma and the 2nd comma or the end of function
        // call.
        if (false === ($commaPosition = $phpcsFile->findNext(\T_COMMA, $openingParenthesis + 1, $limitPtr))) {
            return false;
        }

        //If the second parameter is not a single token usage (except T_WHITESPACE), ignore it because too complicated.
        if (false === ($secondParamToken = $phpcsFile->findNext(\T_WHITESPACE, $commaPosition + 1, $limitPtr, true))) {
            return false;
        }
        if (false !== $phpcsFile->findNext(\T_WHITESPACE, $secondParamToken + 1, $limitPtr, true)) {
            return false;
        }

        //If the second param token is not a string (T_CONSTANT_ENCAPSED_STRING), ignore it because too complicated.
        if (\T_CONSTANT_ENCAPSED_STRING !== $tokens[$secondParamToken]['code']) {
            return false;
        }

        return \trim($tokens[$secondParamToken]['content'], '"\'');
    }
}
