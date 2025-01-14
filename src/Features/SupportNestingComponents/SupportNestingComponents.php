<?php

namespace Livewire\Features\SupportNestingComponents;

use function Livewire\trigger;
use function Livewire\store;
use function Livewire\on;

use Livewire\Mechanisms\HandleComponents\Synthesizers\LivewireSynth;
use Livewire\ComponentHook;
use Livewire\Drawer\Utils;

class SupportNestingComponents extends ComponentHook
{
    static function provide()
    {
        on('pre-mount', function ($name, $params, $key, $parent, $hijack) {
            // If this has already been rendered spoof it...
            if ($parent && static::hasPreviouslyRenderedChild($parent, $key)) {
                [$tag, $childId] = static::getPreviouslyRenderedChild($parent, $key);

                $finish = trigger('mount.stub', $tag, $childId, $params, $parent, $key);

                $html = "<{$tag} wire:id=\"{$childId}\"></{$tag}>";

                static::setParentChild($parent, $key, $tag, $childId);

                $hijack($finish($html));
            }
        });

        on('mount', function ($component, $params, $key, $parent) {
            $start = null;
            if ($parent && config('app.debug')) $start = microtime(true);

            static::setParametersToMatchingProperties($component, $params);

            return function ($html) use ($component, $key, $parent, $start) {
                if ($parent) {
                    if (config('app.debug')) trigger('profile', 'child:'.$component->getId(), $parent->getId(), [$start, microtime(true)]);

                    preg_match('/<([a-zA-Z0-9\-]*)/', $html, $matches, PREG_OFFSET_CAPTURE);
                    $tag = $matches[1][0];
                    static::setParentChild($parent, $key, $tag, $component->getId());
                }
            };
        });
    }

    function hydrate($memo)
    {
        $children = $memo['children'];

        $this->setPreviouslyRenderedChildren($this->component, $children);
    }

    function dehydrate($context)
    {
        $skipRender = $this->storeGet('skipRender');

        if ($skipRender) $this->keepRenderedChildren();

        $context->addMemo('children', $this->getChildren());
    }

    function getChildren() { return $this->storeGet('children', []); }
    function setChild($key, $tag, $id) { $this->storePush('children', [$tag, $id], $key); }

    static function setParentChild($parent, $key, $tag, $id) { store($parent)->push('children', [$tag, $id], $key); }
    static function setPreviouslyRenderedChildren($component, $children) { store($component)->set('previousChildren', $children); }
    static function hasPreviouslyRenderedChild($parent, $key) {
        return in_array($key, array_keys(store($parent)->get('previousChildren', [])));
    }

    static function getPreviouslyRenderedChild($parent, $key)
    {
        return store($parent)->get('previousChildren')[$key];
    }

    function keepRenderedChildren()
    {
        $this->storeSet('children', $this->storeGet('previousChildren'));
    }

    static function setParametersToMatchingProperties($component, $params)
    {
        // Assign all public component properties that have matching parameters.
        collect(array_intersect_key($params, Utils::getPublicPropertiesDefinedOnSubclass($component)))
            ->each(function ($value, $property) use ($component) {
                $component->{$property} = $value;
            });
    }
}
