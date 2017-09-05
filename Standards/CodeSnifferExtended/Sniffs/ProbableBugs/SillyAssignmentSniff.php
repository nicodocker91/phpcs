<?php
/**
 * This sniff prohibits the use of silly assignments.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ProbableBugs
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\ProbableBugs;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff prohibits the use of silly assignments.
 *
 * An example of silly assignment is:
 *
 * <code>
 *  $a = $a;
 *  $array['foo'] = $array['foo'];
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ProbableBugs
 */
class SillyAssignmentSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_EQUAL];
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

        //Recover only the tokens that are concerned by the current statement of assignation.
        $statementStart = $phpcsFile->findStartOfStatement($stackPtr);

        //If the statement is not starting by a variable, we are not in assignment so this sniff does not care.
        if (\T_VARIABLE !== $tokens[$statementStart]['code']) {
            return;
        }

        $statementEnd = $phpcsFile->findEndOfStatement($stackPtr);

        //$assignee is the left side of the assignment.
        $assignee = \trim($phpcsFile->getTokensAsString($statementStart, $stackPtr - $statementStart));
        //$assigner is the right side of the assignment.
        $assigner = \trim($phpcsFile->getTokensAsString($stackPtr + 1, $statementEnd - ($stackPtr + 1)));

        //If both are different, then no error.
        if ($assignee !== $assigner) {
            return;
        }

        $error = 'The left and the right parts of assignment are equal.';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'RemoveUseless');

        if (false === $fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        for ($ptr = $statementStart; $ptr<=$statementEnd; ++$ptr) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }
        //Cleaning also previous white spaces
        for ($ptr = $statementStart - 1; \T_WHITESPACE === $tokens[$ptr]['code']; --$ptr) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }
        $phpcsFile->fixer->endChangeset();
    }
}
