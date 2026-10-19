<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Where a module's widget is shown, set with modules:place: a hook from
 * config/modules.php, or "hidden". Overrides the "hook" in module.json.
 *
 * @property string $widget The module's id and the widget's name, e.g. "news.latest"
 * @property string $hook
 */
class ModuleWidgetSetting extends Model
{
    protected $primaryKey = 'widget';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['widget', 'hook'];
}
