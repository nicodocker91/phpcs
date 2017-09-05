<?php
/**
 * This sniff warns the developer when usage of identical case value in a switch.
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
 * This sniff warns the developer when usage of identical case value in a switch.
 *
 * An example of identical case value in a switch is:
 *
 * <code>
 *  switch($value) {
 *      case 'A':
 *          //Do A stuff
 *          break;
 *      case 'A': //=> see that 'A' expression is already used above.
 *          //Do B stuff
 *          break;
 *      default:
 *          //Do default stuff
 *          break;
 *  }
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ProbableBugs
 */
class DuplicateSwitchCaseSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_SWITCH];
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

        $switchLevel = $tokens[$stackPtr]['level'];
        $expectedCaseLevel = $switchLevel + 1;

        $scopeStart = ($tokens[$stackPtr]['scope_opener'] ?? $stackPtr) + 1;
        $scopeEnd = ($tokens[$stackPtr]['scope_closer'] ?? null);

        $allCasesConditions = [];
        for ($ptr = $scopeStart; $ptr < $scopeEnd; ++$ptr) {
            //For the whole switch instruction, we will parse all cases related to this switch.
            if (\T_CASE !== $tokens[$ptr]['code'] || $expectedCaseLevel !== $tokens[$ptr]['level']) {
                continue;
            }

            //Get the token position just after the `case` keyword.
            $afterCase = $tokens[$ptr]['scope_condition'] + 1;
            //Get the token position of the opening new scope for the `case` purpose.
            $beforeCaseContent = $tokens[$ptr]['scope_opener'];

            //Get the case expression in a string trimmed view.
            $caseCondition = \trim($phpcsFile->getTokensAsString($afterCase, $beforeCaseContent - $afterCase));

            //If this trimmed string was not already recorded, save it.
            if (false === ($firstOccurrenceLine = \array_search($caseCondition, $allCasesConditions, true))) {
                $allCasesConditions[$tokens[$ptr]['line']] = $caseCondition;
                continue;
            }

            //Otherwise, the case condition already exists, so add error.
            $error = 'Duplicate case expression: %s. First occurence of this expression at line %d.';
            $phpcsFile->addError($error, $ptr, null, [$caseCondition, $firstOccurrenceLine]);
        }
    }
}
