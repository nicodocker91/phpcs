<?php
/**
 * This sniff warns the developer when usage of identical variable symbol names are used in foreach statement.
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
 * This sniff warns the developer when usage of identical variable symbol names are used in foreach statement.
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ProbableBugs
 */
class ForeachArrayIsUsedAsKeyOrValueSniff implements Sniff
{
    /** @var string|null */
    private $arrayVarName;

    /** @var string|null */
    private $keyVarName;

    /** @var string|null */
    private $valueVarName;

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_FOREACH];
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
        $this->defineVariableNames($phpcsFile, $stackPtr);

        if (//If all 3 parts are identical.
            null !== $this->arrayVarName &&
            $this->keyVarName === $this->valueVarName &&
            $this->keyVarName === $this->arrayVarName
        ) {
            $error = 'The "%s" variable is defined in "array_expression", "key_expression" and "value_expression".';
            $phpcsFile->addError($error, $stackPtr, null, [$this->arrayVarName]);
            return;
        }

        if (null !== $this->arrayVarName && $this->arrayVarName === $this->keyVarName) {
            $error = 'The "%s" variable is already defined in "array_expression" and "key_expression".';
            $phpcsFile->addError($error, $stackPtr, null, [$this->arrayVarName]);
        }

        if (null !== $this->arrayVarName && $this->arrayVarName === $this->valueVarName) {
            $error = 'The "%s" variable is already defined in "array_expression" and "value_expression".';
            $phpcsFile->addError($error, $stackPtr, null, [$this->arrayVarName]);
        }

        if (null !== $this->valueVarName && $this->keyVarName === $this->valueVarName) {
            $error = 'The "%s" variable is already defined in "key_expression" and "value_expression".';
            $phpcsFile->addError($error, $stackPtr, null, [$this->keyVarName]);
        }
    }

    /**
     * Function that will find the variable symbol name of <array>, <key> and <value> parts of a given foreach.
     * We are gonna parse the following statements:
     * - foreach (<array> as <value>) {
     * - foreach (<array> as <key> => <value>) {
     *
     * @param File $phpcsFile The PHPCS file parsing.
     * @param int $stackPtr                   The token index of the foreach (T_FOREACH).
     */
    private function defineVariableNames(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $openingForeach = $tokens[$stackPtr]['parenthesis_opener'];
        $closingForeach = $tokens[$stackPtr]['parenthesis_closer'];
        $asOperator = $phpcsFile->findNext(\T_AS, $openingForeach, $closingForeach);

        //Find the <array> variable.
        //It must be before the T_AS token but after the $openingForeach.
        if (false === ($arrayVariable = $phpcsFile->findPrevious(\T_VARIABLE, $asOperator - 1, $openingForeach))) {
            //The <array> variable is not a variable, probably directly a value.
            return;
        }

        //Find the <key> or <value> variable.
        //It must be after the T_AS token but before the $closingForeach.
        if (false === ($valueVariable = $phpcsFile->findNext(\T_VARIABLE, $asOperator + 1, $closingForeach))) {
            //The <key> or <value> variable is not a variable.
            return;
        }

        //If there is a token T_DOUBLE_ARROW between the <value> and the $closingForeach, <value> is actually
        //<key> and we need to find the real <value>
        if (false !== $phpcsFile->findNext(\T_DOUBLE_ARROW, $valueVariable + 1, $closingForeach)) {
            $keyVariable = $valueVariable;
            if (false === ($valueVariable = $phpcsFile->findNext(\T_VARIABLE, $keyVariable + 1, $closingForeach))) {
                //The <value> variable is not a variable.
                return;
            }
        }

        $this->arrayVarName = $tokens[$arrayVariable]['content'];
        $this->valueVarName = $tokens[$valueVariable]['content'];
        $this->keyVarName = isset($keyVariable) ? $tokens[$keyVariable]['content'] : null;
    }
}
