<?php

return [
    /**
     * The prefix of the database tables, can be null.
     */
    'table_prefix' => env('BPMN_MANAGER_TABLE_PREFIX', 'bpmn_'),

    /**
     * Default for production is 'null' logger, so all messages will be discarded.
     * For local/staging use the default channel.
     */
    'logger' => env('BPMN_MANAGER_LOGGER', app()->isProduction() ? 'null' : env('LOG_CHANNEL')),
];
