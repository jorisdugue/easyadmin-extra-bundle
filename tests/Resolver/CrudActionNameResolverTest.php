<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CrudActionNameResolverTest extends TestCase
{
    public function testResolveReturnsAttributeActionWhenPresent(): void
    {
        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'detail');
        $request->query->set(EA::CRUD_ACTION, 'edit');

        $resolver = new CrudActionNameResolver();

        self::assertSame('detail', $resolver->resolve($request));
    }

    public function testResolveFallsBackToQueryActionWhenAttributeIsMissing(): void
    {
        $request = new Request();
        $request->query->set(EA::CRUD_ACTION, 'new');

        $resolver = new CrudActionNameResolver();

        self::assertSame('new', $resolver->resolve($request));
    }

    public function testResolveReturnsIndexWhenNoActionWasProvided(): void
    {
        $request = new Request();

        $resolver = new CrudActionNameResolver();

        self::assertSame('index', $resolver->resolve($request));
    }
}
