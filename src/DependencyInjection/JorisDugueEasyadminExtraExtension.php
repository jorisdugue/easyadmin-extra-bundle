<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class JorisDugueEasyadminExtraExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('twig')) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [
                \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'templates' => 'JorisDugueEasyAdminExtraBundle',
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('joris_dugue_easyadmin_extra.discovery_paths', $config['discovery_paths']);
        $container->setParameter('joris_dugue_easyadmin_extra.export.action_display', $config['export']['action_display']);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'config'));
        $loader->load('services.php');
    }
}
