<?php

namespace App\Events;

use App\Models\FolioItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FolioItemPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FolioItem $folioItem,
    ) {}
}
