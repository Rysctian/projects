<?php

return [
    'attachments' => [
        'enabled' => (bool) env('ATTACHMENT_UPLOADS_ENABLED', false),
        'max_kilobytes' => (int) env('ATTACHMENT_MAX_KILOBYTES', 10240),
        'extensions' => array_values(array_filter(explode(',', (string) env('ATTACHMENT_ALLOWED_EXTENSIONS', '')))),
    ],
];
