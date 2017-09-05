<?php
/**
 * This sniff warns the developer when usage of identical keys in array.
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
 * This sniff warns the developer when usage of identical keys in array.
 *
 * An example of identical keys in array is:
 *
 * <code>
 *  $array = array('a' => 1, 'a' => 2);
 *  or
 *  $array = ['a' => 1, 'a' => 2];
 * </code>
 *
 * @category  \PHP_CodeSniffer
 * @package   CodeSnifferExtended
 * @subpackage Sniffs\ProbableBugs
 */
class DuplicateArrayKeysSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register(): array
    {
        return [\T_ARRAY, \T_OPEN_SHORT_ARRAY];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        //TODO: avoid checking operations in keys (if the key is not a single token, abort.)

        $tokens = $phpcsFile->getTokens();

        $ptrOpen = $this->getOpenPtr($tokens[$stackPtr]);
        $ptrClose = $this->getClosePtr($tokens[$stackPtr]);

        $ptr = $ptrOpen;
        $allKeys = [];
        while ($ptr < $ptrClose && false !== ($doubleArrowPtr = $phpcsFile->findNext(\T_DOUBLE_ARROW, $ptr))) {
            //If double arrow is not between the opening and closing current array, it is a foreach usage
            //Like `foreach ([0, 1, 2] as $key => $value)`. Ignore this case, and go to the next double arrow.
            if ($doubleArrowPtr < $ptrOpen || $doubleArrowPtr > $ptrClose) {
                $ptr = $doubleArrowPtr + 1;
                continue;
            }

            //If the double arrow belong to a nested array, the previous array in token list will be different from the
            //currently parsing array or the double arrow position is after the closing token position of the previous
            //array.

            $ptrPreviousArray = $phpcsFile->findPrevious($this->register(), $doubleArrowPtr);
            $ptrClosingPreviousArray = $this->getClosePtr($tokens[$ptrPreviousArray]);

            if ($ptrPreviousArray !== $stackPtr && $ptrClosingPreviousArray > $doubleArrowPtr) {
                //fast-forward the token position pointer to the end of the previous array to continue to the following
                //T_DOUBLE_ARROW to parse.
                $ptr = $this->getClosePtr($tokens[$ptrPreviousArray]);
                continue;
            }

            $keyToken = $phpcsFile->findPrevious(\T_WHITESPACE, $doubleArrowPtr - 1, null, true);
            $rawKey = $tokens[$keyToken]['content'];

            $previousTokens = [\T_OPEN_SHORT_ARRAY, \T_COMMA];
            $keyTokenStart = $phpcsFile->findPrevious($previousTokens, $doubleArrowPtr - 1, $ptrOpen) + 1;
            $complexRawKey = \trim($phpcsFile->getTokensAsString($keyTokenStart, $doubleArrowPtr - $keyTokenStart));

            if ($complexRawKey === $rawKey) {
                //This is a single token so we can use the key formatter, better for testing duplicates through types.
                $key = $this->keyFormatter($tokens[$keyToken]);
            } else {
                //Key is too complex with several tokens, only check the contents.
                $key = $complexRawKey;
            }

            //If this key was not already recorded, save it.
            if (false === ($firstOccurrenceLine = \array_search($key, $allKeys, true))) {
                $allKeys[$tokens[$keyToken]['line']] = $key;
                //Finding next from the current token position.
                $ptr = $doubleArrowPtr + 1;
                continue;
            }

            //Otherwise, the key already exists, so add error.
            $error = 'Duplicate array key: %s. First occurrence of this key at line %d.';
            $phpcsFile->addError($error, $keyToken, null, [$rawKey, $firstOccurrenceLine]);

            //Finding next from the current token position.
            $ptr = $doubleArrowPtr + 1;
        }
    }

    /**
     * Return the token position of the opening array depending of the array syntax used.
     *
     * @param array $tokenStack
     * @return int
     */
    private function getOpenPtr(array $tokenStack): int
    {
        return (\T_ARRAY === $tokenStack['code']) ? $tokenStack['parenthesis_opener'] : $tokenStack['bracket_opener'];
    }

    /**
     * Return the token position of the closing array depending of the array syntax used.
     *
     * @param array $tokenStack
     * @return int
     */
    private function getClosePtr(array $tokenStack): int
    {
        return (\T_ARRAY === $tokenStack['code']) ? $tokenStack['parenthesis_closer'] : $tokenStack['bracket_closer'];
    }

    /**
     * Return a formatted legal key depending of the token used and its content.
     * Rules are defined by @link http://php.net/manual/en/language.types.array.php#language.types.array.syntax
     *
     * @param array $tokenStack
     * @return int|string
     */
    private function keyFormatter(array $tokenStack)
    {
        switch ($tokenStack['code']) {
            default:
            case \T_LNUMBER:
                //Integers and others that are not in the switch (like variables) are not formatted
                return (int)$tokenStack['content'];
            case \T_STRING:
            case \T_CONSTANT_ENCAPSED_STRING:
                //Strings may be formatted, as "8" will be cast as 8 but "08" will not.
                $rawString = $tokenStack['content'];
                //Remove the quotes or doubles quotes that are around the raw string.
                $string = \trim($rawString, '\'"');
                //If this string is the same as the equivalent integer, return the integer value.
                return ((string)(int)$string === $string) ? (int)$string : $string;
            case \T_DNUMBER:
                //Float is cast into integers
                return (int)(float)$tokenStack['content'];
            case \T_TRUE:
            case \T_FALSE:
                //Booleans are cast into integers
                return (int)('true' === $tokenStack['content']);
            case \T_NULL:
                //null is cast into empty string
                return '';
        }
    }
}
