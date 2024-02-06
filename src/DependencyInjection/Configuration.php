<?php

namespace Lifo\AutocompleteBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;

class Configuration implements ConfigurationInterface
{
    const CONFIG_KEY = 'lifo_autocomplete';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        if (Kernel::VERSION_ID < 40000) {
            $treeBuilder = new TreeBuilder();
            $root = $treeBuilder->root(self::CONFIG_KEY);
        } else {
            $treeBuilder = new TreeBuilder(self::CONFIG_KEY);
            $root = $treeBuilder->getRootNode();
        }

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
