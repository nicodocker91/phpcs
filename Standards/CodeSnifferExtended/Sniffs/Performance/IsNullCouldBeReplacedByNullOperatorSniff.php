<?php
/**
 * This sniff reports usages of is_null function.
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
 * This sniff reports usages of is_null function while it is better to use the null === ... syntax.
 * The negative usage is also discouraged and must be replaced.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Performance
 */
class IsNullCouldBeReplacedByNullOperatorSniff implements Sniff
{
    public static $misusedFunction = 'is_null';

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

        // Previous token (except T_WHITESPACE) can be a T_BOOLEAN_NOT. In this case, manage the negative.
        $boolNot = $phpcsFile->findPrevious(\T_WHITESPACE, $stackPtr - 1, null, true, null, true);
        $isNegative = (false !== $boolNot && \T_BOOLEAN_NOT === $tokens[$boolNot]['code']);

        //If here, the current T_STRING is a function named as it requires to be sniffed
        $replacementForErrorProposed = 'null ' . ($isNegative ? '!' : '=') . '== ...';
        $error = 'Function "%s()" used. Replace it with "' . $replacementForErrorProposed . '".';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'ReplacementMissing', [$functionName]);

        if (false !== $fix) {
            return;
        }

        //Start fixing.
        $phpcsFile->fixer->beginChangeset();

        //If negative, remove the T_BOOLEAN_NOT
        if ($isNegative) {
            for ($i = $boolNot; $i < $stackPtr; ++$i) {
                $phpcsFile->fixer->replaceToken($i, '');
            }
        }

        //Replace the function call T_STRING token by the "null <operator>" string
        $operator = ($isNegative ? '!==' : '===');
        $phpcsFile->fixer->replaceToken($stackPtr, 'null ' . $operator);

        $openingParenthesis = $phpcsFile->findNext(\T_OPEN_PARENTHESIS, $stackPtr + 1);
        $phpcsFile->fixer->replaceToken($openingParenthesis, ' ');

        $phpcsFile->fixer->replaceToken($tokens[$openingParenthesis]['parenthesis_closer'], '');

        //End fixing. Apply all changes now.
        $phpcsFile->fixer->endChangeset();
    }
}
