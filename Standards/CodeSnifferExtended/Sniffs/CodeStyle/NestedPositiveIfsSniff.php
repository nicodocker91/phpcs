<?php
declare(strict_types = 1);

/**
 * This sniff detects useless nested if statements.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */

namespace CodeSnifferExtended\Sniffs\CodeStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff detects useless nested if statements. Useless nested if statements are found when an if statement is
 * inside a based one and when this based one only contains the nested if without any other statement.
 *
 * Example
 *
 * <code>
 * if (true === $a) {
 *     if (true === $b) {
 *         //... do something
 *     }
 * }
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
class NestedPositiveIfsSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_IF];
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
        $curToken = $tokens[$stackPtr];

        $baseIfOpen = $curToken['scope_opener'] ?? $curToken['parenthesis_closer'];
        $baseIfClose = $curToken['scope_closer'] ?? $phpcsFile->findNext(\T_WHITESPACE, $baseIfOpen + 1, null, true);

        $nestedIfToken = $phpcsFile->findNext(\T_WHITESPACE, $baseIfOpen + 1, null, true);

        if (\T_IF !== $tokens[$nestedIfToken]['code']) {
            return;
        }

        $nestedIfClosesAt = $tokens[$nestedIfToken]['scope_closer'] ?? null;
        if (null === $nestedIfClosesAt) {
            return;
        }
        $expectedEndOfBasedIf = $phpcsFile->findNext(\T_WHITESPACE, $nestedIfClosesAt + 1, null, true);

        if ($expectedEndOfBasedIf !== $baseIfClose) {
            return;
        }

        $error = 'Nested if detected.';
        $phpcsFile->addError($error, $stackPtr, 'NotAllowed');
    }
}
