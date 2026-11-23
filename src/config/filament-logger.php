<?php

return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',

    'activity_resource' => \Z3d0X\FilamentLogger\Resources\ActivityResource::class,
    'scoped_to_tenant' => true,
    'navigation_sort' => null,

    // Pencatatan memakai log eksplisit: trait LogsActivity pada model, service
    // (AlurStatusService, PenggunaService), dan listener App\Listeners\CatatAutentikasi.
    // Logger otomatis dimatikan agar tidak ada log ganda dan notifikasi tidak ikut tercatat.
    'resources' => [
        'enabled' => false,
        'log_name' => 'Resource',
        'logger' => \Z3d0X\FilamentLogger\Loggers\ResourceLogger::class,
        'color' => 'success',
        'exclude' => [],
        'cluster' => null,
        'navigation_group' => 'Pengaturan',
    ],

    'access' => [
        'enabled' => false,
        'logger' => \Z3d0X\FilamentLogger\Loggers\AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
    ],

    'notifications' => [
        'enabled' => false,
        'logger' => \Z3d0X\FilamentLogger\Loggers\NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
    ],

    'models' => [
        'enabled' => false,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => \Z3d0X\FilamentLogger\Loggers\ModelLogger::class,
        'register' => [],
    ],

    'custom' => [],
];
