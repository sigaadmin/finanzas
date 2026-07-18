<?php

namespace App\Data\Settings;

use App\Enums\Settings\LocalDataResetScope;

final readonly class LocalDataResetResult
{
    /**
     * @param  list<string>  $fileWarnings
     */
    public function __construct(
        public LocalDataResetScope $scope,
        public int $deletedRecords,
        public array $fileWarnings = [],
    ) {}
}
