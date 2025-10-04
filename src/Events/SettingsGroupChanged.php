<?php

namespace Backpack\Settings\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingsGroupChanged
{
    use Dispatchable, SerializesModels;

    public string $group;
    /** @var array<string,mixed> */
    public array $before;
    /** @var array<string,mixed> */
    public array $after;
    /** @var array<string, array{old:mixed,new:mixed}> */
    public array $diff;

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<string, array{old:mixed,new:mixed}> $diff
     */
    public function __construct(string $group, array $before, array $after, array $diff)
    {
        $this->group  = $group;
        $this->before = $before;
        $this->after  = $after;
        $this->diff   = $diff;
    }
}
