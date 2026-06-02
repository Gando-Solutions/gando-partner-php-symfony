<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gando_partner');

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('api_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Partner API key (gando_pk_…).')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://gando.app')
                    ->cannotBeEmpty()
                    ->info('Gando API base URL.')
                ->end()
                ->arrayNode('connect')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret')
                            ->defaultNull()
                            ->info('Connect signing secret (gando_cs_…).')
                        ->end()
                        ->scalarNode('partner_slug')
                            ->defaultNull()
                            ->info('Partner slug used in connect URLs.')
                        ->end()
                        ->scalarNode('base_url')
                            ->defaultValue('https://dashboard.gando.app')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('webhooks')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret')
                            ->defaultNull()
                            ->info('Webhook signing secret (gando_whsec_…).')
                        ->end()
                        ->integerNode('tolerance_seconds')
                            ->defaultValue(300)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
