<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;

final class ExportConfigFactoryTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testItUsesDefaultActionDisplayWhenAttributeDoesNotDefineIt(): void
    {
        $factory = new ExportConfigFactory(ExportActionDisplay::DROPDOWN->value);

        $config = $factory->create(DefaultDisplayCrudController::class);

        self::assertSame(ExportActionDisplay::DROPDOWN, $config->actionDisplay);
    }

    /**
     * @throws ReflectionException
     */
    public function testItUsesAttributeActionDisplayWhenDefined(): void
    {
        $factory = new ExportConfigFactory(ExportActionDisplay::DROPDOWN->value);

        $config = $factory->create(ButtonsDisplayCrudController::class);

        self::assertSame(ExportActionDisplay::BUTTONS, $config->actionDisplay);
    }

    /**
     * @throws ReflectionException
     */
    public function testItFallsBackToButtonsWhenFactoryDefaultIsButtons(): void
    {
        $factory = new ExportConfigFactory(ExportActionDisplay::BUTTONS->value);

        $config = $factory->create(DefaultDisplayCrudController::class);

        self::assertSame(ExportActionDisplay::BUTTONS, $config->actionDisplay);
    }
}

#[AdminExport(
    formats: ['csv'],
)]
final class DefaultDisplayCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }

    public static function getExportFields(): array
    {
        return [];
    }
}

#[AdminExport(
    formats: ['csv'],
    actionDisplay: ExportActionDisplay::BUTTONS,
)]
final class ButtonsDisplayCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }

    public static function getExportFields(): array
    {
        return [];
    }
}
