<?php
/**
 * This sniff prohibits the boolean not operator more that once.
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
 * This sniff prohibits the boolean not operator more that once.
 *
 * Example
 *
 * <code>
 *  $var = !!$a
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\CodeStyle
 */
class NestedNotOperatorUsageSniff implements Sniff
{
    /**
     * @var int
     */
    protected $startOperatorPtr;

    /**
     * @var int
     */
    protected $endOperatorPtr;

    /**
     * @var int[]
     */
    protected $notPtr = [];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_BOOLEAN_NOT];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int $stackPtr  The position in the stack where the token was found.
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if (null === $this->startOperatorPtr) {
            $this->notPtr[] = $stackPtr;
            $this->startOperatorPtr = $stackPtr;
        }

        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr;
        do {
            $nextToken = $phpcsFile->findNext(\T_WHITESPACE, $ptr + 1, null, true);
            if (false !== $nextToken && \T_BOOLEAN_NOT === $tokens[$nextToken]['code']) {
                $this->notPtr[] = $nextToken;
            } else {
                $this->endOperatorPtr = $nextToken;
            }
            $ptr = $nextToken;
        } while (null === $this->endOperatorPtr);

        $nbNotOperator = count($this->notPtr);
        if ($nbNotOperator <= 1) {
            return $this->resetSniff();
        }

        $error = 'Nested not operator detected.';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'NotAllowed');

        if (false === $fix) {
            return $this->resetSniff();
        }

        if (0 === $nbNotOperator % 2) {
            $replacement = '(bool)';
        } else {
            $replacement = '!';
        }

        $phpcsFile->fixer->beginChangeset();

        for ($i = $this->startOperatorPtr; $i < $this->endOperatorPtr; ++$i) {
            $phpcsFile->fixer->replaceToken($i, '');
        }
        $phpcsFile->fixer->replaceToken($this->startOperatorPtr, $replacement);
        $phpcsFile->fixer->endChangeset();

        return $this->resetSniff();
    }

    /**
     * Reset the sniff properties and return the pointer after the last T_BOOLEAN_NOT parsed.
     *
     * @return int
     */
    private function resetSniff(): int
    {
        $endPtr = $this->endOperatorPtr;
        $this->startOperatorPtr = null;
        $this->endOperatorPtr = null;
        $this->notPtr = [];

        return $endPtr;
    }
}
