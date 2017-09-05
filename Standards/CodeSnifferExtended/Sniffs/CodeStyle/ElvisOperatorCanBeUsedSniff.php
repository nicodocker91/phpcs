<?php
/**
 * This sniff warn the user that the elvis operator (?:) can be used.
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
use PHP_CodeSniffer\Util\Tokens;

/**
 * This sniff warn the user that the elvis operator (?:) can be used.
 *
 * <code>
 *  $b = $a ? $a : $c;
 *  //Elvis operator usage:
 *  $b = $a ?: $c;
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
class ElvisOperatorCanBeUsedSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_INLINE_THEN];
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
        //Get the token where the ternary operator starts.
        $ternaryStart = $this->getTokenWhereTernaryReallyStarts($phpcsFile, $stackPtr);
        //Deduct the `condition` string as the token registered is T_INLINE_THEN.
        $conditionContent = $phpcsFile->getTokensAsString($ternaryStart, $stackPtr - 1 - $ternaryStart);

        //Get the token where `then statement` ends, which is the T_INLINE_ELSE at the same nest level than the
        //current T_INLINE_THEN.
        if (false === ($inlineElseToken = $this->getTokenWhereThenStatementEnds($phpcsFile, $stackPtr))) {
            //If no T_INLINE_ELSE found, abort.
            return;
        }
        //Deduct the `then statement` string as the token found is the appropriate T_INLINE_ELSE.
        $trueValueContent = $phpcsFile->getTokensAsString($stackPtr + 1, $inlineElseToken - $stackPtr - 1);

        //Remove useless spaces and parenthesis for comparisons.
        $testableCondition = \trim(\trim($conditionContent), '()');
        $testableTrueValue = \trim(\trim($trueValueContent), '()');

        //If `condition` and `then statement` are not equals (ignoring trailing spaces and parenthesis), ternary is ok.
        if ($testableCondition !== $testableTrueValue) {
            //Ternary is ok => no need to sniff.
            return;
        }

        // `condition` and `then statement` are same, so let's sniff!
        $warning = 'Elvis operator can be used.';
        $fix = $phpcsFile->addFixableWarning($warning, $stackPtr, 'ReplacementMissing');

        if (false === $fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        for ($ptr = $stackPtr + 1; $ptr<$inlineElseToken; ++$ptr) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Return the token where the ternary operation starts.
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return int
     */
    private function getTokenWhereTernaryReallyStarts(File $phpcsFile, int $stackPtr): int
    {
        //Set all tokens that have a better precedence that the ternary operator.
        //This is used to detect the whole `condition` part of the ternary operation.
        $precedenceBeforeTernary = Tokens::$assignmentTokens;
        $precedenceBeforeTernary[\T_LOGICAL_AND] = \T_LOGICAL_AND;
        $precedenceBeforeTernary[\T_LOGICAL_OR] = \T_LOGICAL_OR;
        $precedenceBeforeTernary[\T_LOGICAL_XOR] = \T_LOGICAL_XOR;
        $precedenceBeforeTernary[\T_COMMA] = \T_COMMA;

        $start = $phpcsFile->findStartOfStatement($stackPtr);
        $ternaryStart = $phpcsFile->findPrevious($precedenceBeforeTernary, $stackPtr - 1) + 1;
        $ternaryStart = \max($start, $ternaryStart);

        return $ternaryStart;
    }

    /**
     * Find the T_INLINE_ELSE which is at the same level of the T_INLINE_THEN.
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return int|bool
     */
    private function getTokenWhereThenStatementEnds(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $level = 1;
        $ptr = $stackPtr;

        do {
            $nextToken = [\T_INLINE_THEN, \T_INLINE_ELSE];
            $inlineElseToken = $phpcsFile->findNext($nextToken, $ptr + 1, null, false, null, true);
            if (false === $inlineElseToken) {
                break;
            }
            if (\T_INLINE_THEN === $tokens[$inlineElseToken]['code']) {
                ++$level;
            } elseif (\T_INLINE_ELSE === $tokens[$inlineElseToken]['code']) {
                --$level;
            }
            $ptr = $inlineElseToken;
        } while ($level > 0);

        return $inlineElseToken;
    }
}
