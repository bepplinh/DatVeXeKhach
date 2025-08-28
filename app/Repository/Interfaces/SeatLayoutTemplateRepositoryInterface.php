<?php

namespace App\Repository\Interfaces;

use App\Models\SeatLayoutTemplate;

interface SeatLayoutTemplateRepositoryInterface
{
    public function layoutGrouped(SeatLayoutTemplate $tpl): array;
}
