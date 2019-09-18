<?php

namespace Coyote\Job;

use Coyote\Country;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $job_id
 * @property string $city
 * @property double $latitude
 * @property double $longitude
 * @property Country $country
 * @property string $street
 * @property string $street_number
 */
class Location extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'job_locations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['city', 'latitude', 'longitude', 'street', 'country', 'street_number'];

    /**
     * @var array
     */
    protected $attributes = ['city' => '', 'latitude' => null, 'longitude' => null];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function job()
    {
        return $this->belongsTo('Coyote\Job');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('Coyote\Country')->withDefault();
    }

    /**
     * @param string $city
     */
    public function setCityAttribute($city)
    {
        $this->attributes['city'] = capitalize($city);
    }

    /**
     * @param string $country
     */
    public function setCountryAttribute($country)
    {
        $this->country()->associate((new Country())->where('name', $country)->first());
    }
}
