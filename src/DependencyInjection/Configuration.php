<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('rehearsal');
        $tree->getRootNode()
            ->children()
                ->scalarNode('changes_file')->defaultValue('%kernel.project_dir%/.rehearsal/changes.json')->info('the proposed changes (JSON)')->end()
                ->scalarNode('decisions_file')->defaultValue('%kernel.project_dir%/var/rehearsal/decisions.json')->info('where decisions are written')->end()
                ->enumNode('validation')->values(['manual', 'auto'])->defaultValue('manual')->info('manual: wait for the click; auto: validate when the tests pass')->end()
                ->scalarNode('role')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                ->scalarNode('parent_template')->defaultValue('@Rehearsal/base.html.twig')->info('a template with "title" and "body" blocks')->end()
                ->arrayNode('mailer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('from')->defaultNull()->end()
                        ->arrayNode('to')->scalarPrototype()->end()->end()
                        ->scalarNode('subject_prefix')->defaultValue('[Rehearsal]')->end()
                        ->scalarNode('locale')->defaultValue('en')->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
