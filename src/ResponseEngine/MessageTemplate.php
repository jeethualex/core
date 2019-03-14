<?php

namespace OpenDialogAi\ResponseEngine;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Yaml\Yaml;

/**
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $outgoing_intent_id
 * @property String $name
 * @property String $conditions
 * @property String $message_markup
 */
class MessageTemplate extends Model
{
    protected $fillable = [
        'name',
        'conditions',
        'message_markup',
        'outgoing_intent_id',
    ];

    /**
     * Get the outgoing intent that owns the message template.
     */
    public function outgoingIntent()
    {
        return $this->belongsTo('OpenDialogAi\ResponseEngine\OutgoingIntent');
    }

    /**
     * Helper method: return an array of conditions.
     */
    public function getConditions()
    {
        $yaml = Yaml::parse($this->conditions);
        if (!empty($yaml['conditions']) && is_array($yaml['conditions'])) {
            return $yaml['conditions'];
        }
        return [];
    }
}
