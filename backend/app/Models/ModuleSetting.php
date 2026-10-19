<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Whether a Python module is on or off, set with modules:enable and
 * modules:disable. Overrides the "enabled" in the module's module.json.
 *
 * @property string $module
 * @property bool $enabled
 */
class ModuleSetting extends Model
{
    protected $primaryKey = 'module';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['module', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
