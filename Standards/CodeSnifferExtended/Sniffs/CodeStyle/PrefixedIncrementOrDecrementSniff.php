<?php
/**
 * This sniff detects $a += 1 or $a -= 1 instead of increment or decrement operator usage.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\CodeStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff detects $a += 1 or $a -= 1 instead of increment or decrement operator usage.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
class PrefixedIncrementOrDecrementSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_PLUS_EQUAL, \T_MINUS_EQUAL];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int $stackPtr The position in the stack where the token was found.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $nextPtr = $phpcsFile->findNext(\T_WHITESPACE, $stackPtr + 1, null, true);
        if ('1' !== $tokens[$nextPtr]['content']) {
            return;
        }

        $replacement = (\T_PLUS_EQUAL === $tokens[$stackPtr]['code']) ? '++' : '--';

        $warning = 'Use increment and decrement operator instead += or -= assignation.';
        $fix = $phpcsFile->addFixableWarning($warning, $stackPtr, 'NotAllowed');

        if (false === $fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();

        if (\T_WHITESPACE === $tokens[$stackPtr - 1]['code']) {
            $phpcsFile->fixer->replaceToken($stackPtr - 1, '');
        }
        $phpcsFile->fixer->replaceToken($stackPtr, $replacement);
        $phpcsFile->fixer->replaceToken($nextPtr, '');
        $phpcsFile->fixer->endChangeset();
    }
}
