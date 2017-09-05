<?php
/**
 * This sniff avoid usage of gmmktime() and mktime() functions if they have no arguments.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Compatibility
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\Compatibility;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff avoid usage of gmmktime() and mktime() functions if they have no arguments.
 *
 * Replace those usages with time() function instead.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Compatibility
 */
class MktimeNotCompatibleSniff implements Sniff
{
    public static $timeFunction = [
        'mktime',
        'gmmktime'
    ];

    public static $timeReplacementFunction = [
        'mktime' => 'time',
        'gmmktime' => 'time'
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
        if (!\in_array(\strtolower($functionName), self::$timeFunction, true)) {
            return;
        }

        //Keep only functions, no method allowed.

        // Next token (except T_WHITESPACE) must be an opening parenthesis.
        $openingParenthesis = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true, null, true);
        if (false === $openingParenthesis || \T_OPEN_PARENTHESIS !== $tokens[$openingParenthesis]['code']) {
            return;
        }

        // Previous token (except T_WHITESPACE) must not be an object operator, static operator, or the "function"
        // keyword.
        $operatorTokens = [\T_OBJECT_OPERATOR, \T_PAAMAYIM_NEKUDOTAYIM, \T_FUNCTION];
        $previousToken = $phpcsFile->findPrevious(\T_WHITESPACE, $stackPtr - 1, null, true, null, true);
        if (false === $previousToken || \in_array($tokens[$previousToken]['code'], $operatorTokens, true)) {
            return;
        }

        // Find the closing parenthesis of the function call, as this token must be the same as the next closing one.
        $closingParenthesisFromOpen = $tokens[$openingParenthesis]['parenthesis_closer'];
        $closingParenthesisFromNext = $phpcsFile->findNext(\T_WHITESPACE, $openingParenthesis + 1, null, true);

        if (//the token is not a T_CLOSE_PARENTHESIS or not a the right place, abort.
            \T_CLOSE_PARENTHESIS !== $tokens[$closingParenthesisFromNext]['code'] ||
            $closingParenthesisFromNext !== $closingParenthesisFromOpen
        ) {
            return;
        }

        //If here, the current T_STRING is a function named as it requires to be sniffed
        $error = 'Function "%s()" misused. Replace it with "time()" function.';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'ReplacementMissing', [$functionName]);

        if (false !== $fix) {
            return;
        }

        //Start fixing.
        $phpcsFile->fixer->beginChangeset();

        //Replace the function with the appropriate function.
        $phpcsFile->fixer->replaceToken($stackPtr, self::$timeReplacementFunction[\strtolower($functionName)]);

        //End fixing. Apply all changes now.
        $phpcsFile->fixer->endChangeset();
    }
}
