<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Doctrine\ORM\EntityManagerInterface;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LayerBoundaryTest
{
    /**
     * Domain layer must have zero external dependencies.
     * It must not know about Application, Infrastructure, UI, Symfony, or Doctrine.
     */
    public function test_domain_isolation(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Domain'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Application'),
                Selector::inNamespace('App\Infrastructure'),
                Selector::inNamespace('App\EntryPoint'),
                // Symfony — allowed seams: Security interfaces (User entity) and EventDispatcher contracts
                Selector::inNamespace('Symfony\Bundle'),
                Selector::inNamespace('Symfony\Component\HttpFoundation'),
                Selector::inNamespace('Symfony\Component\Form'),
                Selector::inNamespace('Symfony\Component\Messenger'),
                Selector::inNamespace('Symfony\Component\Routing'),
                Selector::inNamespace('Symfony\Component\DependencyInjection'),
                // Doctrine — allowed seams: ORM\Mapping attributes and Common\Collections; DBAL and EM are forbidden
                Selector::inNamespace('Doctrine\DBAL'),
                Selector::classname(EntityManagerInterface::class),
            )
            ->because('Domain must not depend on Infrastructure, UI, or framework internals (Doctrine ORM\\Mapping + Collections and Symfony Security\\UserInterface + EventDispatcher are accepted framework seams)');
    }

    /**
     * Application layer may depend on Domain, but never on Infrastructure or UI.
     */
    public function test_application_integrity(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Infrastructure'),
                Selector::inNamespace('App\EntryPoint'),
            )
            ->because('Application layer must not know about Infrastructure or UI');
    }

    /**
     * UI must never inject EntityManagerInterface directly
     * and must not depend on Infrastructure internals.
     * It communicates only through Application services or MessageBus.
     */
    public function test_ui_must_not_use_entity_manager(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname(EntityManagerInterface::class),
                Selector::inNamespace('App\Infrastructure'),
            )
            ->because('UI must communicate only through Application services or MessageBus');
    }

    /**
     * Leaf-node classes (Actions, Handlers, ValueObjects) must be final.
     */
    public function test_action_handler_value_object_must_be_final(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('App\EntryPoint\Action'),
                Selector::inNamespace('App\Application\Handler'),
                Selector::inNamespace('App\Domain\ValueObject'),
            )
            ->should()
            ->beFinal()
            ->because('Leaf-node classes must not be extended');
    }

    /**
     * Commands are data carriers — they must be readonly to prevent mutation after dispatch.
     */
    public function test_commands_must_be_readonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application\Command'))
            ->should()
            ->beReadonly()
            ->because('Commands are immutable data carriers — readonly prevents mutation after dispatch');
    }

    /**
     * Value Objects are immutable by definition — enforce readonly.
     */
    public function test_value_objects_must_be_readonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Domain\ValueObject'))
            ->excluding(Selector::isEnum())
            ->should()
            ->beReadonly()
            ->because('Value Objects must be immutable — use readonly classes (enums are inherently immutable)');
    }

    /**
     * Handlers are invokable services — they must implement __invoke.
     */
    public function test_handlers_must_be_invokable(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application\Handler'))
            ->should()
            ->beInvokable()
            ->because('Handlers are invokable — Symfony Messenger requires __invoke');
    }

    /**
     * Single Action Controllers must implement __invoke.
     */
    public function test_action_must_be_invokable(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint\Action'))
            ->should()
            ->beInvokable()
            ->because('Single Action Controllers must implement __invoke');
    }

    /**
     * Every class inside App\EntryPoint\Action must carry the "Action" suffix.
     * Classes that do NOT match /Action$/ should not exist in that namespace.
     */
    public function test_action_naming_convention(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint\Action'))
            ->excluding(Selector::classname('/Action$/', true))
            ->shouldNot()
            ->exist()
            ->because('All classes in App\\UI\\Action must be named with the Action suffix');
    }

    /**
     * UI Layer must only use MessageBus (for writes) or Providers (for reads).
     * It MUST NOT touch Repositories or EntityManager.
     */
    public function test_ui_interaction_restrictions(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint\Action'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Infrastructure\Repository'),
                Selector::implements(EntityManagerInterface::class),
            )
            ->because('UI must use MessageBus for commands or Providers for queries');
    }

    /**
     * EntryPoint must never touch Domain repositories directly.
     * It must go through Application services or MessageBus.
     */
    public function test_entrypoint_must_not_use_repositories(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('App\Domain\Repository'))
            ->because('EntryPoint must go through Application services or MessageBus — never touch repositories directly');
    }

    /**
     * Providers (Read Layer) must be isolated from Domain Logic.
     */
    public function test_providers_isolation(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Infrastructure\Query'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Application\Handler'),
            )
            ->because('Providers are for reading only and should not trigger command handlers');
    }

    /**
     * Application Handlers must be readonly — they have only immutable ctor-injected deps.
     */
    public function test_handlers_must_be_readonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application\Handler'))
            ->should()
            ->beReadonly()
            ->because('Handlers have only immutable constructor-injected dependencies — must be final readonly class');
    }

    /**
     * Symfony Form types in EntryPoint must be final — they are leaf infrastructure classes.
     */
    public function test_form_types_must_be_final(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\EntryPoint\Form'))
            ->excluding(Selector::inNamespace('App\EntryPoint\Form\DataTransformer'))
            ->should()
            ->beFinal()
            ->because('Form types are leaf-node infrastructure classes — they must not be extended');
    }
}
