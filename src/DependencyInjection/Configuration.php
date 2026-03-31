<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines and validates the configuration of the EasyAdmin Extra Bundle.
 *
 * This configuration allows developers to customize how the bundle discovers
 * EasyAdmin dashboards and CRUD controllers within the application.
 *
 * Main goals:
 * - Remove assumptions about project structure (e.g. src/Controller)
 * - Support custom architectures (DDD, modular apps, packages, etc.)
 * - Enable multi-dashboard setups without relying on fixed paths
 *
 * Example configuration:
 *
 * ```yaml
 * joris_dugue_easyadmin_extra:
 *   export:
 *     action_display: dropdown
 *
 *   discovery_paths:
 *     - '%kernel.project_dir%/src/Controller'
 *     - '%kernel.project_dir%/src/Admin'
 *     - '%kernel.project_dir%/modules'
 * ```
 *
 * Behavior:
 * - Each path must be a valid directory
 * - All PHP classes inside these directories are scanned
 * - Classes are then filtered to detect:
 *     - EasyAdmin dashboards (#[AdminDashboard])
 *     - Exportable CRUD controllers (#[AdminExport])
 *
 * Notes:
 * - Defaults to "src/Controller" to match standard Symfony structure
 * - The bundle does NOT require any specific folder structure
 * - This makes the bundle compatible with complex / enterprise setups
 *
 * @author Joris Dugué
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * Builds the configuration tree for the bundle.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('joris_dugue_easyadmin_extra');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode('export')
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('action_display')
            ->values(['buttons', 'dropdown'])
            ->defaultValue('buttons')
            ->end()
            ->end()
            ->end()
            ->arrayNode('discovery_paths')
            ->scalarPrototype()->end()
            ->defaultValue([
                '%kernel.project_dir%/src/Controller',
            ])
            ->validate()
            ->ifTrue(static fn (mixed $paths): bool => [] !== array_filter(
                $paths,
                static fn (mixed $path): bool => !\is_string($path)
            ))
            ->thenInvalid('The "discovery_paths" option must be a list of strings.')
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
