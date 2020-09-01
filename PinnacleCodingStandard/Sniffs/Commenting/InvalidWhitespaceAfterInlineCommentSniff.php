<?php

namespace PinnacleCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * A sniff to check for incorrect whitespace on inline comments.
 */
class InvalidWhitespaceAfterInlineCommentSniff implements Sniff
{
    /**
     * The name of the sniff.
     */
    private const NAME = 'InvalidWhitespaceAfterInlineComment';

    public function register()
    {
        return [
            T_COMMENT,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $commentText = $tokens[$stackPtr]['content'];

        if ($commentText === null) {
            // Shouldn't happen, but check added to be safe.
            return;
        }

        $commentDeclaration = substr($commentText, 0, 2);
        if ($commentDeclaration !== '//') {
            // Not an in-line comment
            return;
        }

        if (preg_match('~//\s\S+~', $commentText)) {
            // Comment matches expected format.
            return;
        }

        $shouldFix = $phpcsFile->addFixableError(
            'Inline comments must have a single space between // and comment.',
            $stackPtr,
            self::NAME
        );

        if (!$shouldFix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($stackPtr, $commentDeclaration . ' ' . substr($commentText, 2));
        $phpcsFile->fixer->endChangeset();
    }
}
