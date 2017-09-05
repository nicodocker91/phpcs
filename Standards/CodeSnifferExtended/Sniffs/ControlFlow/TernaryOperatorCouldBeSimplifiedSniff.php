<?php
/**
 * This sniff reports when useless definition of <code>true</code> and <code>false</code> are used in ternary
 * operations.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ControlFlow
 */
declare(strict_types = 1);

namespace CodeSnifferExtended\Sniffs\ControlFlow;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * This sniff reports when useless definition of <code>true</code> and <code>false</code> are used in ternary
 * operations.
 *
 * <code>
 *  (1 === true ? true : false);
 *  //Can be replaced by:
 *  (1 === true);
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ControlFlow
 */
class TernaryOperatorCouldBeSimplifiedSniff implements Sniff
{
    /**
     * @const string Used to define the type of ternary condition is an expression (not TRUE or FALSE).
     */
    const TYPE_EXPRESSION = 'EXPRESSION';

    /**
     * @const string Used to define the type of ternary condition is TRUE (not an expression or FALSE).
     */
    const TYPE_TRUE = 'TRUE';

    /**
     * @const string Used to define the type of ternary condition is FALSE (not an expression or TRUE).
     */
    const TYPE_FALSE = 'FALSE';

    /**
     * @var string The type of the condition. Must be one of the class constant defined above.
     */
    private $conditionType;

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
        //$ternaryStart = $this->getTokenWhereTernaryReallyStarts($phpcsFile, $stackPtr);
        //Deduct the `condition` string as the token registered is T_INLINE_THEN.
        //$conditionContent = $phpcsFile->getTokensAsString($ternaryStart, $stackPtr - 1 - $ternaryStart);

        //If the $conditionContent does not reflect a boolean result, abort as `condition` must be boolean.
        //if (false === $this->isConditionExpressionBoolean($phpcsFile, $conditionContent)) {
        //    return;
        //}

