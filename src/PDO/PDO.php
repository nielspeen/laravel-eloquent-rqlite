<?php

namespace Wanwire\RQLite\PDO;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;
use PDO as BasePDO;
use PDOException;
use ReturnTypeWillChange;
use Wanwire\RQLite\Interfaces\PDOInterface;

class PDO extends BasePDO implements PDOInterface
{
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = '4001';

    public const RQLITE_ATTR_CONSISTENCY = 1;

    private const DSN_REGEX = '/^rqlite:(?:host=([^;]*))?(?:;port=([^;]*))?(?:;username=([^;]*))?(?:;password=([^;]*))?$/';
    private \CurlHandle $connection;
    private PDOStatement $lastStatement;
    private string $baseUrl;

    private array $attributes = [
        'consistency' => 'strong',
    ];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct($dsn, $username = null, $passwd = null, $options = [])
    {
        $params = self::parseDSN($dsn);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true);

        if (isset($params['username']) && isset($params['password'])) {
            $username = $params['username'];
            $password = $params['password'];
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        }

        $this->connection = $ch;
        $this->baseUrl = "http://{$params['host']}:{$params['port']}";
    }

    private static function parseDSN($dsn)
    {
        $matches = [];

        if (!preg_match(self::DSN_REGEX, $dsn, $matches)) {
            throw new PDOException(sprintf('Invalid DSN %s', $dsn));
        }

        return [
            'host' => !empty($matches[1]) ? $matches[1] : self::DEFAULT_HOST,
            'port' => !empty($matches[2]) ? $matches[2] : self::DEFAULT_PORT,
            'username' => !empty($matches[3]) ? $matches[3] : null,
            'password' => !empty($matches[4]) ? $matches[4] : null,
        ];
    }

    public function beginTransaction(): bool
    {
        throw new PDOException('BEGIN invalid for rqlite.');
    }

    public function commit(): bool
    {
        throw new PDOException('COMMIT invalid for rqlite.');
    }

    public function exec($statement): int
    {
        return $this->prepare($statement)->execute()->rowCount();
    }

    public function getAttribute(int $attribute): mixed
    {
        switch ($attribute) {
            case self::RQLITE_ATTR_CONSISTENCY:
                return $this->attributes['consistency'];
        }

        return null;
    }

    public function lastInsertId($name = null): string
    {
        return (string) $this->lastStatement->lastInsertId;
    }


    #[ReturnTypeWillChange] public function prepare(string $query, array $options = [])
    {
        // we need to store this so we can extract the lastInsertId
        $this->lastStatement = new PDOStatement($query, $this->connection, $this->baseUrl, $this->attributes['consistency']);
        return $this->lastStatement;
    }

    public function query(?string $query = null, ?int $fetchMode = null, mixed ...$fetchModeArgs): Result
    {
        return $this->prepare($query)->execute();
    }

    public function quote(string $string, $type = BasePDO::PARAM_STR): string|false
    {
//        if (is_int($string) || is_float($string)) {
//            return $string;
//        }

        $string = str_replace("'", "''", $string);

        return "'".addcslashes($string, "\000\n\r\\\032")."'";
    }

    public function rollBack(): bool
    {
        throw new PDOException('ROLLBACK invalid for rqlite.');
    }

    public function setAttribute($attribute, $value): bool
    {
        switch ($attribute) {
            case self::RQLITE_ATTR_CONSISTENCY:
                $this->attributes['consistency'] = $value;
                break;
        }

        return true;
    }

}
