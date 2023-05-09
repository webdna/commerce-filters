<?php

namespace webdna\commerce\filters;

use Craft;
use craft\base\Plugin;
use webdna\commerce\filters\services\Filters;
use webdna\commerce\filters\variables\Filters as CommerceFiltersVariable;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * Commerce Filters plugin
 *
 * @method static CommerceFilters getInstance()
 * @author webdna <info@webdna.co.uk>
 * @copyright webdna
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read Filters $filters
 */
class CommerceFilters extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => ['filters' => Filters::class],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
            
        Event::on(
            CraftVariable::class, 
            CraftVariable::EVENT_INIT, 
            function(Event $event) {
                $variable = $event->sender;
                $variable->set('commerceFilters', CommerceFiltersVariable::class);
            }
        );
    }
}