        //Call the processing depending on the condition type found.
        switch ($this->conditionType) {
            case self::TYPE_TRUE:
                $this->processTrue($phpcsFile, $stackPtr);
                return;
            case self::TYPE_FALSE:
                $this->processFalse($phpcsFile, $stackPtr);
                return;
            case self::TYPE_EXPRESSION:
                $this->processExpression($phpcsFile, $stackPtr);
                return;
            default:
                return;
        }
    }

    /**
     * Execute the process if the condition is true.
     * @param File $phpcsFile
     * @param int $stackPtr
     */
    private function processTrue(File $phpcsFile, int $stackPtr)
    {
        $msg = 'Condition returns true. Please only keep the positive part.';
        $fix = $phpcsFile->addFixableError($msg, $stackPtr, 'RemoveUseless');
        if (false === $fix) {
            return;
        }

        //Get the token where the ternary operator starts.
        $ternaryStart = $this->getTokenWhereTernaryReallyStarts($phpcsFile, $stackPtr);
        $ternaryEnd = $this->getTokenWhereTernaryReallyEnds($phpcsFile, $stackPtr);

        $tInlineElse = $this->getTokenWhereThenStatementEnds($phpcsFile, $stackPtr) - 1;
        $positivePartContent = \trim($phpcsFile->getTokensAsString($stackPtr + 1, $tInlineElse - ($stackPtr + 1)));

        $phpcsFile->fixer->beginChangeset();
        for ($i = $ternaryStart; $i <= $ternaryEnd; ++$i) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->replaceToken($stackPtr, $positivePartContent);
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Execute the process if the condition is false.
     * @param File $phpcsFile
     * @param int $stackPtr
     */
    private function processFalse(File $phpcsFile, int $stackPtr)
    {
        $msg = 'Condition returns false. Please only keep the negative part.';
        $fix = $phpcsFile->addFixableError($msg, $stackPtr, 'RemoveUseless');
        if (false === $fix) {
            return;
        }

        //Get the token where the ternary operator starts.
        $ternaryStart = $this->getTokenWhereTernaryReallyStarts($phpcsFile, $stackPtr);
        $ternaryEnd = $this->getTokenWhereTernaryReallyEnds($phpcsFile, $stackPtr);

        $tInlineElse = $this->getTokenWhereThenStatementEnds($phpcsFile, $stackPtr) + 1;
        $negativePartContent = \trim($phpcsFile->getTokensAsString($tInlineElse, $ternaryEnd - $tInlineElse + 1));

        $phpcsFile->fixer->beginChangeset();
        for ($i = $ternaryStart; $i <= $ternaryEnd; ++$i) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->replaceToken($stackPtr, $negativePartContent);
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Execute the process if the condition is an expression.
     * @param File $phpcsFile
     * @param int $stackPtr
     */
    private function processExpression(File $phpcsFile, int $stackPtr)
    {
        //Get the token where the ternary operator ends.
        $ternaryEnd = $this->getTokenWhereTernaryReallyEnds($phpcsFile, $stackPtr);
        $tInlineElse = $this->getTokenWhereThenStatementEnds($phpcsFile, $stackPtr) - 1;

        $tBeforeInlineElse = $tInlineElse - 1;
        $positiveContent = \trim($phpcsFile->getTokensAsString($stackPtr + 1, $tBeforeInlineElse - ($stackPtr + 1)));

        $tAfterInlineElse = $tInlineElse + 1;
        $negativeContent = \trim($phpcsFile->getTokensAsString($tAfterInlineElse, $ternaryEnd - $tAfterInlineElse + 1));

        if (//Check that both positive and negative part are booleans.
            ('true' === \strtolower($positiveContent) || 'false' === \strtolower($positiveContent)) &&
            ('true' === \strtolower($negativeContent) || 'false' === \strtolower($negativeContent))
        ) {
            $warning = 'Positive and negative variants can be skipped: the condition already returns a boolean.';
            $phpcsFile->addWarning($warning, $stackPtr, 'RemoveUseless');
        }
    }

    /**
     * List the token that have a better precedence that the ternary operator.
     * Used in order to limit the bounds of the ternary operator.
     *
     * @return array
     */
    private function getPrecedenceBeforeTernary(): array
    {
        //Set all tokens that have a better precedence that the ternary operator.
        //This is used to detect the whole `condition` part of the ternary operation.
        $precedenceBeforeTernary = Tokens::$assignmentTokens;
        $precedenceBeforeTernary[\T_LOGICAL_AND] = \T_LOGICAL_AND;
        $precedenceBeforeTernary[\T_LOGICAL_OR] = \T_LOGICAL_OR;
        $precedenceBeforeTernary[\T_LOGICAL_XOR] = \T_LOGICAL_XOR;
        $precedenceBeforeTernary[\T_COMMA] = \T_COMMA;

        return $precedenceBeforeTernary;
    }

    /**
     * Return the token where the ternary operation starts.
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return int
     */
    private function getTokenWhereTernaryReallyStarts(File $phpcsFile, int $stackPtr): int
    {
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        $ternaryStart = $phpcsFile->findPrevious($this->getPrecedenceBeforeTernary(), $stackPtr - 1) + 1;
        $ternaryStart = \max($start, $ternaryStart);

        return $ternaryStart;
    }

    /**
     * Return the token where the ternary operation ends.
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return int
     */
    private function getTokenWhereTernaryReallyEnds(File $phpcsFile, int $stackPtr): int
    {
        $end = $phpcsFile->findEndOfStatement($stackPtr);
        $ternaryEnd = $phpcsFile->findNext($this->getPrecedenceBeforeTernary(), $stackPtr + 1) - 1;
        $ternaryEnd = \min($end, $ternaryEnd);

        return $ternaryEnd;
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

    /**
     * Based on the string that represent the `condition` statement of the current ternary operation, this function
     * returns the fact that the expression is evaluated as a boolean or not.
     *
     * @param File $phpcsFile
     * @param string $conditionContent
     * @return bool
     */
    /*
    private function isConditionExpressionBoolean(File $phpcsFile, string $conditionContent): bool
    {
        //Tokenize the string by adding it an opening php tag to make understand the tokenizer it is PHP code.
        $aTokens = $this->tokenizeString($phpcsFile, '<?php ' . $conditionContent);
        \array_shift($aTokens); //Remove useless opening tag from token.

        $aCodes = \array_unique(\array_column($aTokens, 'code'));

        $aComparisonToken = \array_merge(
            Tokens::$comparisonTokens, // Comparisons return always booleans
            Tokens::$booleanOperators, // Boolean operators return always booleans
            [\T_INSTANCEOF => \T_INSTANCEOF] // Instanceof operator returns boolean.
        );

        //If in those tokens, any comparison operator is found, this is boolean expression.
        foreach ($aCodes as $iCode) {
            if (\in_array($iCode, $aComparisonToken, true)) {
                $this->conditionType = self::TYPE_EXPRESSION;
                return true;
            }
        }

        //If no comparison found, remove all empty tokens and both T_OPEN_PARENTHESIS and T_CLOSE_PARENTHESIS.
        //If what only left is T_TRUE or T_FALSE, we get the condition type.
        $aFilter = \array_merge(
            Tokens::$emptyTokens,
            [\T_OPEN_PARENTHESIS => \T_OPEN_PARENTHESIS, \T_CLOSE_PARENTHESIS => \T_CLOSE_PARENTHESIS]
        );
        $aTrimmedCodes = \array_filter($aCodes, function ($code) use ($aFilter) {
            return !\in_array($code, $aFilter, true);
        });

        //If the trim left not only 1 code, it is impossible to know if the expression is boolean.
        if (1 !== count($aTrimmedCodes)) {
            return false;
        }
        //If the trim left only 1 code, it can be the `true` or `false` value.
        $iTrimmedCode = \reset($aTrimmedCodes);
        if (\T_TRUE === $iTrimmedCode) {
            $this->conditionType = self::TYPE_TRUE;
            return true;
        }
        if (\T_FALSE === $iTrimmedCode) {
            $this->conditionType = self::TYPE_FALSE;
            return true;
        }

        //If it's not `true` or `false`, this is not a boolean expression.
        return false;
    }
    */

    /**
     * Tokenize a given string by respecting the snippet of PHP Code Sniffer, ignoring errors as we cannot know the
     * quality of the parsed content.
     *
     * @param File $phpcsFile
     * @param string $string
     * @return array
     */
    /*
    private function tokenizeString(File $phpcsFile, string $string): array
    {
        // Because we are not really parsing code, the tokenizer can throw all sorts
        // of errors that don't mean anything, so ignore them.
        $oldErrors = \ini_get('error_reporting');
        \ini_set('error_reporting', '0');
        try {
            $stringTokens = ???($string, $phpcsFile->tokenizer, $phpcsFile->eolChar);
        } catch (\PHP_CodeSniffer\Exceptions\TokenizerException $e) {
            // We couldn't check the string, so ignore it.
            \ini_set('error_reporting', $oldErrors);
            return [];
        }

        \ini_set('error_reporting', $oldErrors);
        return $stringTokens;
    }
    */
}
