<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer whose maintenance work is billed.
 *
 * @property int $id
 * @property string $name
 * @property string|null $business_id
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $billing_street_address
 * @property string|null $billing_postal_code
 * @property string|null $billing_city
 * @property int $payment_terms_days
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'business_id',
        'contact_person',
        'email',
        'phone',
        'billing_street_address',
        'billing_postal_code',
        'billing_city',
        'payment_terms_days',
        'notes',
    ];

    protected $attributes = [
        'payment_terms_days' => 14,
    ];

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
