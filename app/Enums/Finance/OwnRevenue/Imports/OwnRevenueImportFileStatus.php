<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueImportFileStatus: string
{
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case NeedsCorrection = 'needs_correction';
    case Ready = 'ready';
    case Confirmed = 'confirmed';
    case Replaced = 'replaced';
    case Discarded = 'discarded';
    case Failed = 'failed';
    case ParserPending = 'parser_pending';
}
