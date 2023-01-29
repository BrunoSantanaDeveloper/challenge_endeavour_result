<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;

class RedisConnectionException extends Exception
{

    /**
     * Handle Redis connection exception
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public static function handle(Connection $connection)
    {
        try {
            $connection->ping();
        } catch (\Exception $e) {
            // Log the exception message
            Log::error($e->getMessage());

            // Try to reconnect to Redis
            $connection->disconnect();
            $connection->connect();

            // If the connection still fails, throw the exception again
            if (!$connection->ping()) {
                throw $e;
            }
        }
    }
}
