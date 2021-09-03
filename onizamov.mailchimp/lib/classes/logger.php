<?php

namespace Onizamov\MailChimp\Classes;

use Onizamov\MailChimp\Orm\MailchimpLogTable;

class Logger
{
    /** @var $instances - экземпляр одиночки */
    private static $instances = [];

    protected function __construct()
    {
    }

    /**
     * Запрет Клонирование и десериализация.
     */
    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Метод, используемый для получения экземпляра Одиночки.
     */
    public static function getInstance()
    {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static();
        }
        return self::$instances[$subclass];
    }

    /**
     * Пишем запись в журнале в открытый файловый ресурс.
     */
    private function writeLog(string $status, string $request, string $response): void
    {
        MailchimpLogTable::add(
            [
                'status'   => $status,
                'request'  => $request,
                'response' => $response,
            ]
        );
    }

    /**
     * Просто удобный ярлык для уменьшения объёма кода, необходимого для
     * регистрации сообщений из клиентского кода.
     */
    public static function log(string $status, string $request, string $response): void
    {
        $logger = static::getInstance();
        $logger->writeLog($status, $request, $response);
    }
}
