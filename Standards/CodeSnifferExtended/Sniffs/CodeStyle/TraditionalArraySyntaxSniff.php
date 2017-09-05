<?php
/**
 * This sniff prohibits the use of traditional array syntax like array(...) instead of [...].
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
 * This sniff prohibits the use of traditional array syntax.
 *
 * An example of a traditional array syntax is:
 *
 * <code>
 *  $array = array('foo', 'bar');
 *  //New syntax is preferable:
 *  $array = ['foo', 'bar'];
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
class TraditionalArraySyntaxSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_ARRAY, \T_OPEN_SQUARE_BRACKET];
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

        if (\T_OPEN_SQUARE_BRACKET === $tokens[$stackPtr]['code']) {
            $phpcsFile->recordMetric($stackPtr, 'Use of []', '[]');
            return;
        }

        if ('array' !== $tokens[$stackPtr]['content']) {
            return;
        }

        $warning = 'Traditional syntax array literal detected.';
        $fix   = $phpcsFile->addFixableWarning($warning, $stackPtr, 'NotAllowed');

        if (true !== $fix) {
            return;
        }

        $openParenthesisPtr = $phpcsFile->findNext(\T_OPEN_PARENTHESIS, $stackPtr + 1);
        $closeParenthesisPtr = $tokens[$openParenthesisPtr]['parenthesis_closer'];

        $phpcsFile->fixer->beginChangeset();
        for ($ptr = $stackPtr; $ptr<$openParenthesisPtr; ++$ptr) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }
        $phpcsFile->fixer->replaceToken($openParenthesisPtr, '[');
        $phpcsFile->fixer->replaceToken($closeParenthesisPtr, ']');
        $phpcsFile->fixer->endChangeset();
    }
}
