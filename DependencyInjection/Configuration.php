<?php

namespace Yaso\Bundle\EntityRatingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('yaso_entity_rating_bundle');
        $rootNode
            ->children()
                ->scalarNode('entity_rating_class')
                ->end()
                ->scalarNode('entity_rating_manager_service')
                    ->defaultValue('yaso.entity_rating_bundle.manager')
                ->end()
                ->integerNode('rate_by_ip_limitation')
                    ->defaultValue(10)
                ->end()
                ->arrayNode('map_type_to_class')
                    ->prototype('scalar')->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
