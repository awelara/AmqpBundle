<?php

/*
 * This file is part of the FiveLab AmqpBundle package
 *
 * (c) FiveLab
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The configuration definition for AMQP library.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('fivelab_amqp');

        $rootNode
            ->children()
                ->scalarNode('driver')
                    ->isRequired()
                    ->info('The driver for connect to RabbitMQ.')
                    ->example('extension')
                    ->validate()
                        ->ifNotInArray(['php_extension'])
                        ->thenInvalid('Invalid driver "%s". Available driver: "php_extension".')
                    ->end()
                ->end()

                ->append($this->getRoundRobinDefinition())
                ->append($this->getConnectionsNodeDefinition())
                ->append($this->getExchangesNodeDefinition())
                ->append($this->getQueuesNodeDefinition())
                ->append($this->getConsumersNodeDefinition())
                ->append($this->getPublishersNodeDefinition())
                ->append($this->getMiddlewareNodeDefinition('consumer_'))
                ->append($this->getMiddlewareNodeDefinition('publisher_'))
            ->end();

        return $treeBuilder;
    }

    /**
     * Create round robin definition
     *
     * @return NodeDefinition
     */
    private function getRoundRobinDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('round_robin');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enable')
                    ->defaultValue('%kernel.debug%')
                    ->info('Enable round robin?')
                ->end()

                ->integerNode('executes_messages_per_consumer')
                    ->defaultValue(100)
                    ->info('Count of executes messages per one consumer.')
                ->end()

                ->floatNode('consumers_read_timeout')
                    ->defaultValue(10.0)
                    ->info('The read timeout for one consumer.')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get queues node definition
     *
     * @return NodeDefinition
     */
    private function getQueuesNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('queues');

        $node
            ->defaultValue([])
            ->beforeNormalization()
                ->always(static function ($queues) {
                    if (!\is_array($queues)) {
                        return $queues;
                    }

                    foreach ($queues as $key => $queueInfo) {
                        if (!\array_key_exists('name', $queueInfo)) {
                            $queues[$key]['name'] = $key;
                        }
                    }

                    return $queues;
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('connection')
                    ->isRequired()
                    ->info('The connection for consume queue.')
                ->end()

                ->scalarNode('name')
                    ->isRequired()
                    ->info('The name of queue for consumer.')
                ->end()

                ->booleanNode('durable')
                    ->defaultTrue()
                    ->info('Is durable')
                ->end()

                ->booleanNode('passive')
                    ->defaultFalse()
                    ->info('Is passive?')
                ->end()

                ->booleanNode('exclusive')
                    ->defaultFalse()
                    ->info('Is exclusive?')
                ->end()

                ->booleanNode('auto_delete')
                    ->defaultFalse()
                    ->info('Auto delete queue?')
                ->end()

                ->append($this->getBindingsNodeDefinition('bindings'))
                ->append($this->getBindingsNodeDefinition('unbindings'))

                ->arrayNode('arguments')
                    ->normalizeKeys(false)
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dead-letter-exchange')
                            ->info('Add "x-dead-letter-exchange" argument')
                            ->defaultValue(null)
                        ->end()

                        ->scalarNode('dead-letter-routing-key')
                            ->info('Add "x-dead-letter-routing-key" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->integerNode('expires')
                            ->info('Add "x-expires" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->integerNode('max-length')
                            ->info('Add "x-max-length" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->integerNode('max-length-bytes')
                            ->info('Add "x-max-length-bytes"')
                            ->defaultValue(null)
                        ->end()

                        ->integerNode('max-priority')
                            ->info('Add "x-max-priority" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->integerNode('message-ttl')
                            ->info('Add "x-message-ttl" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->scalarNode('overflow')
                            ->info('Add "x-overflow" argument')
                            ->defaultValue(null)
                            ->validate()
                                ->ifNotInArray(['drop-head', 'reject-publish', 'reject-publish-dlx'])
                                ->thenInvalid('The overflow mode %s is not valid. Available modes: "drop-head", "reject-publish" and "reject-publish-dlx".')
                            ->end()
                        ->end()

                        ->scalarNode('queue-master-locator')
                            ->info('Add "x-queue-master-locator" argument.')
                            ->defaultValue(null)
                            ->validate()
                                ->ifNotInArray(['min-masters', 'client-local', 'random'])
                                ->thenInvalid('The queue master locator %s is not valid. Available locators: "min-masters", "client-local" and "random".')
                            ->end()
                        ->end()

                        ->scalarNode('queue-mode')
                            ->info('Add "x-queue-mode" argument.')
                            ->defaultValue(null)
                            ->validate()
                                ->ifNotInArray(['default', 'lazy'])
                                ->thenInvalid('The queue mode %s is not valid. Available modes: "default" and "lazy".')
                            ->end()
                        ->end()

                        ->scalarNode('queue-type')
                            ->info('Add "x-queue-type" argument.')
                            ->defaultValue(null)
                            ->validate()
                                ->ifNotInArray(['classic', 'quorum'])
                                ->thenInvalid('The queue type %s is not valid. Available types: "classic" and "quorum".')
                            ->end()
                        ->end()

                        ->booleanNode('single-active-consumer')
                            ->info('Add "x-single-active-consumer" argument.')
                            ->defaultValue(null)
                        ->end()

                        ->arrayNode('custom')
                            ->defaultValue([])
                            ->example(['x-my-custom-argument' => 'some'])
                            ->normalizeKeys(false)
                            ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get publishers node definition
     *
     * @return NodeDefinition
     */
    private function getPublishersNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('publishers');

        $node
            ->defaultValue([]);

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('exchange')
                    ->isRequired()
                    ->info('The key of exchange.')
                ->end()

                ->append($this->getMiddlewareNodeDefinition())
            ->end();

        return $node;
    }

    /**
     * Get consumers node definition
     *
     * @return NodeDefinition
     */
    private function getConsumersNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('consumers');

        $node
            ->defaultValue([]);

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('queue')
                    ->isRequired()
                    ->info('The key of queue.')
                ->end()

                ->scalarNode('mode')
                    ->defaultValue('single')
                    ->info('The mode of consumer.')
                    ->validate()
                        ->ifNotInArray(['single', 'spool', 'loop'])
                        ->thenInvalid('The mode %s is not valid. Available modes: "single", "spool" and "loop".')
                    ->end()
                ->end()

                ->arrayNode('message_handlers')
                    ->isRequired()
                    ->info('The list of service ids of message handlers.')
                    ->prototype('scalar')
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(static function ($value) {
                            return !\is_array($value);
                        })
                        ->then(static function ($value) {
                            return [$value];
                        })
                    ->end()
                ->end()

                ->append($this->getMiddlewareNodeDefinition())

                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('requeue_on_error')
                            ->info('Requeue message on error?')
                            ->defaultValue(true)
                        ->end()

                        ->floatNode('read_timeout')
                            ->info('The read timeout (use for loop/spool consumers).')
                            ->defaultValue(300)
                        ->end()

                        ->floatNode('timeout')
                            ->info('The timeout for flush messages (use for spool consumers).')
                            ->defaultValue(30)
                        ->end()

                        ->integerNode('prefetch_count')
                            ->info('The prefetch count or the count messages for flush (use for spool consumers).')
                            ->defaultValue(3)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get exchanges node definition
     *
     * @return NodeDefinition
     */
    private function getExchangesNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('exchanges');

        $node
            ->defaultValue([])
            ->beforeNormalization()
                ->always(static function ($exchanges) {
                    if (!\is_array($exchanges)) {
                        return $exchanges;
                    }

                    foreach ($exchanges as $key => $exchangeInfo) {
                        if (!\array_key_exists('name', $exchangeInfo)) {
                            $exchanges[$key]['name'] = $key;
                        }
                    }

                    return $exchanges;
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('name')
                    ->info('The name of exchange.')
                    ->isRequired()
                ->end()

                ->scalarNode('connection')
                    ->info('The name of connection.')
                    ->isRequired()
                ->end()

                ->scalarNode('type')
                    ->info('The type of exchange.')
                    ->validate()
                        ->ifNotInArray([
                            'direct',
                            'topic',
                            'fanout',
                            'headers',
                        ])
                        ->thenInvalid('Invalid exchange type "%s".')
                    ->end()
                ->end()

                ->booleanNode('durable')
                    ->defaultTrue()
                    ->info('Is durable?')
                ->end()

                ->booleanNode('passive')
                    ->defaultFalse()
                    ->info('Is passive?')
                ->end()

                ->append($this->getBindingsNodeDefinition('bindings'))
                ->append($this->getBindingsNodeDefinition('unbindings'))

                ->arrayNode('arguments')
                    ->normalizeKeys(false)
                    ->children()
                        ->scalarNode('alternate-exchange')
                            ->defaultNull()
                            ->info('Add "alternate-exchange" argument?')
                            ->example('norouted')
                        ->end()

                        ->arrayNode('custom')
                            ->defaultValue([])
                            ->example(['x-my-argument' => 'foo'])
                            ->normalizeKeys(false)
                            ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get connections node definition
     *
     * @return NodeDefinition
     */
    private function getConnectionsNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('connections');

        $node
            ->useAttributeAsKey('name')
            ->requiresAtLeastOneElement()
            ->beforeNormalization()
                ->ifTrue(static function ($value) {
                    return \is_array($value) &&  \array_key_exists('host', $value);
                })
                ->then(static function ($value) {
                    return ['default' => $value];
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->arrayNode('host')
                    ->isRequired()
                    ->beforeNormalization()
                        ->ifTrue(static function ($value) {
                            return !\is_array($value);
                        })
                        ->then(static function ($value) {
                            return [$value];
                        })
                    ->end()
                    ->info('The hosts for connect to RabbitMQ.')
                    ->example('rabbitmq.my-domain.com or [\'rabbitmq-01.my-domoain.com\', \'rabbitmq-02.my-domain.com\']')
                    ->prototype('scalar')
                    ->end()
                ->end()

                ->scalarNode('port')
                    ->defaultValue(5672)
                    ->info('The port for connect to RabbitMQ.')
                ->end()

                ->scalarNode('vhost')
                    ->defaultValue('/')
                    ->info('The virtual host for connect to RabbitMQ.')
                ->end()

                ->scalarNode('login')
                    ->defaultValue('guest')
                    ->info('The login for connect to RabbitMQ.')
                ->end()

                ->scalarNode('password')
                    ->defaultValue('guest')
                    ->info('The password for connect to RabbitMQ.')
                ->end()

                ->scalarNode('read_timeout')
                    ->defaultValue(0)
                    ->info('The read timeout of RabbitMQ.')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get bindings node definition
     *
     * @param string $nodeName
     *
     * @return NodeDefinition
     */
    private function getBindingsNodeDefinition(string $nodeName): NodeDefinition
    {
        $node = new ArrayNodeDefinition($nodeName);

        $node
            ->requiresAtLeastOneElement();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('exchange')
                    ->isRequired()
                    ->info('The exchange for binding.')
                ->end()

                ->scalarNode('routing')
                    ->isRequired()
                    ->info('The routing key for binding.')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Get middleware node
     *
     * @param string $nodePrefix
     *
     * @return NodeDefinition
     */
    private function getMiddlewareNodeDefinition(string $nodePrefix = ''): NodeDefinition
    {
        $node = new ArrayNodeDefinition($nodePrefix.'middleware');

        $node
            ->defaultValue([])
            ->prototype('scalar')
            ->end();

        return $node;
    }
}
