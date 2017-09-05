<?php
/**
 * This sniff detects missing spaces surround the concatenate operator.
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
 * This sniff detects missing spaces surround the concatenate operator.
 *
 * Example
 *
 * <code>
 *  $var = $var."test";
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeSmell
 */
class BeforeAndAfterConcatenateSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_STRING_CONCAT];
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

        if (\T_WHITESPACE === $tokens[$stackPtr + 1]['code'] && \T_WHITESPACE === $tokens[$stackPtr - 1]['code']) {
            return;
        }

        $error = 'You must surround the concat operator by one space.';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'MissingSpace');
        if (false === $fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        if (\T_WHITESPACE !== $tokens[$stackPtr - 1]['code']) {
            $phpcsFile->fixer->addContentBefore($stackPtr, ' ');
        }
        if (\T_WHITESPACE !== $tokens[$stackPtr + 1]['code']) {
            $phpcsFile->fixer->addContent($stackPtr, ' ');
        }
        $phpcsFile->fixer->endChangeset();
    }
}
