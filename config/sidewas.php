<?php

return [
    // Activate only after the round, looping, closure and reporting integration checks in BRD stage 7.
    'reviu_putaran_enabled' => env('SIDEWAS_REVIU_PUTARAN_ENABLED', false),
    // BRD 1.4 revision: enable together with compilation, review, dates and reports after R7.
    'alur_revisi_enabled' => env('SIDEWAS_ALUR_REVISI_ENABLED', false),
];
