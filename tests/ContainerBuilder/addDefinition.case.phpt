<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerBuilder: case sensitivity
 */

use Nette\DI\ContainerBuilder;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('one');
	$builder->addDefinition('One');
}, Nette\InvalidStateException::class, "Service 'One' has the same name as 'one' in a case-insensitive manner.");


// removed definition frees its name
$builder = new ContainerBuilder;
$builder->addDefinition('one');
$builder->removeDefinition('one');
Assert::type(Nette\DI\Definitions\ServiceDefinition::class, $builder->addDefinition('One'));


test('removing a differently cased name preserves the collision check', function () {
	$builder = new ContainerBuilder;
	$definition = $builder->addDefinition('Foo');
	Assert::exception(
		fn() => $builder->addDefinition('Foo'),
		Nette\InvalidStateException::class,
		"Service 'Foo' has already been added.",
	);
	$builder->removeDefinition('foo');
	Assert::same($definition, $builder->getDefinition('Foo'));
	Assert::exception(
		fn() => $builder->addDefinition('foo'),
		Nette\InvalidStateException::class,
		"Service 'foo' has the same name as 'Foo' in a case-insensitive manner.",
	);
});


test('removal through an alias frees the indexed service name', function () {
	$builder = new ContainerBuilder;
	$builder->add('Foo', Nette\DI\imported(stdClass::class));
	$builder->addAlias('alias', 'Foo');
	Assert::exception(
		fn() => $builder->add('alias', Nette\DI\imported(stdClass::class)),
		Nette\InvalidStateException::class,
		"Service 'Foo' has already been added.",
	);
	$builder->remove('alias');
	Assert::false($builder->has('Foo'));
	Assert::same('foo', $builder->add('foo', Nette\DI\imported(stdClass::class))->getName());
	$builder->remove('foo');
	Assert::same('FOO', $builder->add('FOO', Nette\DI\imported(stdClass::class))->getName());
});


test('the built-in container name is indexed and can be removed', function () {
	$builder = new ContainerBuilder;
	Assert::exception(
		fn() => $builder->add('Container', Nette\DI\imported(stdClass::class)),
		Nette\InvalidStateException::class,
		"Service 'Container' has the same name as 'container' in a case-insensitive manner.",
	);
	$builder->remove('container');
	Assert::same('Container', $builder->add('Container', Nette\DI\imported(stdClass::class))->getName());
});


test('anonymous names survive removals and aliases', function () {
	$builder = new ContainerBuilder;
	Assert::same('01', $builder->addDefinition(null)->getName());
	Assert::exception(
		fn() => $builder->addDefinition('01'),
		Nette\InvalidStateException::class,
		"Service '01' has already been added.",
	);
	$builder->removeDefinition('missing');
	$builder->remove('01');
	Assert::same('01', $builder->addDefinition(null)->getName());
	Assert::same('02', $builder->addDefinition(null)->getName());
	$builder->addAlias('03', '01');
	Assert::same('04', $builder->addDefinition(null)->getName());
	$builder->remove('03');
	Assert::same('01', $builder->addDefinition(null)->getName());
});
