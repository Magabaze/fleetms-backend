<?php

return [
    'pdf' => [
        'enabled' => true,
        'binary'  => env('WKHTML_PDF_BINARY', 'C:\Users\magabaze.ernesto\Downloads\wkhtmltopdf\bin\wkhtmltopdf.exe'),
        'timeout' => 120,
        'options' => [
            'enable-local-file-access' => true,
            'no-background' => false,
            'page-size' => 'A4',
            'orientation' => 'landscape',
            'margin-top' => '12',
            'margin-right' => '14',
            'margin-bottom' => '10',
            'margin-left' => '14',
            'encoding' => 'UTF-8',
            'dpi' => 150,
            'disable-smart-shrinking' => false,
            'zoom' => 1.0,
            'enable-external-links' => true,
            'enable-internal-links' => true,
            'disable-javascript' => true,
            'no-stop-slow-scripts' => true,
            'load-error-handling' => 'ignore',
            'load-media-error-handling' => 'ignore',
            'quiet' => true,
        ],
        'env' => [],
    ],
    
    'image' => [
        'enabled' => true,
        'binary'  => env('WKHTML_IMG_BINARY', 'C:\Users\magabaze.ernesto\Downloads\wkhtmltopdf\bin\wkhtmltoimage.exe'),
        'timeout' => 120,
        'options' => [
            'enable-local-file-access' => true,
        ],
        'env' => [],
    ],
];