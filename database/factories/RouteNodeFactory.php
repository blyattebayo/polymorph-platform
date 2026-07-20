<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

/**
 * Р¤Р°Р±СЂРёРєР° РґР»СЏ РјРѕРґРµР»Рё RouteNode.
 *
 * @extends Factory<RouteNode>
 */
class RouteNodeFactory extends Factory
{
    protected $model = RouteNode::class;

    /**
     * РћРїСЂРµРґРµР»РёС‚СЊ СЃРѕСЃС‚РѕСЏРЅРёРµ РјРѕРґРµР»Рё РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'sort_order' => 0,
            'enabled' => true,
            'kind' => RouteNodeKind::ROUTE,
            'name' => null,
            'domain' => null,
            'prefix' => null,
            'namespace' => null,
            'methods' => ['GET'],
            'uri' => fake()->slug(),
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action_meta' => [
                'action' => 'Polymorph\\Platform\\Domain\\Records\\Http\\Controllers\\RecordController@index',
            ],
            'middleware' => null,
            'where' => null,
            'defaults' => null,
        ];
    }

    /**
     * РЈРєР°Р·Р°С‚СЊ, С‡С‚Рѕ СѓР·РµР» СЏРІР»СЏРµС‚СЃСЏ РіСЂСѓРїРїРѕР№.
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RouteNodeKind::GROUP,
            'methods' => null,
            'uri' => null,
            'action_meta' => null,
            'prefix' => fake()->slug(),
        ]);
    }

    /**
     * РЈРєР°Р·Р°С‚СЊ, С‡С‚Рѕ СѓР·РµР» СЏРІР»СЏРµС‚СЃСЏ РјР°СЂС€СЂСѓС‚РѕРј.
     */
    public function route(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RouteNodeKind::ROUTE,
            'methods' => ['GET'],
            'uri' => fake()->slug(),
            'action_meta' => [
                'action' => 'Polymorph\\Platform\\Domain\\Records\\Http\\Controllers\\RecordController@index',
            ],
        ]);
    }

    /**
     * РЈРєР°Р·Р°С‚СЊ СЂРѕРґРёС‚РµР»СЊСЃРєРёР№ СѓР·РµР».
     *
     * @param  RouteNode  $parent  Р РѕРґРёС‚РµР»СЊСЃРєРёР№ СѓР·РµР»
     */
    public function withParent(RouteNode $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * РЈРєР°Р·Р°С‚СЊ, С‡С‚Рѕ СѓР·РµР» РІРєР»СЋС‡С‘РЅ.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    /**
     * РЈРєР°Р·Р°С‚СЊ, С‡С‚Рѕ СѓР·РµР» РІС‹РєР»СЋС‡РµРЅ.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
