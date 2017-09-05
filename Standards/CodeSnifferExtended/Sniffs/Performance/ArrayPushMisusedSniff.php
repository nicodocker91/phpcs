<?php
/**
 * This sniff reports usages of array_push function.
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
 * This sniff reports usages of array_push function while it is better to use the $a[] = $x syntax.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Performance
 */
class ArrayPushMisusedSniff implements Sniff
{
    public static $misusedFunction = 'array_push';

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
        if (\strtolower($functionName) !==  self::$misusedFunction) {
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

        if (false === ($arrayVar = $phpcsFile->findNext(\T_VARIABLE, $stackPtr + 1, null, false, null, true))) {
            //If here, the current T_STRING is a function named as it requires to be sniffed
            $error = 'Function "%s()" used but with invalid signature.';
            $phpcsFile->addError($error, $stackPtr, null, [$functionName]);
            return;
        }

        //If here, the current T_STRING is a function named as it requires to be sniffed
        $error = 'Function "%s()" used. Replace it with the [] short syntax equivalent.';
        $phpcsFile->addError($error, $stackPtr, null, [$functionName]);
    }
}
