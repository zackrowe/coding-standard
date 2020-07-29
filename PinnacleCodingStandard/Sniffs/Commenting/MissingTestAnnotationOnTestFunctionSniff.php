<?php

namespace PinnacleCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

/**
 * A sniff to check for test functions missing a @test annotation.
 */
class MissingTestAnnotationOnTestFunctionSniff implements Sniff
{
    /**
     * The name of the sniff.
     */
    private const NAME = 'MissingTestAnnotationOnTestFunction';

    public function register(): array
    {
        return [
            T_FUNCTION,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $namespace    = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);
        $functionName = FunctionHelper::getName($phpcsFile, $stackPtr);

        if (!$this->looksLikeTestFunction($namespace, $functionName)) {
            // Not a test function, don't bother checking for annotation.
            return;
        }

        $docComment = DocCommentHelper::getDocComment($phpcsFile, $stackPtr);

        if ($docComment !== null && preg_match('/\s+@test$/m', $docComment)) {
            // Found @test annotation, no need to add error.
            return;
        }

        // Found a test function without a @test annotation, add an error.
        $phpcsFile->addError(
            sprintf(
                '%s %s() looks like a test but is missing the @test annotation.',
                FunctionHelper::getTypeLabel($phpcsFile, $stackPtr),
                FunctionHelper::getFullyQualifiedName($phpcsFile, $stackPtr)
            ),
            $stackPtr,
            self::NAME
        );
    }

    /**
     * Whether the specified function name looks like a test function according to our format.
     */
    private function looksLikeTestFunction(string $namespace, string $functionName): bool
    {
        if (substr($namespace, 0, 6) !== 'Tests\\') {
            // Function doesn't belong to a class in the Tests\ namespace.
            return false;
        }

        // Check if the function matches our naming convention for tests: unitUnderTest_Scenario_ExpectedResult().
        return (bool)preg_match('/^[a-zA-Z0-9]+_[a-zA-Z0-9]+_[a-zA-Z0-9]+$/', $functionName);
    }
}
