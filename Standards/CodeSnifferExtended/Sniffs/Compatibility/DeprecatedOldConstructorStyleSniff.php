<?php
/**
 * This sniff avoid usage of deprecated old style constructor.
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
 * This sniff avoid usage of deprecated old style constructor (PHP 4 style).
 *
 * <code>
 *  class Foo
 *  {
 *      function Foo(...);
 *  }
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\Compatibility
 */
class DeprecatedOldConstructorStyleSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_CLASS];
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

        //Get the next T_STRING to find the name of the current class.
        $tokenClassName = $phpcsFile->findNext(\T_STRING, $stackPtr + 1);
        if (false === $tokenClassName) {
            return;
        }

        $className = $tokens[$tokenClassName]['content'];

        //For all T_STRING that have value of the class name, check if it is a function.
        $ptr = $tokenClassName;
        do {
            $tStringUsage = $phpcsFile->findNext(\T_STRING, $ptr + 1, null, false, $className);
            if (false === $tStringUsage) {
                return;
            }
            // Previous token (except T_WHITESPACE) must be the "function" keyword.
            $previousToken = $phpcsFile->findPrevious(\T_WHITESPACE, $tStringUsage - 1, null, true);
            if (false === $previousToken) {
                return;
            }

            $ptr = $tStringUsage;
        } while (\T_FUNCTION !== $tokens[$previousToken]['code']);

        $error = 'Old constructor style found. Replace it with the __construct method.';
        $fix = $phpcsFile->addFixableError($error, $ptr, 'ReplacementMissing');

        if (false !== $fix) {
            return;
        }

        //Start fixing.
        $phpcsFile->fixer->beginChangeset();

        //Replace the function with the good constructor definition.
        $phpcsFile->fixer->replaceToken($ptr, '__construct');

        //End fixing. Apply all changes now.
        $phpcsFile->fixer->endChangeset();
    }
}
