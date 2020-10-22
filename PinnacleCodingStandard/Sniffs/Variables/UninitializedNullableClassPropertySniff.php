<?php

declare(strict_types=1);

namespace PinnacleCodingStandard\Sniffs\Variables;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks for any nullable class properties with strict types that are not properly initialized.
 */
class UninitializedNullableClassPropertySniff implements Sniff
{
    /**
     * The name of the sniff.
     */
    private const NAME = 'UninitializedNullableClassProperty';

    /**
     * @var bool
     */
    private $checkedForConstructor;

    /**
     * @var int|null
     */
    private $constructorPointer;

    public function __construct()
    {
        $this->constructorPointer    = null;
        $this->checkedForConstructor = false;
    }

    public function register(): array
    {
        return [
            T_PRIVATE,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        // Find the pointer to the constructor if we haven't done so already.
        if (!$this->checkedForConstructor) {
            $this->constructorPointer = $this->findConstructorPointer($phpcsFile);
        }

        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];
        if (!$token['code'] === T_PRIVATE) {
            return;
        }

        // Check to see if the token is a variable that has a static type and is nullable.
        $endOfDeclarationPointer = $phpcsFile->findEndOfStatement($stackPtr);
        if (!$this->isNullableVariable($phpcsFile, $stackPtr, $endOfDeclarationPointer)) {
            return;
        }

        // Check to see if the variable is initialized on declaration.
        if ($this->isNullableVariableInitializedInDeclaration($phpcsFile, $stackPtr, $endOfDeclarationPointer)) {
            return;
        }

        // Parse the property name
        $propertyNamePointer = $this->findVariableNamePointer($phpcsFile, $stackPtr, $endOfDeclarationPointer);
        $propertyName        = str_replace('$', '', $tokens[$propertyNamePointer]['content']);

        if ($this->constructorPointer !== null) {
            // Check if the property that was not assigned on declaration is assigned in the constructor.
            $endOfFunctionPointer      = $phpcsFile->findEndOfStatement($this->constructorPointer);
            $closingParenthesisPointer = $phpcsFile->findNext(
                [T_CLOSE_PARENTHESIS],
                $this->constructorPointer,
                $endOfFunctionPointer
            );

            // This should never happen unless there's a syntax error.
            if ($closingParenthesisPointer === false) {
                return;
            }

            $this->checkIfVariableInitializedInConstructor(
                $phpcsFile,
                $closingParenthesisPointer,
                $endOfFunctionPointer,
                $stackPtr,
                $propertyName
            );
        } else {
            // If there's no constructor, add an error for the class property that was not assigned when declared.
            $phpcsFile->addError('Uninitialized nullable class property.', $stackPtr, self::NAME);
        }
    }

    /**
     * Returns a boolean indicating if the token at the specified pointer is a variable with a nullable static type.
     */
    private function isNullableVariable(File $phpcsFile, int $stackPtr, int $endOfDeclarationPointer): bool
    {
        $nullablePtr = $phpcsFile->findNext([T_NULLABLE], $stackPtr + 1, $endOfDeclarationPointer);
        if (!$nullablePtr) {
            return false;
        }

        // If we find an open parenthesis, the value we're checking is not a class property.
        $openParenthesisPtr = $phpcsFile->findNext([T_OPEN_PARENTHESIS], $stackPtr + 1, $endOfDeclarationPointer);
        if ($openParenthesisPtr !== false) {
            return false;
        }

        if ($this->findVariableNamePointer($phpcsFile, $stackPtr, $endOfDeclarationPointer) === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the pointer to the variable name in the class property declaration at the specified pointer, or false if
     * none exists.
     *
     * @return false|int
     */
    private function findVariableNamePointer(File $phpcsFile, int $stackPtr, int $endOfDeclarationPointer)
    {
        return $phpcsFile->findNext([T_VARIABLE], $stackPtr, $endOfDeclarationPointer);
    }

    /**
     * Checks to see if the nullable class property token at the specified pointer is initialized when it's declared.
     */
    private function isNullableVariableInitializedInDeclaration(
        File $phpcsFile,
        int $stackPtr,
        int $endOfDeclarationPointer
    ): bool {
        $assignmentOperatorPtr = $phpcsFile->findNext([T_EQUAL], $stackPtr + 1, $endOfDeclarationPointer);
        if ($assignmentOperatorPtr === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the pointer to the constructor function, or null if none exists.
     */
    private function findConstructorPointer(File $phpcsFile): ?int
    {
        $this->checkedForConstructor = true;

        foreach ($phpcsFile->getTokens() as $stackPtr => $token) {
            if ($token['code'] !== T_FUNCTION) {
                continue;
            }

            $endOfFunctionPointer = $phpcsFile->findEndOfStatement($stackPtr);
            if ($this->isConstructor($phpcsFile, $stackPtr, $endOfFunctionPointer)) {
                return $stackPtr;
            }
        }

        return null;
    }

    /**
     * Returns a boolean indicating if the function at the specified pointer is a constructor.
     */
    private function isConstructor(File $phpcsFile, int $stackPtr, int $endOfFunctionPointer): bool
    {
        $functionNamePtr = $phpcsFile->findNext([T_STRING], $stackPtr, $endOfFunctionPointer);
        if ($functionNamePtr === null) {
            return false;
        }

        $functionName = $phpcsFile->getTokens()[$functionNamePtr]['content'];

        return trim($functionName) === '__construct';
    }

    /**
     * Checks to see if the property with the specified name is initialized in the constructor, and adds an error if
     * it isn't.
     */
    private function checkIfVariableInitializedInConstructor(
        File $phpcsFile,
        int $constructorBodyPointer,
        int $endOfConstructorPointer,
        int $propertyPointer,
        string $propertyName
    ): void {
        $tokens                  = $phpcsFile->getTokens();
        $lastVariablePointer     = $constructorBodyPointer;
        $assignmentFound         = false;
        do {
            // Find the next pointer for a variable within the constructor body.
            $variablePointer     = $phpcsFile->findNext(
                [T_VARIABLE],
                $lastVariablePointer + 1,
                $endOfConstructorPointer
            );
            $lastVariablePointer = $variablePointer;
            if ($variablePointer === false) {
                break;
            }

            // If the variable doesn't reference the implicit parameter, it's not a property assignment.
            if ($tokens[$variablePointer]['content'] !== '$this') {
                continue;
            }

            // Check for a property name after the implicit parameter.
            $endOfStatementPointer = $phpcsFile->findEndOfStatement($variablePointer);
            $propertyNamePointer   = $phpcsFile->findNext(
                [T_STRING],
                $variablePointer,
                $endOfStatementPointer
            );
            if ($propertyNamePointer === false) {
                continue;
            }

            // Check to see if the property name matches the class property we're checking.
            $foundPropertyName = $tokens[$propertyNamePointer]['content'];
            if ($propertyName !== $foundPropertyName) {
                continue;
            }

            // Check to see if the statement is assigning a value to the property.
            $assignmentOperatorPointer = $phpcsFile->findNext(
                [T_EQUAL],
                $propertyNamePointer,
                $endOfStatementPointer
            );
            if ($assignmentOperatorPointer === false) {
                continue;
            }

            $assignmentFound = true;
        } while ($variablePointer !== false && !$assignmentFound);

        if (!$assignmentFound) {
            $phpcsFile->addError('Uninitialized nullable class property.', $propertyPointer, self::NAME);
        }
    }
}
