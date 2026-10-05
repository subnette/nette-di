<?php declare(strict_types=1);

/**
 * Test: Resolver completion uses strict definition identity, not name or alias existence.
 */

use Nette\DI;
use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class MembershipProbe extends DI\Definition
{
	public ?DI\Definition $currentService = null;


	public function __construct()
	{
		$this->setType(stdClass::class);
	}


	public function resolveType(Resolver $resolver): void
	{
	}


	public function complete(Resolver $resolver): void
	{
		$this->currentService = $resolver->getCurrentService();
		Assert::same(stdClass::class, $resolver->getCurrentServiceType());
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return '';
	}
}


class AutowiringProbe extends MembershipProbe
{
	public ?DI\Expressions\Reference $reference = null;


	public function complete(Resolver $resolver): void
	{
		parent::complete($resolver);
		$this->reference = $resolver->withCurrentServiceAvailable()->getByType(stdClass::class);
	}
}


interface MembershipFactory
{
	public function create(): stdClass;
}


test('registered, removed, replaced and alias-named definitions', function () {
	$builder = new DI\ContainerBuilder;
	$resolver = new Resolver($builder);
	$registered = $builder->add('registered', new MembershipProbe);
	$resolver->completeDefinition($registered);
	Assert::same($registered, $registered->currentService);
	$anonymous = $builder->add(null, new MembershipProbe);
	$resolver->completeDefinition($anonymous);
	Assert::same($anonymous, $anonymous->currentService);

	$builder->remove('registered');
	$resolver->completeDefinition($registered);
	Assert::null($registered->currentService);
	$replacement = $builder->add('registered', new MembershipProbe);
	$resolver->completeDefinition($registered);
	Assert::null($registered->currentService);
	$resolver->completeDefinition($replacement);
	Assert::same($replacement, $replacement->currentService);

	$detached = (new MembershipProbe)->setName('registered');
	$resolver->completeDefinition($detached);
	Assert::null($detached->currentService);
	$clone = clone $replacement;
	Assert::null($clone->getName(throw: false));
	$clone->setName('registered');
	$resolver->completeDefinition($clone);
	Assert::null($clone->currentService);

	$builder->addAlias('alias', 'registered');
	$alias = (new MembershipProbe)->setName('alias');
	$resolver->completeDefinition($alias);
	Assert::null($alias->currentService);
	Assert::null($resolver->getCurrentService());
});


test('membership does not use alias-aware lookup methods', function () {
	$builder = new class extends DI\ContainerBuilder {
		public function hasDefinition(string $name): bool
		{
			throw new RuntimeException('Unexpected hasDefinition() call.');
		}


		public function getDefinition(string $name): DI\Definition
		{
			throw new RuntimeException('Unexpected getDefinition() call.');
		}
	};
	$registered = $builder->add('registered', new MembershipProbe);
	(new Resolver($builder))->completeDefinition($registered);
	Assert::same($registered, $registered->currentService);
});


test('unnamed definitions retain local autowiring and explicit contexts remain unchanged', function () {
	$builder = new DI\ContainerBuilder;
	$resolver = new Resolver($builder);
	$unnamed = new MembershipProbe;
	$resolver->completeDefinition($unnamed);
	Assert::same($unnamed, $unnamed->currentService);
	$detached = (new MembershipProbe)->setName('detached');
	Assert::same($detached, $resolver->withCurrentService($detached)->getCurrentService());
	Assert::null($resolver->getCurrentService());
});


test('factory results retain their unnamed local context', function () {
	$builder = new DI\ContainerBuilder;
	$result = new MembershipProbe;
	$factory = $builder->addFactoryDefinition('factory')
		->setImplement(MembershipFactory::class)
		->setResultDefinition($result);
	$builder->resolve();
	$resolver = new Resolver($builder);
	$resolver->completeDefinition($factory);
	Assert::null($result->getName(throw: false));
	Assert::same($result, $result->currentService);
	Assert::null($resolver->getCurrentService());
});


test('detached setup autowiring uses the registered service instead of self', function () {
	$builder = new DI\ContainerBuilder;
	$registered = $builder->add('registered', new AutowiringProbe);
	$builder->resolve();
	$resolver = new Resolver($builder);
	$resolver->completeDefinition($registered);
	Assert::true($registered->reference->isSelf());

	$detached = (new AutowiringProbe)->setName('registered');
	$resolver->completeDefinition($detached);
	Assert::same('registered', $detached->reference->getValue());
	Assert::false($detached->reference->isSelf());
	$unnamed = new AutowiringProbe;
	$resolver->completeDefinition($unnamed);
	Assert::true($unnamed->reference->isSelf());

	Assert::exception(
		fn() => $resolver->withCurrentService($registered)->getByType(stdClass::class),
		DI\MissingServiceException::class,
	);
	Assert::null($resolver->getCurrentService());
});
