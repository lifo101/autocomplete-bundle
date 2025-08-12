<?php

namespace Lifo\AutocompleteBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const string CONFIG_KEY = 'lifo_autocomplete';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::CONFIG_KEY);
        $root = $treeBuilder->getRootNode();

        // @formatter:off
        $root
            ->children()
                ->booleanNode('autoconfigure')
                    ->defaultTrue()
                    ->info('Automatically configure subsystems?')
                ->end()
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
