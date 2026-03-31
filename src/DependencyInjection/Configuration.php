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
     *
     * @return TreeBuilder The configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('joris_dugue_easyadmin_extra');

        $treeBuilder->getRootNode()
            ->children()

            /*
             * Defines the directories where the bundle will scan for PHP classes.
             *
             * These directories are used to:
             * - Discover EasyAdmin dashboards (#[AdminDashboard])
             * - Discover CRUD controllers with export enabled (#[AdminExport])
             *
             * Important:
             * - Must be a flat list of strings (absolute or parameter-based paths)
             * - Each path should point to a directory
             *
             * Default:
             * - "%kernel.project_dir%/src/Controller"
             *
             * Recommended usages:
             * - Custom admin folder: "src/Admin"
             * - Modular architecture: "modules/"
             * - Domain-driven structure
             */
            ->arrayNode('discovery_paths')
            ->scalarPrototype()->end()
            ->defaultValue([
                '%kernel.project_dir%/src/Controller',
            ])
            ->validate()
            ->ifTrue(fn ($paths) => array_filter($paths, fn ($p) => !\is_string($p)) !== [])
            ->thenInvalid('The "discovery_paths" option must be a list of strings.')
            ->end()
            ->end();

        return $treeBuilder;
    }
}
