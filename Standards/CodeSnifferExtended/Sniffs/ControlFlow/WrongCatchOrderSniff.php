<?php
declare(strict_types = 1);

/**
 * This sniff detects conflicts in Exception orders in all catch statements on a try statement.
 *
 * PHP version 7
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ControlFlow
 */

namespace CodeSnifferExtended\Sniffs\ControlFlow;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * This sniff detects if you catch several times the same exception, or you catch \Exception in first when you have
 * several.
 *
 * Example of using \Exception at first:
 * <code>
 * try {
 *     //do something...
 * } catch (\Exception $e) {
 *     // catch all exceptions here
 * } catch (OtherException $other) {
 *    // you cannot reach this statement.
 * }
 * </code>
 *
 * Example of using several times the same exception:
 * <code>
 * try {
 *     //do something...
 * } catch (MySuperException $e) {
 *     // catch all exceptions here
 * } catch (MySuperException $other) {
 *    // already defined above.
 * }
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ControlFlow
 */
class WrongCatchOrderSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_TRY];
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
        $ptr = $stackPtr;
        $i = 0;
        $exceptions = [];

        $savedPtr = null;

        //Todo: handle namespaces exceptions
        //Todo: check if \Exception was not aliased before.

        while (false !== $ptr = $phpcsFile->findNext(\T_CATCH, $ptr + 1, $tokens[$stackPtr]['scope_closer'] ?? null)) {
            $ptrClass = $phpcsFile->findNext(\T_STRING, $ptr + 1);
            if ('Exception' === $tokens[$ptrClass]['content'] && 0 === $i) {
                $savedPtr = $ptr;
            }
            if (!\in_array($tokens[$ptrClass]['content'], $exceptions, true)) {
                $exceptions[] = $tokens[$ptrClass]['content'];
            } else {
                $error = 'You catch two times the Exception: %s.';
                $phpcsFile->addError($error, $ptr, null, [$tokens[$ptrClass]['content']]);
            }
            $i++;
        }
        if (null !== $savedPtr && $i > 1) {
            $phpcsFile->addError("You can't catch \\Exception in first.", $savedPtr);
        }
    }
}
