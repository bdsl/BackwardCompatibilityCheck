<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility;

use Psl\Dict;
use Psl\Regex;
use Psl\Str;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\ClassBased\ClassBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\EnumBased\EnumBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\InterfaceBased\InterfaceBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\TraitBased\TraitBased;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\Reflector\Reflector;

final class CompareClasses implements CompareApi
{
    public function __construct(
        private ClassBased $classBasedComparisons,
        private InterfaceBased $interfaceBasedComparisons,
        private TraitBased $traitBasedComparisons,
        private EnumBased|null $enumBasedComparisons = null, // optional argument for now to avoid BC break
    ) {
    }

    public function __invoke(
        Reflector $definedSymbols,
        Reflector $pastSourcesWithDependencies,
        Reflector $newSourcesWithDependencies,
    ): Changes {
        $definedApiClassNames = Dict\map(
            Dict\filter(
                $definedSymbols->reflectAllClasses(),
                function (ReflectionClass $class): bool {
                    return ! ($class->isAnonymous() || $this->isInternalDocComment($class->getDocComment()));
                },
            ),
            static function (ReflectionClass $class): string {
                return $class->getName();
            },
        );

        return Changes::fromIterator($this->makeSymbolsIterator(
            $definedApiClassNames,
            $pastSourcesWithDependencies,
            $newSourcesWithDependencies,
        ));
    }

    /**
     * @param string[] $definedApiClassNames
     *
     * @return iterable<int, Change>
     */
    private function makeSymbolsIterator(
        array $definedApiClassNames,
        Reflector $pastSourcesWithDependencies,
        Reflector $newSourcesWithDependencies,
    ): iterable {
        foreach ($definedApiClassNames as $apiClassName) {
            $oldSymbol = $pastSourcesWithDependencies->reflectClass($apiClassName);

            yield from $this->examineSymbol($oldSymbol, $newSourcesWithDependencies);
        }
    }

    /** @return iterable<int, Change> */
    private function examineSymbol(
        ReflectionClass $oldSymbol,
        Reflector $newSourcesWithDependencies,
    ): iterable {
        try {
            $newClass = $newSourcesWithDependencies->reflectClass($oldSymbol->getName());
        } catch (IdentifierNotFound) {
            yield Change::removed(Str\format('Class %s has been deleted', $oldSymbol->getName()));

            return;
        }

        if ($oldSymbol->isInterface()) {
            yield from ($this->interfaceBasedComparisons)($oldSymbol, $newClass);

            return;
        }

        if ($oldSymbol->isTrait()) {
            yield from ($this->traitBasedComparisons)($oldSymbol, $newClass);

            return;
        }

        if ($oldSymbol->isEnum() && $this->enumBasedComparisons) {
            yield from ($this->enumBasedComparisons)($oldSymbol, $newClass);

            return;
        }

        yield from ($this->classBasedComparisons)($oldSymbol, $newClass);
    }

    private function isInternalDocComment(string|null $comment): bool
    {
        return $comment !== null
            && Regex\matches($comment, '/\s+@internal\s+/');
    }
}
