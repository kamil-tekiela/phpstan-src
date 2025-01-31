<?php declare(strict_types = 1);

namespace PHPStan\Type\Php;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class PregSplitDynamicReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{

	private ReflectionProvider $reflectionProvider;

	public function __construct(ReflectionProvider $reflectionProvider)
	{
		$this->reflectionProvider = $reflectionProvider;
	}


	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return strtolower($functionReflection->getName()) === 'preg_split';
	}


	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		$flagsArg = $functionCall->getArgs()[3] ?? null;

		if ($this->hasFlag($this->getConstant('PREG_SPLIT_OFFSET_CAPTURE'), $flagsArg, $scope)) {
			$type = new ArrayType(
				new IntegerType(),
				new ConstantArrayType([new ConstantIntegerType(0), new ConstantIntegerType(1)], [new StringType(), new IntegerType()])
			);
			return TypeCombinator::union($type, new ConstantBooleanType(false));
		}

		return ParametersAcceptorSelector::selectSingle($functionReflection->getVariants())->getReturnType();
	}


	private function hasFlag(int $flag, ?Arg $expression, Scope $scope): bool
	{
		if ($expression === null) {
			return false;
		}

		$type = $scope->getType($expression->value);
		return $type instanceof ConstantIntegerType && ($type->getValue() & $flag) === $flag;
	}


	private function getConstant(string $constantName): int
	{
		$constant = $this->reflectionProvider->getConstant(new Name($constantName), null);
		$valueType = $constant->getValueType();
		if (!$valueType instanceof ConstantIntegerType) {
			throw new \PHPStan\ShouldNotHappenException(sprintf('Constant %s does not have integer type.', $constantName));
		}

		return $valueType->getValue();
	}

}
