<?php
/**
 * This sniff avoid usage of default random API functions and prefer using the Mersenne Twister random API functions.
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
 * This sniff avoid usage of default random API functions and prefer using the Mersenne Twister random API functions.
 *
 * Random API functions are:
 *
 * <code>
 *  rand();
 *  srand();
 *  getrandmax();
 * </code>
 *
 * Prefer using:
 * <code>
 *  mt_rand();
 *  mt_srand();
 *  mt_getrandmax();
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Compatibility
 */
class RandomAreNotMersenneTwisterSniff implements Sniff
{
    public static $randomFunction = [
        'rand',
        'srand',
        'getrandmax'
    ];

    public static $randomReplacementFunction = [
        'rand' => 'mt_rand',
        'srand' => 'mt_srand',
        'getrandmax' => 'mt_getrandmax'
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
        if (!\in_array(\strtolower($functionName), self::$randomFunction, true)) {
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

        //If here, the current T_STRING is a function named as it requires to be sniffed
        $error = 'Random function "%s()" used. Replace it with the Mersenne Twister function equivalent.';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'ReplacementMissing', [$functionName]);

        if (false !== $fix) {
            return;
        }

        //Start fixing.
        $phpcsFile->fixer->beginChangeset();

        //Replace the function with the Mersenne Twister function.
        $phpcsFile->fixer->replaceToken($stackPtr, self::$randomReplacementFunction[\strtolower($functionName)]);

        //End fixing. Apply all changes now.
        $phpcsFile->fixer->endChangeset();
    }
}
